<?php

declare(strict_types=1);

namespace app\oauth\service;

use app\common\dto\AuditContext;
use app\common\exception\OAuthProtocolException;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\AuthorizationCodeRepositoryInterface;
use app\common\repository\contract\OAuthConsentRepositoryInterface;
use app\common\repository\contract\OAuthPushedAuthorizationRequestRepositoryInterface;
use app\common\repository\contract\OAuthScopeRepositoryInterface;
use app\common\support\SecureToken;
use app\common\support\IpAddress;
use app\oauth\dto\AuthorizationRequest;
use app\oauth\dto\ClientCredentials;
use app\oauth\dto\PushedAuthorizationResult;
use app\passport\dto\AuthenticatedSession;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/** 校验授权请求，并根据用户决定签发一次性授权码或拒绝授权。 */
final class AuthorizationService
{
    private const CODE_LIFETIME_SECONDS = 300;
    private const PAR_LIFETIME_SECONDS = 60;

    public function __construct(
        private readonly OAuthClientValidationService $clientValidation,
        private readonly OAuthScopeRepositoryInterface $scopes,
        private readonly OAuthConsentRepositoryInterface $consents,
        private readonly OAuthPushedAuthorizationRequestRepositoryInterface $pushedRequests,
        private readonly AuthorizationCodeRepositoryInterface $authorizationCodes,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly SecureToken $secureToken,
        private readonly IpAddress $ipAddress,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    /** @param array<string, mixed> $parameters */
    public function validate(array $parameters): AuthorizationRequest
    {
        $requestUri = $this->stringParameterOrNull($parameters, 'request_uri', 512);
        if ($requestUri !== null) {
            $parameters = $this->parametersFromPushedRequest($parameters, $requestUri);
        }

        $clientId = $this->stringParameter($parameters, 'client_id');
        $redirectUri = $this->stringParameter($parameters, 'redirect_uri');
        $client = $this->clientValidation->resolveAuthorizationClient($clientId, $redirectUri);

        // 客户端与回调地址通过后，后续协议错误才允许安全地重定向给客户端。
        if ($this->stringParameter($parameters, 'response_type') !== 'code') {
            throw new OAuthProtocolException('unsupported_response_type', '仅支持 response_type=code。');
        }
        if (!in_array('code', (array) $client->allowed_response_types, true)) {
            throw new OAuthProtocolException('unauthorized_client', '该客户端不允许使用授权码响应类型。');
        }
        if (!in_array('authorization_code', (array) $client->allowed_grant_types, true)) {
            throw new OAuthProtocolException('unauthorized_client', '该客户端不允许使用授权码模式。');
        }

        $codeChallenge = $this->stringParameter($parameters, 'code_challenge');
        $codeChallengeMethod = $this->stringParameter($parameters, 'code_challenge_method');
        if ($codeChallengeMethod !== 'S256') {
            throw new OAuthProtocolException('invalid_request', '必须使用 PKCE S256。');
        }
        if (preg_match('/^[A-Za-z0-9_-]{43,128}$/D', $codeChallenge) !== 1) {
            throw new OAuthProtocolException('invalid_request', 'code_challenge 格式无效。');
        }

        $state = $this->optionalStringParameter($parameters, 'state', 512);
        $nonce = $this->optionalStringParameter($parameters, 'nonce', 255);
        $requestedNames = $this->parseScopeNames($parameters['scope'] ?? null);
        $resolvedScopes = $this->resolveAllowedScopes($client->id, $requestedNames);

        return new AuthorizationRequest(
            $client,
            $redirectUri,
            $resolvedScopes,
            $codeChallenge,
            $state,
            $nonce,
            $requestUri,
        );
    }

    /** @param array<string, mixed> $parameters */
    public function push(
        array $parameters,
        ClientCredentials $credentials,
        ?AuditContext $auditContext = null,
    ): PushedAuthorizationResult {
        $client = $this->clientValidation->authenticateTokenClient(
            $credentials->clientId,
            $credentials->clientSecret,
            $credentials->method,
        );
        $parameters['client_id'] = $credentials->clientId;
        unset($parameters['client_secret']);

        $request = $this->validate($parameters);
        $storedParameters = $this->pushedParameters($parameters, $request);
        $requestUri = 'urn:ietf:params:oauth:request_uri:' . $this->secureToken->generate();
        $now = $this->now();

        $this->transactions->run(function () use (
            $requestUri,
            $client,
            $storedParameters,
            $now,
            $auditContext,
        ): void {
            $this->pushedRequests->create([
                'request_uri_hash' => $this->secureToken->hash($requestUri),
                'client_id' => $client->id,
                'parameters' => $storedParameters,
                'expires_at' => $now->add(new DateInterval('PT' . self::PAR_LIFETIME_SECONDS . 'S')),
                'created_at' => $now,
            ]);
            $this->auditLogs->record([
                'event_type' => 'oauth.authorization.request_pushed',
                'client_id' => $client->id,
                ...$this->auditAttributes($auditContext),
                'success' => true,
                'details' => ['scopes' => $storedParameters['scope']],
            ]);
        });

        return new PushedAuthorizationResult($requestUri, self::PAR_LIFETIME_SECONDS);
    }

    public function consentRequired(AuthorizationRequest $request, int $userId): bool
    {
        if ((bool) $request->client->require_consent) {
            return true;
        }

        $consent = $this->consents->findActive($userId, $request->client->id, $this->now());
        if ($consent === null) {
            return true;
        }

        return array_diff($request->scopeNames(), (array) $consent->scopes) !== [];
    }

    public function approve(
        AuthorizationRequest $request,
        AuthenticatedSession $identity,
        ?AuditContext $auditContext = null,
    ): string
    {
        $now = $this->now();
        $rawCode = $this->secureToken->generate();
        $scopeNames = $request->scopeNames();

        $this->transactions->run(function () use ($request, $identity, $auditContext, $now, $rawCode, $scopeNames): void {
            $this->consumePushedRequest($request, $now);
            $this->consents->grant($identity->user->id, $request->client->id, $scopeNames, null);
            $this->authorizationCodes->create([
                'code_hash' => $this->secureToken->hash($rawCode),
                'client_id' => $request->client->id,
                'user_id' => $identity->user->id,
                'redirect_uri' => $request->redirectUri,
                'scopes' => $scopeNames,
                'code_challenge' => $request->codeChallenge,
                'code_challenge_method' => 'S256',
                'nonce' => $request->nonce,
                'auth_time' => $identity->session->created_at ?? $now,
                'expires_at' => $now->add(new DateInterval('PT' . self::CODE_LIFETIME_SECONDS . 'S')),
                'created_at' => $now,
            ]);
            $this->auditLogs->record([
                'event_type' => 'oauth.authorization.approved',
                'user_id' => $identity->user->id,
                'client_id' => $request->client->id,
                ...$this->auditAttributes($auditContext),
                'success' => true,
                'details' => ['scopes' => $scopeNames],
            ]);
        });

        return $this->buildRedirect($request->redirectUri, [
            'code' => $rawCode,
            'state' => $request->state,
        ]);
    }

    public function deny(
        AuthorizationRequest $request,
        AuthenticatedSession $identity,
        ?AuditContext $auditContext = null,
    ): string
    {
        $now = $this->now();
        $this->transactions->run(function () use ($request, $identity, $auditContext, $now): void {
            $this->consumePushedRequest($request, $now);
            $this->auditLogs->record([
                'event_type' => 'oauth.authorization.denied',
                'user_id' => $identity->user->id,
                'client_id' => $request->client->id,
                ...$this->auditAttributes($auditContext),
                'success' => true,
                'details' => ['scopes' => $request->scopeNames()],
            ]);
        });

        return $this->errorRedirect($request, 'access_denied', '用户拒绝了授权请求。');
    }

    public function errorRedirect(AuthorizationRequest $request, string $error, string $description): string
    {
        return $this->buildRedirect($request->redirectUri, [
            'error' => $error,
            'error_description' => $description,
            'state' => $request->state,
        ]);
    }

    /**
     * 仅在客户端与回调地址重新校验通过后构造错误重定向。
     *
     * @param array<string, mixed> $parameters
     */
    public function tryBuildErrorRedirect(array $parameters, OAuthProtocolException $exception): ?string
    {
        try {
            $clientId = $this->stringParameter($parameters, 'client_id');
            $redirectUri = $this->stringParameter($parameters, 'redirect_uri');
            $this->clientValidation->resolveAuthorizationClient($clientId, $redirectUri);
        } catch (OAuthProtocolException) {
            return null;
        }

        $state = $parameters['state'] ?? null;
        return $this->buildRedirect($redirectUri, [
            'error' => $exception->oauthError,
            'error_description' => $exception->getMessage(),
            'state' => is_string($state) && strlen($state) <= 512 ? $state : null,
        ]);
    }

    /**
     * @param list<string> $requestedNames
     * @return list<\app\common\model\OAuthScope>
     */
    private function resolveAllowedScopes(int $clientId, array $requestedNames): array
    {
        $allowed = $this->scopes->findAllowedForClient($clientId);
        $allowedByName = [];
        foreach ($allowed as $scope) {
            $allowedByName[$scope->name] = $scope;
        }

        if ($requestedNames === []) {
            $requestedNames = array_values(array_map(
                static fn ($scope): string => $scope->name,
                array_filter($allowed, static fn ($scope): bool => (bool) $scope->is_default),
            ));
        }

        $resolved = [];
        foreach ($requestedNames as $name) {
            if (!isset($allowedByName[$name])) {
                throw new OAuthProtocolException('invalid_scope', '请求包含未授权或无效的 Scope。');
            }
            $resolved[] = $allowedByName[$name];
        }

        return $resolved;
    }

    /**
     * 解析 PAR 引用时只接受展示提示和决定字段，拒绝客户端再提交任何
     * 协议参数；因此 URL 中手动改写 scope、redirect_uri 或 PKCE 会直接失败。
     *
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function parametersFromPushedRequest(array $parameters, string $requestUri): array
    {
        foreach (array_keys($parameters) as $name) {
            if (!in_array($name, ['request_uri', 'display', 'decision'], true)) {
                throw new OAuthProtocolException('invalid_request', 'request_uri 不能与其他授权参数同时使用。');
            }
        }

        $pushed = $this->pushedRequests->findUsableByHash(
            $this->secureToken->hash($requestUri),
            $this->now(),
        );
        if ($pushed === null) {
            throw new OAuthProtocolException('invalid_request', 'request_uri 无效或已过期。');
        }

        /** @var array<string, mixed> $stored */
        $stored = $pushed->parameters;
        if (!isset($stored['client_id'], $stored['redirect_uri'], $stored['scope'])) {
            throw new OAuthProtocolException('invalid_request', 'request_uri 数据无效。');
        }

        return $stored;
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function pushedParameters(array $parameters, AuthorizationRequest $request): array
    {
        $allowed = ['client_id', 'redirect_uri', 'response_type', 'scope', 'state', 'code_challenge', 'code_challenge_method', 'nonce', 'display'];
        $stored = [];
        foreach ($allowed as $name) {
            $value = $parameters[$name] ?? null;
            if (is_string($value) && $value !== '') {
                $stored[$name] = $value;
            }
        }
        $stored['scope'] = implode(' ', $request->scopeNames());
        unset($stored['display']);

        return $stored;
    }

    private function consumePushedRequest(AuthorizationRequest $request, DateTimeImmutable $now): void
    {
        if ($request->requestUri === null) {
            return;
        }
        if (!$this->pushedRequests->consume($this->secureToken->hash($request->requestUri), $now)) {
            throw new OAuthProtocolException('invalid_request', 'request_uri 无效、已过期或已使用。');
        }
    }

    /** @return list<string> */
    private function parseScopeNames(mixed $scope): array
    {
        if ($scope === null || $scope === '') {
            return [];
        }
        if (!is_string($scope) || strlen($scope) > 1000) {
            throw new OAuthProtocolException('invalid_scope', 'scope 参数格式无效。');
        }

        $names = preg_split('/ +/', trim($scope), -1, PREG_SPLIT_NO_EMPTY);
        if ($names === false) {
            throw new OAuthProtocolException('invalid_scope', 'scope 参数格式无效。');
        }

        foreach ($names as $name) {
            if (preg_match('/^[\x21\x23-\x5B\x5D-\x7E]+$/D', $name) !== 1) {
                throw new OAuthProtocolException('invalid_scope', 'scope 参数格式无效。');
            }
        }

        return array_values(array_unique($names));
    }

    /** @param array<string, mixed> $parameters */
    private function stringParameter(array $parameters, string $name): string
    {
        $value = $parameters[$name] ?? null;
        if (!is_string($value) || $value === '') {
            throw new OAuthProtocolException('invalid_request', "缺少或无效的 {$name} 参数。");
        }

        return $value;
    }

    /** @param array<string, mixed> $parameters */
    private function optionalStringParameter(array $parameters, string $name, int $maxLength): ?string
    {
        $value = $parameters[$name] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || strlen($value) > $maxLength) {
            throw new OAuthProtocolException('invalid_request', "{$name} 参数格式无效。");
        }

        return $value;
    }

    /** @param array<string, mixed> $parameters */
    private function stringParameterOrNull(array $parameters, string $name, int $maxLength): ?string
    {
        $value = $parameters[$name] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value) || strlen($value) > $maxLength) {
            throw new OAuthProtocolException('invalid_request', "{$name} 参数格式无效。");
        }

        return $value;
    }

    /** @param array<string, string|null> $parameters */
    private function buildRedirect(string $redirectUri, array $parameters): string
    {
        $parameters = array_filter($parameters, static fn (?string $value): bool => $value !== null);
        $separator = str_contains($redirectUri, '?') ? '&' : '?';

        return $redirectUri . $separator . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
    }

    /** @return array{request_id:string,ip_address:?string,user_agent:?string}|array{} */
    private function auditAttributes(?AuditContext $context): array
    {
        if ($context === null) {
            return [];
        }

        return [
            'request_id' => $context->requestId,
            'ip_address' => $this->ipAddress->toBinary($context->ipAddress),
            'user_agent' => $context->userAgent === null ? null : mb_substr($context->userAgent, 0, 500),
        ];
    }
}

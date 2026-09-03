<?php

declare(strict_types=1);

namespace app\oauth\service;

use app\common\dto\AuditContext;
use app\common\enum\TokenEndpointAuthMethod;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\support\SecureToken;
use app\common\support\IpAddress;
use DateTimeImmutable;
use DateTimeZone;

/** 认证客户端并撤销其持有的 Access Token 或 Refresh Token。 */
final class TokenRevocationService
{
    public function __construct(
        private readonly OAuthClientValidationService $clientValidation,
        private readonly AccessTokenRepositoryInterface $accessTokens,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly SecureToken $secureToken,
        private readonly IpAddress $ipAddress,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function revoke(
        string $clientId,
        ?string $clientSecret,
        TokenEndpointAuthMethod $authenticationMethod,
        string $rawToken,
        ?string $tokenTypeHint,
        ?AuditContext $auditContext = null,
    ): void {
        $client = $this->clientValidation->authenticateTokenClient(
            $clientId,
            $clientSecret,
            $authenticationMethod,
        );
        $tokenHash = $this->secureToken->hash($rawToken);
        $refreshFirst = $tokenTypeHint === 'refresh_token';

        if ($refreshFirst && $this->revokeRefreshToken($tokenHash, $client->id, $auditContext)) {
            return;
        }
        if ($this->revokeAccessToken($tokenHash, $client->id, $auditContext)) {
            return;
        }
        if (!$refreshFirst) {
            $this->revokeRefreshToken($tokenHash, $client->id, $auditContext);
        }

        // RFC 7009 要求未知、过期或已撤销令牌同样返回成功，防止令牌探测。
    }

    private function revokeAccessToken(string $tokenHash, int $clientId, ?AuditContext $auditContext): bool
    {
        $token = $this->accessTokens->findByHash($tokenHash);
        if ($token === null || $token->client_id !== $clientId) {
            return false;
        }

        $now = $this->now();
        if (!$this->accessTokens->revokeByHash($tokenHash, $now)) {
            return true;
        }
        $this->auditLogs->record([
            'event_type' => 'oauth.token.revoked',
            'user_id' => $token->user_id,
            'client_id' => $clientId,
            ...$this->auditAttributes($auditContext),
            'success' => true,
            'details' => ['token_type' => 'access_token'],
        ]);

        return true;
    }

    private function revokeRefreshToken(string $tokenHash, int $clientId, ?AuditContext $auditContext): bool
    {
        $token = $this->refreshTokens->findByHash($tokenHash);
        if ($token === null || $token->client_id !== $clientId) {
            return false;
        }
        if ($token->revoked_at !== null) {
            return true;
        }

        $now = $this->now();
        $this->transactions->run(function () use ($token, $clientId, $now, $auditContext): void {
            $this->refreshTokens->revokeFamily($token->family_id, $now);
            $this->accessTokens->revokeForClientAndUser($clientId, $token->user_id, $now);
            $this->auditLogs->record([
                'event_type' => 'oauth.token.revoked',
                'user_id' => $token->user_id,
                'client_id' => $clientId,
                ...$this->auditAttributes($auditContext),
                'success' => true,
                'details' => ['token_type' => 'refresh_token', 'family_id' => $token->family_id],
            ]);
        });

        return true;
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

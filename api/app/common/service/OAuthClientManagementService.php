<?php

declare(strict_types=1);

namespace app\common\service;

use app\common\enum\OAuthApplicationType;
use app\common\enum\OAuthClientStatus;
use app\common\enum\OAuthClientType;
use app\common\enum\TokenEndpointAuthMethod;
use app\common\exception\BusinessException;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\model\OAuthClient;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\OAuthClientManagementRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\repository\contract\OAuthScopeRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\support\PasswordHasher;
use app\common\support\SecureToken;
use app\common\dto\CreateOAuthClientInput;
use app\common\dto\CreatedOAuthClient;
use app\common\dto\UpdateOAuthClientInput;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Ulid;

/**
 * 提供用户自助与后台共用的 OAuth 客户端工作流，AppSecret 仅在创建或轮换时返回一次。
 *
 * 用户入口必须保持所有者校验；只有已通过后台权限中间件的调用方才能关闭该校验。
 */
final class OAuthClientManagementService
{
    public function __construct(
        private readonly OAuthClientRepositoryInterface $clients,
        private readonly OAuthClientManagementRepositoryInterface $management,
        private readonly OAuthScopeRepositoryInterface $scopes,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly AccessTokenRepositoryInterface $accessTokens,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly SecureToken $secureToken,
        private readonly PasswordHasher $passwordHasher,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function create(
        int $ownerUserId,
        CreateOAuthClientInput $input,
        ?int $applicationId = null,
    ): CreatedOAuthClient
    {
        $applicationType = OAuthApplicationType::from($input->applicationType);
        $serviceClient = $applicationType === OAuthApplicationType::Service;
        $clientType = in_array($applicationType, [OAuthApplicationType::Web, OAuthApplicationType::Service], true)
            ? OAuthClientType::Confidential
            : OAuthClientType::Public;
        $redirectUris = $serviceClient ? [] : $this->validateRedirectUris($input->redirectUris, $applicationType);
        if (!$serviceClient && $redirectUris === []) {
            throw new BusinessException('redirect_uri_required', '用户登录接入至少需要一个回调地址。', 422);
        }
        if ($serviceClient && $input->scopes !== ['service']) {
            throw new BusinessException('invalid_service_scope', '服务端 API 接入只能使用机器调用 Scope。', 422);
        }
        if (!$serviceClient && in_array('service', $input->scopes, true)) {
            throw new BusinessException('invalid_scope', '用户登录应用不能申请机器调用 Scope。', 422);
        }
        $resolvedScopes = $this->scopes->findActiveByNames(array_values(array_unique($input->scopes)));
        if (count($resolvedScopes) !== count(array_unique($input->scopes))) {
            throw new BusinessException('invalid_scope', '包含不存在或已禁用的 Scope。', 422);
        }

        $plainSecret = $clientType === OAuthClientType::Confidential
            ? 'ms_' . $this->secureToken->generate(48)
            : null;
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));

        /** @var CreatedOAuthClient $result */
        $result = $this->transactions->run(function () use (
            $ownerUserId,
            $input,
            $applicationType,
            $serviceClient,
            $clientType,
            $redirectUris,
            $resolvedScopes,
            $plainSecret,
            $now,
            $applicationId,
        ): CreatedOAuthClient {
            $client = $this->clients->create([
                'application_id' => $applicationId,
                'client_id' => 'moo_' . $this->secureToken->generate(18),
                'name' => trim($input->name),
                'description' => $input->description === null ? null : trim($input->description),
                'logo_url' => $input->logoUrl,
                'client_type' => $clientType,
                'application_type' => $applicationType,
                'token_endpoint_auth_method' => $clientType === OAuthClientType::Confidential
                    ? TokenEndpointAuthMethod::ClientSecretBasic
                    : TokenEndpointAuthMethod::None,
                'require_pkce' => !$serviceClient,
                'require_consent' => !$serviceClient,
                'allowed_grant_types' => $serviceClient
                    ? ['client_credentials']
                    : ['authorization_code', 'refresh_token'],
                'allowed_response_types' => $serviceClient ? [] : ['code'],
                'access_token_ttl' => (int) config('oauth.access_token_ttl'),
                'refresh_token_ttl' => (int) config('oauth.refresh_token_ttl'),
                'status' => OAuthClientStatus::Active,
                'owner_user_id' => $ownerUserId,
            ]);

            $this->management->addRedirectUris($client->id, $redirectUris);
            $this->management->attachScopes(
                $client->id,
                array_map(static fn ($scope): int => $scope->id, $resolvedScopes),
            );
            if ($plainSecret !== null) {
                $this->management->createSecret([
                    'client_id' => $client->id,
                    'secret_id' => (string) new Ulid(),
                    'secret_hash' => $this->passwordHasher->hash($plainSecret),
                    'description' => '初始 AppSecret',
                    'created_at' => $now,
                ]);
            }

            $this->auditLogs->record([
                'event_type' => 'oauth.client.created',
                'user_id' => $ownerUserId,
                'client_id' => $client->id,
                'success' => true,
                'details' => [
                    'application_type' => $applicationType->value,
                    'client_type' => $clientType->value,
                ],
            ]);

            return new CreatedOAuthClient($client, $plainSecret);
        });

        return $result;
    }

    /** @return list<OAuthClient> */
    public function list(int $ownerUserId, bool $enforceOwnership = true): array
    {
        return $enforceOwnership
            ? $this->management->listOwnedByUser($ownerUserId)
            : $this->clients->listAll();
    }

    public function detail(int $ownerUserId, string $clientId, bool $enforceOwnership = true): OAuthClient
    {
        $client = $enforceOwnership
            ? $this->management->findOwnedByClientId($ownerUserId, $clientId)
            : $this->clients->findByClientId($clientId);
        if ($client === null) {
            throw new BusinessException('oauth_client_not_found', 'OAuth 应用不存在。', 404);
        }

        return $client;
    }

    public function update(
        int $ownerUserId,
        string $clientId,
        UpdateOAuthClientInput $input,
        bool $enforceOwnership = true,
    ): OAuthClient
    {
        $client = $this->detail($ownerUserId, $clientId, $enforceOwnership);
        if (
            $input->name === null
            && !$input->descriptionProvided
            && $input->redirectUris === null
            && $input->scopes === null
        ) {
            throw new BusinessException('empty_update', '至少需要提供一个可更新字段。', 422);
        }

        $applicationType = $client->application_type instanceof OAuthApplicationType
            ? $client->application_type
            : OAuthApplicationType::from($client->application_type);
        $redirectUris = $input->redirectUris === null
            ? null
            : $this->validateRedirectUris($input->redirectUris, $applicationType);
        $resolvedScopes = null;
        if ($input->scopes !== null) {
            $uniqueScopes = array_values(array_unique($input->scopes));
            $resolvedScopes = $this->scopes->findActiveByNames($uniqueScopes);
            if (count($resolvedScopes) !== count($uniqueScopes)) {
                throw new BusinessException('invalid_scope', '包含不存在或已禁用的 Scope。', 422);
            }
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
        $this->transactions->run(function () use (
            $client,
            $ownerUserId,
            $input,
            $redirectUris,
            $resolvedScopes,
            $now,
        ): void {
            if ($input->name !== null) {
                $client->name = trim($input->name);
            }
            if ($input->descriptionProvided) {
                $client->description = $input->description === null ? null : trim($input->description);
            }
            $this->clients->save($client);

            if ($redirectUris !== null) {
                $this->management->replaceRedirectUris($client->id, $redirectUris);
            }
            if ($resolvedScopes !== null) {
                $this->management->replaceScopes(
                    $client->id,
                    array_map(static fn ($scope): int => $scope->id, $resolvedScopes),
                );
                // Scope 变更后撤销存量令牌，避免旧令牌继续保留已移除权限。
                $this->accessTokens->revokeForClient($client->id, $now);
                $this->refreshTokens->revokeForClient($client->id, $now);
            }

            $this->auditLogs->record([
                'event_type' => 'oauth.client.updated',
                'user_id' => $ownerUserId,
                'client_id' => $client->id,
                'success' => true,
                'details' => [
                    'redirect_uris_changed' => $redirectUris !== null,
                    'scopes_changed' => $resolvedScopes !== null,
                ],
            ]);
        });

        return $client->refresh();
    }

    public function rotateSecret(int $ownerUserId, string $clientId, bool $enforceOwnership = true): string
    {
        $client = $this->detail($ownerUserId, $clientId, $enforceOwnership);
        if ($client->client_type !== OAuthClientType::Confidential) {
            throw new BusinessException('public_client_has_no_secret', '公开客户端不能创建 AppSecret。', 422);
        }

        $plainSecret = 'ms_' . $this->secureToken->generate(48);
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
        $this->transactions->run(function () use ($client, $ownerUserId, $plainSecret, $now): void {
            // 新旧 Secret 不设置重叠窗口，事务提交后旧 Secret 立即失效。
            $this->management->revokeSecretsForClient($client->id, $now);
            $this->management->createSecret([
                'client_id' => $client->id,
                'secret_id' => (string) new Ulid(),
                'secret_hash' => $this->passwordHasher->hash($plainSecret),
                'description' => '轮换 AppSecret',
                'created_at' => $now,
            ]);
            $this->auditLogs->record([
                'event_type' => 'oauth.client.secret_rotated',
                'user_id' => $ownerUserId,
                'client_id' => $client->id,
                'success' => true,
            ]);
        });

        return $plainSecret;
    }

    public function updateStatus(
        int $ownerUserId,
        string $clientId,
        OAuthClientStatus $status,
        bool $enforceOwnership = true,
    ): OAuthClient
    {
        $client = $this->detail($ownerUserId, $clientId, $enforceOwnership);
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
        $this->transactions->run(function () use ($client, $ownerUserId, $status, $now): void {
            $client->status = $status;
            $this->clients->save($client);
            if ($status === OAuthClientStatus::Disabled) {
                // 禁用必须同时撤销令牌，防止重新启用后旧令牌恢复有效。
                $this->accessTokens->revokeForClient($client->id, $now);
                $this->refreshTokens->revokeForClient($client->id, $now);
            }
            $this->auditLogs->record([
                'event_type' => 'oauth.client.status_changed',
                'user_id' => $ownerUserId,
                'client_id' => $client->id,
                'success' => true,
                'details' => ['status' => $status->value],
            ]);
        });

        return $client->refresh();
    }

    /** @return list<string> */
    public function redirectUris(int $internalClientId): array
    {
        return $this->management->redirectUris($internalClientId);
    }

    /** @return list<string> */
    public function scopeNames(int $internalClientId): array
    {
        return $this->management->scopeNames($internalClientId);
    }

    /**
     * @param list<int> $clientIds
     * @return array<int, array{redirect_uris: list<string>, scopes: list<string>}>
     */
    public function clientConfigurations(array $clientIds): array
    {
        return $this->management->configurationsByClientIds($clientIds);
    }

    /**
     * @param list<string> $redirectUris
     * @return list<string>
     */
    private function validateRedirectUris(array $redirectUris, OAuthApplicationType $applicationType): array
    {
        $validated = [];
        foreach (array_values(array_unique($redirectUris)) as $uri) {
            $parts = parse_url($uri);
            if (!is_array($parts) || isset($parts['fragment'], $parts['user']) || isset($parts['pass'])) {
                throw new BusinessException('invalid_redirect_uri', '回调地址格式无效或包含禁止内容。', 422);
            }
            $scheme = strtolower((string) ($parts['scheme'] ?? ''));
            $host = strtolower((string) ($parts['host'] ?? ''));
            if ($scheme === '' || str_contains($uri, '*')) {
                throw new BusinessException('invalid_redirect_uri', '回调地址必须使用固定且完整的 URI。', 422);
            }

            $loopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
            if ($applicationType === OAuthApplicationType::Web && $scheme !== 'https' && !$loopback) {
                throw new BusinessException('invalid_redirect_uri', 'Web 应用回调地址必须使用 HTTPS。', 422);
            }
            if ($applicationType === OAuthApplicationType::Spa && $scheme !== 'https' && !$loopback) {
                throw new BusinessException('invalid_redirect_uri', 'SPA 回调地址必须使用 HTTPS。', 422);
            }
            if ($applicationType === OAuthApplicationType::Native && $scheme === 'http' && !$loopback) {
                throw new BusinessException('invalid_redirect_uri', '原生应用的 HTTP 回调只能使用环回地址。', 422);
            }

            $validated[] = $uri;
        }

        return $validated;
    }
}


<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\OAuthClient;
use app\common\model\OAuthClientSecret;

/** 定义 OAuth 应用所有者管理客户端关联配置所需的持久化操作。 */
interface OAuthClientManagementRepositoryInterface
{
    /** @return list<OAuthClient> */
    public function listOwnedByUser(int $ownerUserId): array;

    /**
     * @param list<int> $applicationIds
     * @return list<OAuthClient>
     */
    public function listByApplicationIds(array $applicationIds): array;

    public function findOwnedByClientId(int $ownerUserId, string $clientId): ?OAuthClient;

    /** @param list<string> $redirectUris */
    public function addRedirectUris(int $clientId, array $redirectUris): void;

    /** @param list<string> $redirectUris */
    public function replaceRedirectUris(int $clientId, array $redirectUris): void;

    /** @param list<int> $scopeIds */
    public function attachScopes(int $clientId, array $scopeIds): void;

    /** @param list<int> $scopeIds */
    public function replaceScopes(int $clientId, array $scopeIds): void;

    /** @param array<string, mixed> $attributes */
    public function createSecret(array $attributes): OAuthClientSecret;

    public function revokeSecretsForClient(int $clientId, \DateTimeImmutable $revokedAt): int;

    /** @return list<string> */
    public function redirectUris(int $clientId): array;

    /** @return list<string> */
    public function scopeNames(int $clientId): array;
}

<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthClient;
use app\common\model\OAuthClientRedirectUri;
use app\common\model\OAuthClientScope;
use app\common\model\OAuthClientSecret;
use app\common\model\OAuthScope;
use app\common\repository\contract\OAuthClientManagementRepositoryInterface;
use DateTimeImmutable;

/** 通过 Eloquent 管理 OAuth 客户端回调地址、Scope 和密钥关联。 */
final class OAuthClientManagementRepository implements OAuthClientManagementRepositoryInterface
{
    public function listOwnedByUser(int $ownerUserId): array
    {
        /** @var list<OAuthClient> $clients */
        $clients = OAuthClient::query()
            ->where('owner_user_id', $ownerUserId)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->all();

        return $clients;
    }

    /** @param list<int> $applicationIds */
    public function listByApplicationIds(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        /** @var list<OAuthClient> $clients */
        $clients = OAuthClient::query()
            ->whereIn('application_id', $applicationIds)
            ->orderBy('id')
            ->get()
            ->all();

        return $clients;
    }

    public function findOwnedByClientId(int $ownerUserId, string $clientId): ?OAuthClient
    {
        $client = OAuthClient::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('client_id', $clientId)
            ->first();

        return $client instanceof OAuthClient ? $client : null;
    }

    public function addRedirectUris(int $clientId, array $redirectUris): void
    {
        $now = new DateTimeImmutable();
        foreach ($redirectUris as $redirectUri) {
            OAuthClientRedirectUri::query()->create([
                'client_id' => $clientId,
                'redirect_uri' => $redirectUri,
                'created_at' => $now,
            ]);
        }
    }

    public function replaceRedirectUris(int $clientId, array $redirectUris): void
    {
        OAuthClientRedirectUri::query()->where('client_id', $clientId)->delete();
        $this->addRedirectUris($clientId, $redirectUris);
    }

    public function attachScopes(int $clientId, array $scopeIds): void
    {
        $now = new DateTimeImmutable();
        foreach ($scopeIds as $scopeId) {
            OAuthClientScope::query()->create([
                'client_id' => $clientId,
                'scope_id' => $scopeId,
                'created_at' => $now,
            ]);
        }
    }

    public function replaceScopes(int $clientId, array $scopeIds): void
    {
        OAuthClientScope::query()->where('client_id', $clientId)->delete();
        $this->attachScopes($clientId, $scopeIds);
    }

    public function createSecret(array $attributes): OAuthClientSecret
    {
        return OAuthClientSecret::query()->create($attributes);
    }

    public function revokeSecretsForClient(int $clientId, DateTimeImmutable $revokedAt): int
    {
        return OAuthClientSecret::query()
            ->where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }

    public function redirectUris(int $clientId): array
    {
        /** @var list<string> $uris */
        $uris = OAuthClientRedirectUri::query()
            ->where('client_id', $clientId)
            ->orderBy('id')
            ->pluck('redirect_uri')
            ->all();

        return $uris;
    }

    public function scopeNames(int $clientId): array
    {
        $scopeIds = OAuthClientScope::query()->where('client_id', $clientId)->pluck('scope_id');
        /** @var list<string> $names */
        $names = OAuthScope::query()
            ->whereIn('id', $scopeIds)
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return $names;
    }
}

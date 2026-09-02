<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthClientScope;
use app\common\model\OAuthScope;
use app\common\repository\contract\OAuthScopeRepositoryInterface;

/**
 * 通过 Eloquent 解析有效 Scope 及客户端 Scope 配置。
 */
final class OAuthScopeRepository implements OAuthScopeRepositoryInterface
{
    public function findActiveByName(string $name): ?OAuthScope
    {
        $scope = OAuthScope::query()
            ->where('name', $name)
            ->where('status', 'active')
            ->first();

        return $scope instanceof OAuthScope ? $scope : null;
    }

    public function findAllActive(): array
    {
        /** @var list<OAuthScope> $scopes */
        $scopes = OAuthScope::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get()
            ->all();

        return $scopes;
    }

    public function findActiveByNames(array $names): array
    {
        if ($names === []) {
            return [];
        }

        /** @var list<OAuthScope> $scopes */
        $scopes = OAuthScope::query()
            ->whereIn('name', array_values(array_unique($names)))
            ->where('status', 'active')
            ->get()
            ->values()
            ->all();

        return $scopes;
    }

    public function findAllowedForClient(int $clientId): array
    {
        $scopeIds = OAuthClientScope::query()
            ->where('client_id', $clientId)
            ->pluck('scope_id');

        /** @var list<OAuthScope> $scopes */
        $scopes = OAuthScope::query()
            ->whereIn('id', $scopeIds)
            ->where('status', 'active')
            ->get()
            ->values()
            ->all();

        return $scopes;
    }
}

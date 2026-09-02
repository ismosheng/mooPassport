<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthSigningKey;
use app\common\repository\contract\OAuthSigningKeyRepositoryInterface;
use DateTimeImmutable;

/** 查询已生效且尚未过期的活动或退役中 OIDC 公钥。 */
final class OAuthSigningKeyRepository implements OAuthSigningKeyRepositoryInterface
{
    public function findPublishable(DateTimeImmutable $now): array
    {
        /** @var list<OAuthSigningKey> $keys */
        $keys = OAuthSigningKey::query()
            ->whereIn('status', ['active', 'retiring'])
            ->where('not_before', '<=', $now)
            ->where(static function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderByDesc('created_at')
            ->get()
            ->all();

        return $keys;
    }

    public function findActiveForSigning(DateTimeImmutable $now): ?OAuthSigningKey
    {
        $key = OAuthSigningKey::query()
            ->where('status', 'active')
            ->where('not_before', '<=', $now)
            ->where(static function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderByDesc('created_at')
            ->first();

        return $key instanceof OAuthSigningKey ? $key : null;
    }

    public function create(array $attributes): OAuthSigningKey
    {
        return OAuthSigningKey::query()->create($attributes);
    }
}

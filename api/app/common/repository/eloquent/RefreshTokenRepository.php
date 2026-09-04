<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthRefreshToken;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use DateTimeImmutable;

/**
 * 持久化刷新令牌哈希，并提供防重放的原子更新。
 */
final class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function create(array $attributes): OAuthRefreshToken
    {
        return OAuthRefreshToken::query()->create($attributes);
    }

    public function findByHash(string $tokenHash): ?OAuthRefreshToken
    {
        $token = OAuthRefreshToken::query()->where('token_hash', $tokenHash)->first();

        return $token instanceof OAuthRefreshToken ? $token : null;
    }

    public function consume(string $tokenHash, DateTimeImmutable $usedAt): bool
    {
        // 条件消费是检测并发重放的关键边界。
        return OAuthRefreshToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $usedAt)
            ->update(['used_at' => $usedAt]) === 1;
    }

    public function revokeFamily(string $familyId, DateTimeImmutable $revokedAt): int
    {
        // 任一已轮换令牌被重复使用，都视为其整个后代令牌族已泄露。
        return OAuthRefreshToken::query()
            ->where('family_id', $familyId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }

    public function revokeForClient(int $clientId, DateTimeImmutable $revokedAt): int
    {
        return OAuthRefreshToken::query()
            ->where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }

    public function revokeForClientAndUser(int $clientId, int $userId, DateTimeImmutable $revokedAt): int
    {
        return OAuthRefreshToken::query()
            ->where('client_id', $clientId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }

    public function revokeForUser(int $userId, DateTimeImmutable $revokedAt): int
    {
        return OAuthRefreshToken::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }
}

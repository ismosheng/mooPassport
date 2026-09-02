<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthAccessToken;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use DateTimeImmutable;

/**
 * 持久化不透明访问令牌哈希并执行撤销更新。
 */
final class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function create(array $attributes): OAuthAccessToken
    {
        return OAuthAccessToken::query()->create($attributes);
    }

    public function findActiveByHash(string $tokenHash, DateTimeImmutable $now): ?OAuthAccessToken
    {
        $token = (new OAuthAccessToken())->newQuery()
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $now)
            ->first();

        return $token instanceof OAuthAccessToken ? $token : null;
    }

    public function findByHash(string $tokenHash): ?OAuthAccessToken
    {
        $token = OAuthAccessToken::query()->where('token_hash', $tokenHash)->first();

        return $token instanceof OAuthAccessToken ? $token : null;
    }

    public function revokeByHash(string $tokenHash, DateTimeImmutable $revokedAt): bool
    {
        return OAuthAccessToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]) === 1;
    }

    public function revokeForClientAndUser(int $clientId, int $userId, DateTimeImmutable $revokedAt): int
    {
        return OAuthAccessToken::query()
            ->where('client_id', $clientId)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }

    public function revokeForClient(int $clientId, DateTimeImmutable $revokedAt): int
    {
        return OAuthAccessToken::query()
            ->where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }

    public function revokeForUser(int $userId, DateTimeImmutable $revokedAt): int
    {
        return OAuthAccessToken::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }
}

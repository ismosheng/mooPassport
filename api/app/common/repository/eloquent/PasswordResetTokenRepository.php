<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\PasswordResetToken;
use app\common\repository\contract\PasswordResetTokenRepositoryInterface;
use DateTimeImmutable;

/** 存储密码重置令牌哈希，并保证令牌只能成功消费一次。 */
final class PasswordResetTokenRepository implements PasswordResetTokenRepositoryInterface
{
    public function create(array $attributes): PasswordResetToken
    {
        return PasswordResetToken::query()->create($attributes);
    }

    public function findValidByHash(string $tokenHash, DateTimeImmutable $now): ?PasswordResetToken
    {
        $token = PasswordResetToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->first();

        return $token instanceof PasswordResetToken ? $token : null;
    }

    public function consume(string $tokenHash, DateTimeImmutable $consumedAt): bool
    {
        return PasswordResetToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $consumedAt)
            ->update(['consumed_at' => $consumedAt]) === 1;
    }

    public function hasIssuedSince(int $userId, DateTimeImmutable $since): bool
    {
        return PasswordResetToken::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    public function invalidateOutstandingForUser(int $userId, DateTimeImmutable $consumedAt): int
    {
        return PasswordResetToken::query()
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => $consumedAt]);
    }
}

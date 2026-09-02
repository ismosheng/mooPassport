<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\EmailVerificationToken;
use app\common\repository\contract\EmailVerificationTokenRepositoryInterface;
use DateTimeImmutable;

/** 存储邮箱验证令牌哈希，并执行原子的一次性消费。 */
final class EmailVerificationTokenRepository implements EmailVerificationTokenRepositoryInterface
{
    public function create(array $attributes): EmailVerificationToken
    {
        return EmailVerificationToken::query()->create($attributes);
    }

    public function findValidByHash(string $tokenHash, DateTimeImmutable $now): ?EmailVerificationToken
    {
        $token = (new EmailVerificationToken())->newQuery()
            ->where('token_hash', $tokenHash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $now)
            ->first();

        return $token instanceof EmailVerificationToken ? $token : null;
    }

    public function consume(string $tokenHash, DateTimeImmutable $consumedAt): bool
    {
        // 条件更新可阻止同一验证链接被并发重复使用。
        return EmailVerificationToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', $consumedAt)
            ->update(['consumed_at' => $consumedAt]) === 1;
    }

    public function hasIssuedSince(int $userId, DateTimeImmutable $since): bool
    {
        return EmailVerificationToken::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', $since)
            ->exists();
    }

    public function invalidateOutstandingForUser(int $userId, DateTimeImmutable $consumedAt): int
    {
        return EmailVerificationToken::query()
            ->where('user_id', $userId)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => $consumedAt]);
    }
}

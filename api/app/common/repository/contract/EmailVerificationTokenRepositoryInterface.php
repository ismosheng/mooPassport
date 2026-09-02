<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\EmailVerificationToken;
use DateTimeImmutable;

/** 持久化一次性邮箱验证令牌哈希。 */
interface EmailVerificationTokenRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): EmailVerificationToken;

    public function findValidByHash(string $tokenHash, DateTimeImmutable $now): ?EmailVerificationToken;

    public function consume(string $tokenHash, DateTimeImmutable $consumedAt): bool;

    public function hasIssuedSince(int $userId, DateTimeImmutable $since): bool;

    public function invalidateOutstandingForUser(int $userId, DateTimeImmutable $consumedAt): int;
}

<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\PasswordResetToken;
use DateTimeImmutable;

/** 定义一次性密码重置令牌的签发、查询和原子消费操作。 */
interface PasswordResetTokenRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): PasswordResetToken;

    public function findValidByHash(string $tokenHash, DateTimeImmutable $now): ?PasswordResetToken;

    public function consume(string $tokenHash, DateTimeImmutable $consumedAt): bool;

    public function hasIssuedSince(int $userId, DateTimeImmutable $since): bool;

    public function invalidateOutstandingForUser(int $userId, DateTimeImmutable $consumedAt): int;
}

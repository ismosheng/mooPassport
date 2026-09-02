<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\LoginAttempt;
use DateTimeImmutable;

/**
 * 存储并统计登录尝试，用于防止接口滥用。
 */
interface LoginAttemptRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function record(array $attributes): LoginAttempt;

    public function countRecentFailuresByIdentifier(string $identifierHash, DateTimeImmutable $since): int;

    public function countRecentFailuresByIp(string $ipAddress, DateTimeImmutable $since): int;
}

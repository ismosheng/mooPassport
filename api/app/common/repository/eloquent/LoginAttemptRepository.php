<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\LoginAttempt;
use app\common\repository\contract\LoginAttemptRepositoryInterface;
use DateTimeImmutable;

/**
 * 持久化登录记录，用于账号和 IP 限流。
 */
final class LoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    public function record(array $attributes): LoginAttempt
    {
        return LoginAttempt::query()->create($attributes);
    }

    public function countRecentFailuresByIdentifier(string $identifierHash, DateTimeImmutable $since): int
    {
        return LoginAttempt::query()
            ->where('login_identifier_hash', $identifierHash)
            ->where('succeeded', false)
            ->where('created_at', '>=', $since)
            ->count();
    }

    public function countRecentFailuresByIp(string $ipAddress, DateTimeImmutable $since): int
    {
        return LoginAttempt::query()
            ->where('ip_address', $ipAddress)
            ->where('succeeded', false)
            ->where('created_at', '>=', $since)
            ->count();
    }
}

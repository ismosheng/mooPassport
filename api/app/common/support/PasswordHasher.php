<?php

declare(strict_types=1);

namespace app\common\support;

/** 封装 Argon2id 密码哈希及透明的计算成本升级。 */
final class PasswordHasher
{
    /** @var array<string, int> */
    private const OPTIONS = [
        'memory_cost' => 65536,
        'time_cost' => 4,
        // Some PHP Argon2 implementations only support a single lane.
        'threads' => 1,
    ];

    public function hash(string $password): string
    {
        return password_hash($password, PASSWORD_ARGON2ID, self::OPTIONS);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID, self::OPTIONS);
    }
}

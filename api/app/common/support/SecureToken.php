<?php

declare(strict_types=1);

namespace app\common\support;

/** 生成 URL 安全的高熵令牌及不可逆查询哈希。 */
final class SecureToken
{
    public function generate(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token, true);
    }
}

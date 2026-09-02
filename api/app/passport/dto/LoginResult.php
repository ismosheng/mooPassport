<?php

declare(strict_types=1);

namespace app\passport\dto;

use app\common\model\User;
use DateTimeImmutable;

/**
 * 将新浏览器会话令牌一次性返回给 HTTP 边界层。
 */
final readonly class LoginResult
{
    public function __construct(
        public User $user,
        public string $sessionToken,
        public DateTimeImmutable $expiresAt,
    ) {
    }
}

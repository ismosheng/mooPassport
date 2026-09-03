<?php

declare(strict_types=1);

namespace app\passport\dto;

/** 封装已校验的本地账号注册参数。 */
final readonly class RegisterInput
{
    public function __construct(
        public string $username,
        public string $email,
        public string $password,
        public string $displayName,
        public ?string $ipAddress,
        public ?string $userAgent,
        public ?string $requestId = null,
    ) {
    }
}

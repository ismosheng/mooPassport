<?php

declare(strict_types=1);

namespace app\passport\dto;

/** 封装已校验的登录凭据与请求元数据。 */
final readonly class LoginInput
{
    public function __construct(
        public string $identifier,
        public string $password,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace app\oauth\dto;

/** 封装一次授权码交换后只向 HTTP 边界暴露一次的原始令牌。 */
final readonly class TokenResult
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public ?string $refreshToken,
        public string $scope,
        public ?string $idToken = null,
    ) {
    }
}

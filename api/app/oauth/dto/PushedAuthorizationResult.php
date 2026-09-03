<?php

declare(strict_types=1);

namespace app\oauth\dto;

/**
 * 返回 PAR 端点生成的短时、不透明 request_uri。
 */
final readonly class PushedAuthorizationResult
{
    public function __construct(
        public string $requestUri,
        public int $expiresIn,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace app\common\exception;

use RuntimeException;

/** 携带 OAuth 协议错误码，供协议控制器生成标准错误响应。 */
final class OAuthProtocolException extends RuntimeException
{
    public function __construct(
        public readonly string $oauthError,
        string $description,
        public readonly int $httpStatus = 400,
    ) {
        parent::__construct($description);
    }
}

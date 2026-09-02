<?php

declare(strict_types=1);

namespace app\common\exception;

use RuntimeException;

/**
 * 携带稳定的业务错误码，同时避免泄露内部异常信息。
 */
class BusinessException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 400,
    ) {
        parent::__construct($message);
    }
}

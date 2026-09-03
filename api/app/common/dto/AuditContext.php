<?php

declare(strict_types=1);

namespace app\common\dto;

/** 封装审计事件允许持久化的请求元数据，不包含 Cookie、凭据或 Token。 */
final readonly class AuditContext
{
    public function __construct(
        public string $requestId,
        public ?string $ipAddress,
        public ?string $userAgent,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\OAuthAuditLog;

/**
 * 追加安全审计事件，严禁写入任何原始凭据。
 */
interface AuditLogRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function record(array $attributes): OAuthAuditLog;
}

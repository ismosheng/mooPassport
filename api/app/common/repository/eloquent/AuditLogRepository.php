<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthAuditLog;
use app\common\repository\contract\AuditLogRepositoryInterface;

/**
 * 通过 Eloquent 追加不可变的 OAuth 及账号安全事件。
 */
final class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function record(array $attributes): OAuthAuditLog
    {
        return OAuthAuditLog::query()->create($attributes);
    }
}

<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthAuditLog;
use app\common\repository\contract\AuditLogRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;

/**
 * 通过 Eloquent 追加不可变的 OAuth 及账号安全事件。
 */
final class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function record(array $attributes): OAuthAuditLog
    {
        // 审计时间不能依赖数据库服务器时区，否则同一流程会出现相差 8 小时的记录。
        $attributes['created_at'] ??= new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
        return OAuthAuditLog::query()->create($attributes);
    }
}

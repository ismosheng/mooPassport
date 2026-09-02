<?php

declare(strict_types=1);

namespace app\admin\repository\eloquent;

use app\admin\repository\contract\DashboardRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;
use support\Db;

/** 使用数据库聚合工作台指标，不加载完整模型以避免无意义的内存开销。 */
final class DashboardRepository implements DashboardRepositoryInterface
{
    public function summary(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $today = $now->setTime(0, 0);

        return [
            'applications' => Db::table('moo_applications')->count(),
            'users' => Db::table('moo_users')->whereNull('deleted_at')->count(),
            'active_sessions' => Db::table('moo_user_sessions')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', $now)
                ->count(),
            'security_events_today' => Db::table('moo_oauth_audit_logs')
                ->where('created_at', '>=', $today)
                ->count(),
        ];
    }
}

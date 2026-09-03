<?php

declare(strict_types=1);

namespace app\admin\service;

use app\admin\repository\contract\AuditLogQueryRepositoryInterface;
use app\common\exception\BusinessException;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** 组织安全审计只读检索，按北京时间解释管理端日期边界。 */
final class AuditLogQueryService
{
    public function __construct(private readonly AuditLogQueryRepositoryInterface $logs) {}

    /** @return array{items:list<object>,total:int,event_types:list<string>,page:int,per_page:int} */
    public function search(string $keyword, ?string $eventType, ?bool $success, ?string $startedOn, ?string $endedOn, int $page, int $perPage): array
    {
        try {
            $zone = new DateTimeZone('Asia/Shanghai');
            $startedAt = $startedOn ? new DateTimeImmutable($startedOn . ' 00:00:00', $zone) : null;
            $endedAt = $endedOn ? new DateTimeImmutable($endedOn . ' 23:59:59.999999', $zone) : null;
        } catch (Throwable) {
            throw new BusinessException('invalid_date_range', '审计时间范围格式无效。', 422);
        }
        $now = new DateTimeImmutable('now', $zone);
        $endedAt ??= $now;
        $startedAt ??= $endedAt->modify('-30 days')->setTime(0, 0);
        if ($startedAt > $endedAt || $startedAt->diff($endedAt)->days > 31) {
            throw new BusinessException('audit_range_too_large', '单次最多查询 31 天审计日志。', 422);
        }
        $result = $this->logs->search(trim($keyword), $eventType, $success, $startedAt, $endedAt, $page, $perPage);
        return [...$result, 'page' => $page, 'per_page' => $perPage];
    }
}

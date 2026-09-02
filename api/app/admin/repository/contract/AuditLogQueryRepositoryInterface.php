<?php

declare(strict_types=1);

namespace app\admin\repository\contract;

use DateTimeImmutable;

/** 定义后台只读审计查询边界，审计记录不允许修改或删除。 */
interface AuditLogQueryRepositoryInterface
{
    /** @return array{items:list<object>,total:int,event_types:list<string>} */
    public function search(string $keyword, ?string $eventType, ?bool $success, ?DateTimeImmutable $startedAt, ?DateTimeImmutable $endedAt, int $page, int $perPage): array;
}

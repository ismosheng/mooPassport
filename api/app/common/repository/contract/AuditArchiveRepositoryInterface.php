<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use DateTimeImmutable;

/** 定义审计热数据向月归档表迁移的持久化边界。 */
interface AuditArchiveRepositoryInterface
{
    public function acquireLock(): bool;

    public function releaseLock(): void;

    /** @return array{month:string,row_count:int}|null */
    public function archiveBatch(DateTimeImmutable $cutoff, int $batchSize): ?array;

    /** @return list<string> */
    public function purgeBefore(DateTimeImmutable $cutoff): array;
}

<?php

declare(strict_types=1);

namespace app\common\service;

use app\common\repository\contract\AuditArchiveRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;

/** 协调审计归档批次并使用数据库锁阻止多进程重复执行。 */
final class AuditArchiveService
{
    public function __construct(private readonly AuditArchiveRepositoryInterface $archives) {}

    /** @return array{batches:int,rows:int,dropped_archives:int} */
    public function run(int $retentionDays, int $batchSize, int $maxBatches, int $coldRetentionDays, bool $deleteEnabled): array
    {
        $locked = $this->archives->acquireLock();
        if (!$locked) return ['batches' => 0, 'rows' => 0, 'dropped_archives' => 0];
        try {
            $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify(sprintf('-%d days', $retentionDays));
            $batches = 0; $rows = 0;
            while ($batches < $maxBatches) {
                $result = $this->archives->archiveBatch($cutoff, $batchSize);
                if ($result === null) break;
                $batches++; $rows += $result['row_count'];
            }
            $dropped = $deleteEnabled ? $this->archives->purgeBefore((new DateTimeImmutable('now', new DateTimeZone('UTC')))->modify(sprintf('-%d days', $coldRetentionDays))) : [];
            return ['batches' => $batches, 'rows' => $rows, 'dropped_archives' => count($dropped)];
        } finally {
            $this->archives->releaseLock();
        }
    }
}

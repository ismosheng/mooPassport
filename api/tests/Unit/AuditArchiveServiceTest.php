<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\repository\contract\AuditArchiveRepositoryInterface;
use app\common\service\AuditArchiveService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuditArchiveServiceTest extends TestCase
{
    public function testLockedRunnerSkipsArchive(): void
    {
        $repository = $this->createMock(AuditArchiveRepositoryInterface::class);
        $repository->expects(self::once())->method('acquireLock')->willReturn(false);
        $repository->expects(self::never())->method('archiveBatch');
        self::assertSame(['batches' => 0, 'rows' => 0, 'dropped_archives' => 0], (new AuditArchiveService($repository))->run(90, 5000, 20, 1095, false));
    }

    public function testArchivesInBoundedBatchesAndAlwaysReleasesLock(): void
    {
        $repository = $this->createMock(AuditArchiveRepositoryInterface::class);
        $repository->method('acquireLock')->willReturn(true);
        $repository->expects(self::exactly(3))->method('archiveBatch')->with(self::isInstanceOf(DateTimeImmutable::class), 5000)
            ->willReturnOnConsecutiveCalls(['month' => '202601', 'row_count' => 5000], ['month' => '202601', 'row_count' => 1200], null);
        $repository->expects(self::once())->method('releaseLock');
        $repository->expects(self::never())->method('purgeBefore');
        self::assertSame(['batches' => 2, 'rows' => 6200, 'dropped_archives' => 0], (new AuditArchiveService($repository))->run(90, 5000, 20, 1095, false));
    }

    public function testColdArchiveDeletionRequiresExplicitSwitch(): void
    {
        $repository = $this->createMock(AuditArchiveRepositoryInterface::class);
        $repository->method('acquireLock')->willReturn(true);
        $repository->method('archiveBatch')->willReturn(null);
        $repository->expects(self::once())->method('purgeBefore')->willReturn(['moo_oauth_audit_logs_202401']);
        $repository->expects(self::once())->method('releaseLock');
        self::assertSame(1, (new AuditArchiveService($repository))->run(90, 5000, 1, 1095, true)['dropped_archives']);
    }
}

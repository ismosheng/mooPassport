<?php

declare(strict_types=1);

namespace tests\Unit;

use app\admin\repository\contract\AuditLogQueryRepositoryInterface;
use app\admin\service\AuditLogQueryService;
use app\common\exception\BusinessException;
use PHPUnit\Framework\TestCase;

final class AuditLogQueryServiceTest extends TestCase
{
    public function testRejectsRangesLongerThanThirtyOneDays(): void
    {
        $repository = $this->createMock(AuditLogQueryRepositoryInterface::class);
        $repository->expects(self::never())->method('search');
        $this->expectException(BusinessException::class);
        (new AuditLogQueryService($repository))->search('', null, null, '2026-01-01', '2026-03-01', 1, 20);
    }

    public function testDefaultsToBoundedRecentRange(): void
    {
        $repository = $this->createMock(AuditLogQueryRepositoryInterface::class);
        $repository->expects(self::once())->method('search')->with('', null, null, self::isInstanceOf(\DateTimeImmutable::class), self::isInstanceOf(\DateTimeImmutable::class), 1, 20)
            ->willReturn(['items' => [], 'total' => 0, 'event_types' => []]);
        self::assertSame(0, (new AuditLogQueryService($repository))->search('', null, null, null, null, 1, 20)['total']);
    }
}

<?php

declare(strict_types=1);

namespace tests\Unit;

use app\admin\repository\contract\AuditLogQueryRepositoryInterface;
use app\admin\service\AuditLogQueryService;
use app\common\exception\BusinessException;
use DateTimeImmutable;
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
        $repository->expects(self::once())->method('search')->with(
            '',
            null,
            null,
            self::callback(static fn (DateTimeImmutable $value): bool => $value->getTimezone()->getName() === 'Asia/Shanghai'),
            self::callback(static fn (DateTimeImmutable $value): bool => $value->getTimezone()->getName() === 'Asia/Shanghai'),
            1,
            20,
        )
            ->willReturn(['items' => [], 'total' => 0, 'event_types' => []]);
        self::assertSame(0, (new AuditLogQueryService($repository))->search('', null, null, null, null, 1, 20)['total']);
    }

    public function testTreatsSelectedDatesAsBeijingTime(): void
    {
        $repository = $this->createMock(AuditLogQueryRepositoryInterface::class);
        $repository->expects(self::once())->method('search')->with(
            'oauth.token.issued',
            null,
            true,
            self::callback(static fn (DateTimeImmutable $value): bool => $value->format(DATE_ATOM) === '2026-09-03T00:00:00+08:00'),
            self::callback(static fn (DateTimeImmutable $value): bool => $value->format('Y-m-d H:i:s.uP') === '2026-09-03 23:59:59.999999+08:00'),
            1,
            20,
        )->willReturn(['items' => [], 'total' => 0, 'event_types' => []]);

        (new AuditLogQueryService($repository))->search(
            'oauth.token.issued',
            null,
            true,
            '2026-09-03',
            '2026-09-03',
            1,
            20,
        );
    }
}

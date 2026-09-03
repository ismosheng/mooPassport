<?php

declare(strict_types=1);

namespace app\admin\service;

use app\admin\repository\contract\DashboardRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;

/** 组织管理员工作台只读摘要，不承担用户或应用管理流程。 */
final class DashboardService
{
    public function __construct(private readonly DashboardRepositoryInterface $dashboard)
    {
    }

    /** @return array<string, mixed> */
    public function summary(): array
    {
        return [
            'metrics' => $this->dashboard->summary(),
            'updated_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai')))->format(DATE_ATOM),
        ];
    }
}

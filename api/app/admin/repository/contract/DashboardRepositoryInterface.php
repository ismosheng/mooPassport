<?php

declare(strict_types=1);

namespace app\admin\repository\contract;

/** 定义后台工作台所需的只读聚合查询，禁止在此接口中修改业务数据。 */
interface DashboardRepositoryInterface
{
    /** @return array{applications:int,users:int,active_sessions:int,security_events_today:int} */
    public function summary(): array;
}

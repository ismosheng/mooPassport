<?php

declare(strict_types=1);

namespace app\common\infrastructure\database;

use Closure;

/** 隔离数据库事务设施，使业务安全分支可以在不连接 MySQL 的单元测试中验证。 */
interface TransactionManagerInterface
{
    /**
     * @template T
     * @param Closure(): T $callback
     * @return T
     */
    public function run(Closure $callback): mixed;
}

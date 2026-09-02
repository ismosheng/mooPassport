<?php

declare(strict_types=1);

namespace app\common\infrastructure\database;

use Closure;
use support\Db;

/** 使用当前 MySQL 连接执行事务，异常时由数据库层统一回滚。 */
final class DatabaseTransactionManager implements TransactionManagerInterface
{
    public function run(Closure $callback): mixed
    {
        return Db::connection()->transaction($callback);
    }
}

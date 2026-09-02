<?php

declare(strict_types=1);

namespace app\common\model;

use support\Model;

/**
 * 通行证 MySQL 模型基类。
 *
 * 这里只统一数据库连接和微秒时间格式，业务流程与事务边界必须由 Service 负责。
 */
abstract class BaseModel extends Model
{
    protected $connection = 'mysql';

    protected $dateFormat = 'Y-m-d H:i:s.u';
}

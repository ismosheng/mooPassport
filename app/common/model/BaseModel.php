<?php

declare(strict_types=1);

namespace app\common\model;

use support\Model;

/**
 * Base class for MySQL-backed Passport models.
 *
 * It centralizes the connection and microsecond timestamp format only; business
 * workflows and transaction boundaries belong to services.
 */
abstract class BaseModel extends Model
{
    protected $connection = 'mysql';

    protected $dateFormat = 'Y-m-d H:i:s.u';
}

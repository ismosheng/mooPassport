<?php

declare(strict_types=1);

namespace app\common\model;

/** 映射后台权限点定义；权限 code 是代码与数据库之间的稳定契约。 */
final class Permission extends BaseModel
{
    protected $table = 'moo_permissions';

    protected $fillable = ['code', 'name', 'module', 'description'];

    /** @var array<string, string> */
    protected $casts = ['created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
}

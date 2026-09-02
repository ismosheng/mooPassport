<?php

declare(strict_types=1);

namespace app\common\model;

use DateTimeImmutable;

/**
 * 映射后台角色定义；授权流程和并发保护必须由 Service 负责。
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_system
 * @property string $status
 * @property int $version
 * @property DateTimeImmutable $created_at
 * @property DateTimeImmutable $updated_at
 */
final class Role extends BaseModel
{
    protected $table = 'moo_roles';

    protected $fillable = ['code', 'name', 'description', 'is_system', 'status', 'version'];

    /** @var array<string, string> */
    protected $casts = [
        'is_system' => 'boolean',
        'version' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}

<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射用于限制 OAuth 令牌权限的 Scope。
 *
 * @property string $name
 * @property int $id
 * @property string $display_name
 * @property string|null $description
 * @property bool $is_default
 */
final class OAuthScope extends BaseModel
{
    protected $table = 'moo_oauth_scopes';

    protected $fillable = [
        'name', 'display_name', 'description', 'is_default', 'status',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'is_default' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}


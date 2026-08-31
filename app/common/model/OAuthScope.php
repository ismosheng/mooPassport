<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * Maps a scope that limits the privileges granted to an OAuth token.
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


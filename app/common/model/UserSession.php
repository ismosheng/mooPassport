<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * Maps a browser login session; only the SHA-256 session hash is persisted.
 */
final class UserSession extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_user_sessions';

    protected $fillable = [
        'session_hash', 'user_id', 'ip_address', 'user_agent', 'last_seen_at',
        'expires_at', 'revoked_at', 'created_at',
    ];

    protected $hidden = ['session_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'last_seen_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}


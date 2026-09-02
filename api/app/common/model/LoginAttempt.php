<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射不可变的登录尝试记录，用于限流和安全审计。
 */
final class LoginAttempt extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_login_attempts';

    protected $fillable = [
        'user_id', 'login_identifier_hash', 'ip_address', 'user_agent',
        'succeeded', 'failure_reason', 'created_at',
    ];

    protected $hidden = ['login_identifier_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'succeeded' => 'boolean',
        'created_at' => 'immutable_datetime',
    ];
}


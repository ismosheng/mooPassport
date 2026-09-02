<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射浏览器登录会话，数据库只持久化会话令牌的 SHA-256 哈希。
 *
 * @property int $id
 * @property string $session_hash
 * @property int $user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \DateTimeImmutable|null $last_seen_at
 * @property \DateTimeImmutable|null $expires_at
 * @property \DateTimeImmutable|null $revoked_at
 * @property \DateTimeImmutable|null $created_at
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


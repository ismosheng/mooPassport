<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射一次性邮箱验证令牌，数据库只保存令牌哈希。
 *
 * @property int $user_id
 * @property string $email
 * @property string $token_hash
 */
final class EmailVerificationToken extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_email_verification_tokens';

    protected $fillable = [
        'user_id', 'email', 'token_hash', 'purpose', 'expires_at', 'consumed_at',
        'created_at',
    ];

    protected $hidden = ['token_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'expires_at' => 'immutable_datetime',
        'consumed_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}


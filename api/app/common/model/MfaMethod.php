<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\enum\MfaType;

/**
 * 映射已绑定的多因素认证方式，并避免输出加密后的密钥。
 */
final class MfaMethod extends BaseModel
{
    protected $table = 'moo_mfa_methods';

    protected $fillable = [
        'user_id', 'type', 'name', 'encrypted_secret', 'credential_data',
        'enabled_at', 'last_used_at',
    ];

    protected $hidden = ['encrypted_secret', 'credential_data'];

    /** @var array<string, string> */
    protected $casts = [
        'type' => MfaType::class,
        'credential_data' => 'array',
        'enabled_at' => 'immutable_datetime',
        'last_used_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}


<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * Maps an enrolled MFA method while keeping encrypted secrets out of output.
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
        'credential_data' => 'array',
        'enabled_at' => 'immutable_datetime',
        'last_used_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}


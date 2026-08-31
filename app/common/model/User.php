<?php

declare(strict_types=1);

namespace app\common\model;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Maps a local Passport account without implementing authentication workflows.
 */
final class User extends BaseModel
{
    use SoftDeletes;

    protected $table = 'moo_users';

    protected $fillable = [
        'public_id', 'username', 'email', 'phone_country_code', 'phone_number',
        'password_hash', 'display_name', 'avatar_url', 'status',
        'email_verified_at', 'phone_verified_at', 'password_changed_at',
        'last_login_at',
    ];

    protected $hidden = ['password_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'email_verified_at' => 'immutable_datetime',
        'phone_verified_at' => 'immutable_datetime',
        'password_changed_at' => 'immutable_datetime',
        'last_login_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
        'deleted_at' => 'immutable_datetime',
    ];
}


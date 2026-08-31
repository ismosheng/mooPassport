<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * Maps an external identity linked to a local Passport user.
 */
final class UserIdentity extends BaseModel
{
    protected $table = 'moo_user_identities';

    protected $fillable = ['user_id', 'provider', 'provider_subject', 'profile'];

    /** @var array<string, string> */
    protected $casts = [
        'profile' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}


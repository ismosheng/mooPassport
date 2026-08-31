<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * Maps a one-time email verification challenge stored only by token hash.
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


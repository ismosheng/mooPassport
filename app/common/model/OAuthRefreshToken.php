<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * Maps a refresh-token rotation member and its replay-detection family.
 */
final class OAuthRefreshToken extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_oauth_refresh_tokens';

    protected $fillable = [
        'token_hash', 'family_id', 'parent_id', 'access_token_id', 'client_id',
        'user_id', 'scopes', 'expires_at', 'used_at', 'revoked_at', 'created_at',
    ];

    protected $hidden = ['token_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'immutable_datetime',
        'used_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}


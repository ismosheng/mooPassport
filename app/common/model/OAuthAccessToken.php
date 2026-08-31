<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * Maps an audience- and scope-restricted opaque access token by hash.
 */
final class OAuthAccessToken extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_oauth_access_tokens';

    protected $fillable = [
        'token_hash', 'client_id', 'user_id', 'grant_type', 'scopes', 'audience',
        'expires_at', 'revoked_at', 'created_at',
    ];

    protected $hidden = ['token_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}


<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * Maps a rotatable OIDC signing key while preventing private-key serialization.
 */
final class OAuthSigningKey extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_oauth_signing_keys';

    protected $fillable = [
        'kid', 'algorithm', 'public_jwk', 'encrypted_private_key', 'status',
        'not_before', 'expires_at', 'created_at',
    ];

    protected $hidden = ['encrypted_private_key'];

    /** @var array<string, string> */
    protected $casts = [
        'public_jwk' => 'array',
        'not_before' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}


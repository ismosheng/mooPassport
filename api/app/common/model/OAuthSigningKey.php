<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\enum\SigningKeyStatus;

/**
 * 映射可轮换的 OIDC 签名密钥，并禁止序列化私钥。
 *
 * @property string $kid
 * @property string $algorithm
 * @property array<string, mixed> $public_jwk
 * @property string $encrypted_private_key
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
        'status' => SigningKeyStatus::class,
        'public_jwk' => 'array',
        'not_before' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}


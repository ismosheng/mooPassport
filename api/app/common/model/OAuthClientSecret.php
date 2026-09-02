<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射可轮换的 OAuth 客户端密钥，明文密钥永不持久化。
 *
 * @property int $id
 * @property string $secret_hash
 */
final class OAuthClientSecret extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_oauth_client_secrets';

    protected $fillable = [
        'client_id', 'secret_id', 'secret_hash', 'description', 'last_used_at',
        'expires_at', 'revoked_at', 'created_at',
    ];

    protected $hidden = ['secret_hash'];

    /** @var array<string, string> */
    protected $casts = [
        'last_used_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}


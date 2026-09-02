<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射绑定 PKCE 挑战值的一次性授权码。
 *
 * @property int $client_id
 * @property int $user_id
 * @property string $redirect_uri
 * @property list<string> $scopes
 * @property string $code_challenge
 * @property string $code_challenge_method
 * @property string|null $nonce
 * @property \DateTimeImmutable $auth_time
 * @property \DateTimeImmutable $expires_at
 * @property \DateTimeImmutable|null $used_at
 */
final class OAuthAuthorizationCode extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_oauth_authorization_codes';

    protected $fillable = [
        'code_hash', 'client_id', 'user_id', 'redirect_uri', 'scopes',
        'code_challenge', 'code_challenge_method', 'nonce', 'auth_time',
        'expires_at', 'used_at', 'created_at',
    ];

    protected $hidden = ['code_hash', 'code_challenge', 'nonce'];

    /** @var array<string, string> */
    protected $casts = [
        'scopes' => 'array',
        'auth_time' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'used_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}


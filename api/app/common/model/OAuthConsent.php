<?php

declare(strict_types=1);

namespace app\common\model;

use DateTimeImmutable;

/**
 * 映射用户已授权给 OAuth 客户端的权限范围。
 *
 * @property int $client_id
 * @property list<string> $scopes
 * @property DateTimeImmutable $granted_at
 */
final class OAuthConsent extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_oauth_consents';

    protected $fillable = [
        'user_id', 'client_id', 'scopes', 'granted_at', 'expires_at', 'revoked_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'scopes' => 'array',
        'granted_at' => 'immutable_datetime',
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];
}


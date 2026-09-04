<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\enum\GrantType;

/**
 * 通过哈希映射受受众和权限范围约束的不透明访问令牌。
 *
 * @property int $id
 * @property int $client_id
 * @property int|null $user_id
 * @property GrantType $grant_type
 * @property list<string> $scopes
 * @property \DateTimeImmutable|null $revoked_at
 * @property \DateTimeImmutable $expires_at
 * @property \DateTimeImmutable $created_at
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
        'grant_type' => GrantType::class,
        'scopes' => 'array',
        'expires_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}


<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射刷新令牌轮换成员及其重放检测族。
 *
 * @property int $id
 * @property string $family_id
 * @property int $client_id
 * @property int $user_id
 * @property list<string> $scopes
 * @property \DateTimeImmutable $expires_at
 * @property \DateTimeImmutable|null $used_at
 * @property \DateTimeImmutable|null $revoked_at
 * @property \DateTimeImmutable $created_at
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


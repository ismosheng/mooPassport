<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射短时 Pushed Authorization Request。
 *
 * 请求参数在服务端保存并通过一次性 request_uri 引用，避免授权页面信任
 * 浏览器 URL 中可被用户修改的 scope、redirect_uri 或 PKCE 参数。
 *
 * @property int $client_id
 * @property array<string, mixed> $parameters
 * @property \DateTimeImmutable $expires_at
 * @property \DateTimeImmutable|null $used_at
 * @property \DateTimeImmutable $created_at
 */
final class OAuthPushedAuthorizationRequest extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_oauth_pushed_authorization_requests';

    protected $fillable = [
        'request_uri_hash', 'client_id', 'parameters', 'expires_at', 'used_at', 'created_at',
    ];

    protected $hidden = ['request_uri_hash', 'parameters'];

    /** @var array<string, string> */
    protected $casts = [
        'parameters' => 'array',
        'expires_at' => 'immutable_datetime',
        'used_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}

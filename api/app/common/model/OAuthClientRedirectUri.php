<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射 OAuth 客户端已注册且必须精确匹配的重定向 URI。
 */
final class OAuthClientRedirectUri extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_oauth_client_redirect_uris';

    protected $fillable = ['client_id', 'redirect_uri', 'created_at'];

    /** @var array<string, string> */
    protected $casts = ['created_at' => 'immutable_datetime'];
}


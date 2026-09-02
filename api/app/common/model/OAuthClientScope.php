<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射 OAuth 客户端允许申请的权限范围。
 */
final class OAuthClientScope extends BaseModel
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'moo_oauth_client_scopes';

    protected $fillable = ['client_id', 'scope_id', 'created_at'];

    /** @var array<string, string> */
    protected $casts = ['created_at' => 'immutable_datetime'];
}


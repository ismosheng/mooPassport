<?php

declare(strict_types=1);

namespace app\passport\validator;

use support\validation\Validator;

/** 校验创建 OAuth 客户端的基础 HTTP 参数结构。 */
final class CreateOAuthClientValidator extends Validator
{
    /** @var array<string, string> */
    protected array $rules = [
        'name' => 'required|string|min:2|max:100',
        'description' => 'nullable|string|max:500',
        'application_type' => 'required|string|in:web,spa,native,service',
        'redirect_uris' => 'nullable|array|max:10',
        'redirect_uris.*' => 'required|string|max:1000',
        'scopes' => 'required|array|min:1|max:20',
        'scopes.*' => 'required|string|max:100',
    ];

    /** @var array<string, string> */
    protected array $attributes = [
        'name' => '应用名称',
        'description' => '应用说明',
        'application_type' => '应用类型',
        'redirect_uris' => '回调地址',
        'scopes' => '权限范围',
    ];
}

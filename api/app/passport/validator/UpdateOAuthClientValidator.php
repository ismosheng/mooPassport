<?php

declare(strict_types=1);

namespace app\passport\validator;

use support\validation\Validator;

/** 校验 OAuth 应用资料和协议配置更新请求。 */
final class UpdateOAuthClientValidator extends Validator
{
    /** @var array<string, string> */
    protected array $rules = [
        'name' => 'nullable|string|min:2|max:100',
        'description' => 'nullable|string|max:500',
        'redirect_uris' => 'nullable|array|min:1|max:10',
        'redirect_uris.*' => 'required|string|max:1000',
        'scopes' => 'nullable|array|min:1|max:20',
        'scopes.*' => 'required|string|max:100',
    ];
}

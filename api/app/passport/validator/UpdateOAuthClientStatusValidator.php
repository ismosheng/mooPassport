<?php

declare(strict_types=1);

namespace app\passport\validator;

use support\validation\Validator;

/** 校验 OAuth 应用启用或禁用请求。 */
final class UpdateOAuthClientStatusValidator extends Validator
{
    /** @var array<string, string> */
    protected array $rules = ['status' => 'required|string|in:active,disabled'];
}

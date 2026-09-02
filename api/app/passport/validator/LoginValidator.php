<?php

declare(strict_types=1);

namespace app\passport\validator;

use support\validation\Validator;

/** 校验本地账号登录请求的 HTTP 参数。 */
final class LoginValidator extends Validator
{
    /** @var array<string, string> */
    protected array $rules = [
        'identifier' => 'required|string|max:191',
        'password' => 'required|string|max:128',
    ];

    /** @var array<string, string> */
    protected array $attributes = [
        'identifier' => '账号',
        'password' => '密码',
    ];
}

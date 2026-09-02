<?php

declare(strict_types=1);

namespace app\passport\validator;

use support\validation\Validator;

/** 校验登录用户修改密码的请求。 */
final class ChangePasswordValidator extends Validator
{
    /** @var array<string, string> */
    protected array $rules = [
        'current_password' => 'required|string|max:128',
        'password' => 'required|string|min:9|max:128|regex:/[A-Z]/|regex:/[a-z]/|regex:/[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]/|confirmed',
    ];

    /** @var array<string, string> */
    protected array $messages = [
        'password.min' => '密码至少需要 9 位。',
        'password.regex' => '密码必须同时包含大写字母、小写字母和特殊符号。',
        'password.confirmed' => '两次输入的密码不一致。',
    ];
}

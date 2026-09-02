<?php

declare(strict_types=1);

namespace app\passport\validator;

use support\validation\Validator;

/** 校验本地账号注册请求的 HTTP 参数。 */
final class RegisterValidator extends Validator
{
    /** @var array<string, string> */
    protected array $rules = [
        'username' => 'required|string|min:3|max:32|regex:/^[A-Za-z0-9_]+$/',
        'email' => 'required|string|email|max:191',
        'password' => 'required|string|min:9|max:128|regex:/[A-Z]/|regex:/[a-z]/|regex:/[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]/|confirmed',
        'display_name' => 'nullable|string|max:100',
    ];

    /** @var array<string, string> */
    protected array $messages = [
        'username.regex' => '用户名只能包含字母、数字和下划线。',
        'password.min' => '密码至少需要 9 位。',
        'password.regex' => '密码必须同时包含大写字母、小写字母和特殊符号。',
        'password.confirmed' => '两次输入的密码不一致。',
    ];

    /** @var array<string, string> */
    protected array $attributes = [
        'username' => '用户名',
        'email' => '邮箱',
        'password' => '密码',
        'display_name' => '显示名称',
    ];
}

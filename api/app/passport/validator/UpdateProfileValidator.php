<?php

declare(strict_types=1);

namespace app\passport\validator;

use support\validation\Validator;

/** 校验登录用户更新个人资料的请求。 */
final class UpdateProfileValidator extends Validator
{
    /** @var array<string, string> */
    protected array $rules = [
        'display_name' => 'required|string|min:1|max:100',
        'phone_country_code' => 'nullable|string|in:+86,+852,+853,+886,+1',
        'phone_number' => 'nullable|string|regex:/^[0-9]{6,15}$/',
    ];

    /** @var array<string, string> */
    protected array $messages = [
        'display_name.required' => '请输入显示名称。',
        'display_name.max' => '显示名称不能超过 100 个字符。',
        'phone_country_code.in' => '请选择支持的国家或地区代码。',
        'phone_number.regex' => '请输入 6 至 15 位数字的手机号。',
    ];
}

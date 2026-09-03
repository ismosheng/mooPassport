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
        'gender' => 'nullable|string|in:male,female,other,undisclosed',
        'birth_date' => 'nullable|string|date_format:Y-m-d',
        'bio' => 'nullable|string|max:500',
        'real_name' => 'nullable|string|min:2|max:100',
        'identity_document_type' => 'nullable|string|in:id_card,passport,other',
        'identity_document_number' => 'nullable|string|min:5|max:64|regex:/^[^\p{C}]+$/u',
    ];

    /** @var array<string, string> */
    protected array $messages = [
        'display_name.required' => '请输入显示名称。',
        'display_name.max' => '显示名称不能超过 100 个字符。',
        'phone_country_code.in' => '请选择支持的国家或地区代码。',
        'phone_number.regex' => '请输入 6 至 15 位数字的手机号。',
        'gender.in' => '请选择有效的性别。',
        'birth_date.date_format' => '出生日期格式应为 YYYY-MM-DD。',
        'bio.max' => '个人简介不能超过 500 个字符。',
        'real_name.min' => '真实姓名至少需要 2 个字符。',
        'real_name.max' => '真实姓名不能超过 100 个字符。',
        'identity_document_type.in' => '请选择有效的证件类型。',
        'identity_document_number.regex' => '证件号码不能包含控制字符。',
    ];
}

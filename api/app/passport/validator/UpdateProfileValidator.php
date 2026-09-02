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
    ];

    /** @var array<string, string> */
    protected array $messages = [
        'display_name.required' => '请输入显示名称。',
        'display_name.max' => '显示名称不能超过 100 个字符。',
    ];
}

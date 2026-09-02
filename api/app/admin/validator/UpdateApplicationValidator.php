<?php

declare(strict_types=1);

namespace app\admin\validator;

use Webman\Validation\Validator;

/** 校验逻辑应用基础资料更新，OAuth 协议配置由客户端接口维护。 */
final class UpdateApplicationValidator
{
    /** @param array<string, mixed> $data */
    public static function make(array $data): Validator
    {
        return Validator::make($data, [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'logo_url' => 'nullable|string|max:500|url',
        ], ['name.required' => '请输入应用名称。']);
    }
}

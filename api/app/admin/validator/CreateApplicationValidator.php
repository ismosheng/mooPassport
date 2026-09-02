<?php

declare(strict_types=1);

namespace app\admin\validator;

use Webman\Validation\Validator;

/** 校验创建逻辑应用所需的基础字段，能力组合规则由 Service 负责。 */
final class CreateApplicationValidator
{
    /** @param array<string, mixed> $data */
    public static function make(array $data): Validator
    {
        return Validator::make($data, [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'logo_url' => 'nullable|string|max:500|url',
            'capabilities' => 'required|array|min:1',
            'capabilities.*' => 'required|in:login,service',
            'login_application_type' => 'nullable|in:web,spa,native',
            'redirect_uris' => 'nullable|array',
            'redirect_uris.*' => 'string|max:2048',
            'login_scopes' => 'nullable|array',
            'login_scopes.*' => 'string|max:100',
        ], [
            'name.required' => '请输入应用名称。',
            'capabilities.required' => '请至少选择一种接入能力。',
            'capabilities.min' => '请至少选择一种接入能力。',
        ]);
    }
}

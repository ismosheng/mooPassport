<?php

declare(strict_types=1);

namespace app\admin\validator;

use Webman\Validation\Validator;

/** 校验角色可变资料；角色 code 不进入更新输入，避免稳定标识被误改。 */
final class UpdateRoleValidator
{
    /** @param array<string, mixed> $data */
    public static function make(array $data): Validator
    {
        return Validator::make($data, [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
            'status' => 'required|in:active,disabled',
            'version' => 'required|integer|min:1',
        ], [
            'name.required' => '请输入角色名称。',
            'status.in' => '角色状态无效。',
            'version.required' => '缺少角色版本号，请刷新后重试。',
        ]);
    }
}

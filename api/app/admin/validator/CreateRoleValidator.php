<?php

declare(strict_types=1);

namespace app\admin\validator;

use Webman\Validation\Validator;

/** 校验角色基础资料；稳定 code 创建后不允许修改。 */
final class CreateRoleValidator
{
    /** @param array<string, mixed> $data */
    public static function make(array $data): Validator
    {
        return Validator::make($data, [
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]{2,63}$/'],
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:500',
        ], [
            'code.required' => '请输入角色标识。',
            'code.regex' => '角色标识只能使用小写字母、数字和下划线，且必须以字母开头。',
            'name.required' => '请输入角色名称。',
        ]);
    }
}

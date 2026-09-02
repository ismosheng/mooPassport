<?php

declare(strict_types=1);

namespace app\admin\validator;

use Webman\Validation\Validator;

/** 校验角色权限整组替换请求，权限是否存在由 Service 校验。 */
final class UpdateRolePermissionsValidator
{
    /** @param array<string, mixed> $data */
    public static function make(array $data): Validator
    {
        return Validator::make($data, [
            'permissions' => 'present|array|max:100',
            'permissions.*' => 'string|max:100|distinct',
        ], ['permissions.present' => '请提交权限列表。']);
    }
}

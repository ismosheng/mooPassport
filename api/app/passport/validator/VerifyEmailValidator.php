<?php

declare(strict_types=1);

namespace app\passport\validator;

use support\validation\Validator;

/** 校验前端提交的邮箱验证令牌。 */
final class VerifyEmailValidator extends Validator
{
    /** @var array<string, string> */
    protected array $rules = ['token' => 'required|string|min:40|max:200'];

    /** @var array<string, string> */
    protected array $attributes = ['token' => '验证令牌'];
}

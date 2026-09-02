<?php

declare(strict_types=1);

namespace app\passport\validator;

use support\validation\Validator;

/** 校验申请密码重置邮件的请求。 */
final class ForgotPasswordValidator extends Validator
{
    /** @var array<string, string> */
    protected array $rules = ['email' => 'required|string|email|max:191'];
}

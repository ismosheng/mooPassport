<?php

declare(strict_types=1);

namespace app\passport\validator;

use support\validation\Validator;

/** 校验重新发送邮箱验证链接的请求参数。 */
final class ResendVerificationValidator extends Validator
{
    /** @var array<string, string> */
    protected array $rules = ['email' => 'required|string|email|max:191'];

    /** @var array<string, string> */
    protected array $attributes = ['email' => '邮箱'];
}

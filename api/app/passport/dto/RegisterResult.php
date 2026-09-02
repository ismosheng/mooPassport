<?php

declare(strict_types=1);

namespace app\passport\dto;

use app\common\model\User;

/** 返回注册流程创建的待验证账号。 */
final readonly class RegisterResult
{
    public function __construct(public User $user)
    {
    }
}

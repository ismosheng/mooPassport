<?php

declare(strict_types=1);

namespace app\common\enum;

/** 定义本地用户账号的生命周期状态。 */
enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Locked = 'locked';
    case Disabled = 'disabled';
}

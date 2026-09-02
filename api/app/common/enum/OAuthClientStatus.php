<?php

declare(strict_types=1);

namespace app\common\enum;

/** 定义 OAuth 客户端是否允许发起协议流程。 */
enum OAuthClientStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}

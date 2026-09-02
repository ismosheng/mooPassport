<?php

declare(strict_types=1);

namespace app\common\enum;

/** 描述 OAuth 客户端的运行环境。 */
enum OAuthApplicationType: string
{
    case Web = 'web';
    case Spa = 'spa';
    case Native = 'native';
    case Service = 'service';
}

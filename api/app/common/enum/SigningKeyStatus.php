<?php

declare(strict_types=1);

namespace app\common\enum;

/** 定义 OIDC 签名密钥发布和退役的生命周期。 */
enum SigningKeyStatus: string
{
    case Active = 'active';
    case Retiring = 'retiring';
    case Retired = 'retired';
}

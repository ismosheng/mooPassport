<?php

declare(strict_types=1);

namespace app\common\enum;

/** 定义系统支持的多因素认证方式。 */
enum MfaType: string
{
    case Totp = 'totp';
    case WebAuthn = 'webauthn';
}

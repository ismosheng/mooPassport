<?php

declare(strict_types=1);

namespace app\common\enum;

/** 区分能够保护凭据的机密客户端与公开客户端。 */
enum OAuthClientType: string
{
    case Public = 'public';
    case Confidential = 'confidential';
}

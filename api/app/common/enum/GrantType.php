<?php

declare(strict_types=1);

namespace app\common\enum;

/** 定义哞哞通行证允许使用的 OAuth 授权类型。 */
enum GrantType: string
{
    case AuthorizationCode = 'authorization_code';
    case RefreshToken = 'refresh_token';
    case ClientCredentials = 'client_credentials';
}

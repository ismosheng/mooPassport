<?php

declare(strict_types=1);

namespace app\common\enum;

/** 定义 OAuth 令牌端点支持的客户端认证方式。 */
enum TokenEndpointAuthMethod: string
{
    case None = 'none';
    case ClientSecretBasic = 'client_secret_basic';
    case ClientSecretPost = 'client_secret_post';
    case PrivateKeyJwt = 'private_key_jwt';
}

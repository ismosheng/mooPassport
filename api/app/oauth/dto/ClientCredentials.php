<?php

declare(strict_types=1);

namespace app\oauth\dto;

use app\common\enum\TokenEndpointAuthMethod;

/** 封装从 OAuth 协议请求中解析出的客户端凭据。 */
final readonly class ClientCredentials
{
    public function __construct(
        public string $clientId,
        public ?string $clientSecret,
        public TokenEndpointAuthMethod $method,
    ) {
    }
}

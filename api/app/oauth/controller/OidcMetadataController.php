<?php

declare(strict_types=1);

namespace app\oauth\controller;

use app\oauth\service\JwksService;
use app\oauth\service\OidcMetadataService;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use Webman\Http\Response;

/** 发布 OIDC Discovery、OAuth 授权服务器元数据及 JWKS 公钥集。 */
#[DisableDefaultRoute]
final class OidcMetadataController
{
    public function __construct(
        private readonly OidcMetadataService $metadata,
        private readonly JwksService $jwks,
    ) {
    }

    #[Get('/.well-known/openid-configuration', 'oidc.discovery')]
    public function openidConfiguration(): Response
    {
        return $this->publicJson($this->metadata->metadata());
    }

    #[Get('/.well-known/oauth-authorization-server', 'oauth.metadata')]
    public function authorizationServerMetadata(): Response
    {
        return $this->publicJson($this->metadata->metadata());
    }

    #[Get('/oauth/jwks', 'oidc.jwks')]
    public function jwks(): Response
    {
        return $this->publicJson($this->jwks->publicKeySet());
    }

    /** @param array<string, mixed> $payload */
    private function publicJson(array $payload): Response
    {
        return json($payload)->withHeader('Cache-Control', 'public, max-age=300');
    }
}

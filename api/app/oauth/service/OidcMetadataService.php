<?php

declare(strict_types=1);

namespace app\oauth\service;

use app\common\repository\contract\OAuthScopeRepositoryInterface;

/** 构建 OpenID Connect Discovery 和 OAuth 授权服务器元数据。 */
final class OidcMetadataService
{
    public function __construct(private readonly OAuthScopeRepositoryInterface $scopes)
    {
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        $issuer = (string) config('oauth.issuer');
        $scopeNames = array_map(
            static fn ($scope): string => $scope->name,
            $this->scopes->findAllActive(),
        );

        return [
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/oauth/authorize',
            'pushed_authorization_request_endpoint' => $issuer . '/oauth/par',
            'token_endpoint' => $issuer . '/oauth/token',
            'userinfo_endpoint' => $issuer . '/oauth/userinfo',
            'jwks_uri' => $issuer . '/.well-known/jwks.json',
            'revocation_endpoint' => $issuer . '/oauth/revoke',
            'introspection_endpoint' => $issuer . '/oauth/introspect',
            'response_types_supported' => ['code'],
            'response_modes_supported' => ['query'],
            'grant_types_supported' => ['authorization_code', 'refresh_token', 'client_credentials'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => [
                'none',
                'client_secret_basic',
                'client_secret_post',
            ],
            'revocation_endpoint_auth_methods_supported' => [
                'none',
                'client_secret_basic',
                'client_secret_post',
            ],
            'introspection_endpoint_auth_methods_supported' => [
                'client_secret_basic',
                'client_secret_post',
            ],
            'code_challenge_methods_supported' => ['S256'],
            'scopes_supported' => $scopeNames,
            'claims_supported' => [
                'sub',
                'name',
                'preferred_username',
                'picture',
                'email',
                'email_verified',
            ],
        ];
    }

}

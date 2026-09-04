<?php

declare(strict_types=1);

namespace app\oauth\controller;

use app\common\dto\AuditContext;
use app\common\exception\OAuthProtocolException;
use app\common\support\RequestId;
use app\oauth\service\TokenService;
use app\oauth\support\ClientCredentialsParser;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供 OAuth Token 端点并解析标准客户端认证方式。 */
#[DisableDefaultRoute]
final class TokenController
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly ClientCredentialsParser $credentialsParser,
    ) {
    }

    #[Get('/oauth/token', 'oauth.token.method_not_allowed')]
    public function methodNotAllowed(): Response
    {
        return $this->noStore(json([
            'error' => 'invalid_request',
            'error_description' => 'Token 端点必须使用 POST 表单请求。',
        ])->withStatus(405)->withHeader('Allow', 'POST'));
    }

    #[Post('/oauth/token', 'oauth.token')]
    public function token(Request $request): Response
    {
        try {
            $contentType = strtolower((string) $request->header('Content-Type'));
            if (!str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
                throw new OAuthProtocolException('invalid_request', 'Token 端点只接受表单编码请求。');
            }

            /** @var array<string, mixed> $parameters */
            $parameters = (array) $request->post();
            $grantType = $parameters['grant_type'] ?? null;
            if (!in_array($grantType, ['authorization_code', 'refresh_token', 'client_credentials'], true)) {
                throw new OAuthProtocolException(
                    'unsupported_grant_type',
                    '仅支持 authorization_code、refresh_token 或 client_credentials。',
                );
            }

            $credentials = $this->credentialsParser->parse($request, $parameters);
            $auditContext = $this->auditContext($request);
            if ($grantType === 'authorization_code') {
                $result = $this->tokens->exchangeAuthorizationCode(
                    $credentials->clientId,
                    $credentials->clientSecret,
                    $credentials->method,
                    $this->requiredString($parameters, 'code'),
                    $this->requiredString($parameters, 'redirect_uri'),
                    $this->requiredString($parameters, 'code_verifier'),
                    $auditContext,
                );
            } elseif ($grantType === 'refresh_token') {
                $scope = $parameters['scope'] ?? null;
                if ($scope !== null && !is_string($scope)) {
                    throw new OAuthProtocolException('invalid_scope', 'scope 参数格式无效。');
                }
                $result = $this->tokens->rotateRefreshToken(
                    $credentials->clientId,
                    $credentials->clientSecret,
                    $credentials->method,
                    $this->requiredString($parameters, 'refresh_token'),
                    $scope,
                    $auditContext,
                );
            } else {
                $scope = $parameters['scope'] ?? null;
                if ($scope !== null && !is_string($scope)) {
                    throw new OAuthProtocolException('invalid_scope', 'scope 参数格式无效。');
                }
                $result = $this->tokens->issueClientCredentials(
                    $credentials->clientId,
                    $credentials->clientSecret,
                    $credentials->method,
                    $scope,
                    $auditContext,
                );
            }

            $payload = [
                'token_type' => 'Bearer',
                'access_token' => $result->accessToken,
                'expires_in' => $result->expiresIn,
                'scope' => $result->scope,
            ];
            if ($result->refreshToken !== null) {
                $payload['refresh_token'] = $result->refreshToken;
            }
            if ($result->idToken !== null) {
                $payload['id_token'] = $result->idToken;
            }

            return $this->noStore(json($payload));
        } catch (OAuthProtocolException $exception) {
            $response = json([
                'error' => $exception->oauthError,
                'error_description' => $exception->getMessage(),
            ])->withStatus($exception->httpStatus);

            if ($exception->oauthError === 'invalid_client') {
                $response = $response->withHeader('WWW-Authenticate', 'Basic realm="oauth-token"');
            }

            return $this->noStore($response);
        }
    }

    /** @param array<string, mixed> $parameters */
    private function requiredString(array $parameters, string $name): string
    {
        $value = $parameters[$name] ?? null;
        if (!is_string($value) || $value === '') {
            throw new OAuthProtocolException('invalid_request', "缺少或无效的 {$name} 参数。");
        }
        if (strlen($value) > ($name === 'redirect_uri' ? 1000 : 512)) {
            throw new OAuthProtocolException('invalid_request', "{$name} 参数长度超出限制。");
        }

        return $value;
    }

    private function noStore(Response $response): Response
    {
        return $response
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }

    private function auditContext(Request $request): AuditContext
    {
        $userAgent = $request->header('User-Agent');

        return new AuditContext(
            RequestId::get($request),
            $request->getRealIp(),
            is_string($userAgent) ? $userAgent : null,
        );
    }
}

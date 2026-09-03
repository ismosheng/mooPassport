<?php

declare(strict_types=1);

namespace app\oauth\controller;

use app\common\exception\OAuthProtocolException;
use app\oauth\service\TokenIntrospectionService;
use app\oauth\support\ClientCredentialsParser;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供受客户端认证保护的 OAuth Token 内省端点。 */
#[DisableDefaultRoute]
final class TokenIntrospectionController
{
    public function __construct(
        private readonly TokenIntrospectionService $introspection,
        private readonly ClientCredentialsParser $credentialsParser,
    ) {
    }

    #[Get('/oauth/introspect', 'oauth.token.introspect.method_not_allowed')]
    public function methodNotAllowed(): Response
    {
        return $this->noStore(json([
            'error' => 'invalid_request',
            'error_description' => '内省端点必须使用 POST 表单请求。',
        ])->withStatus(405)->withHeader('Allow', 'POST'));
    }

    #[Post('/oauth/introspect', 'oauth.token.introspect')]
    public function introspect(Request $request): Response
    {
        try {
            $contentType = strtolower((string) $request->header('Content-Type'));
            if (!str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
                throw new OAuthProtocolException('invalid_request', '内省端点只接受表单编码请求。');
            }

            /** @var array<string, mixed> $parameters */
            $parameters = (array) $request->post();
            $rawToken = $parameters['token'] ?? null;
            if (!is_string($rawToken) || $rawToken === '' || strlen($rawToken) > 512) {
                throw new OAuthProtocolException('invalid_request', '缺少或无效的 token 参数。');
            }
            $tokenTypeHint = $parameters['token_type_hint'] ?? null;
            if ($tokenTypeHint !== null && !is_string($tokenTypeHint)) {
                throw new OAuthProtocolException('invalid_request', 'token_type_hint 参数格式无效。');
            }

            $credentials = $this->credentialsParser->parse($request, $parameters);
            $result = $this->introspection->introspect(
                $credentials->clientId,
                $credentials->clientSecret,
                $credentials->method,
                $rawToken,
                $tokenTypeHint,
            );

            return $this->noStore(json($result));
        } catch (OAuthProtocolException $exception) {
            $response = json([
                'error' => $exception->oauthError,
                'error_description' => $exception->getMessage(),
            ])->withStatus($exception->httpStatus);
            if ($exception->oauthError === 'invalid_client') {
                $response = $response->withHeader('WWW-Authenticate', 'Basic realm="oauth-introspect"');
            }

            return $this->noStore($response);
        }
    }

    private function noStore(Response $response): Response
    {
        return $response
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }
}

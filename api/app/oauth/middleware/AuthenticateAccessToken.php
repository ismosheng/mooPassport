<?php

declare(strict_types=1);

namespace app\oauth\middleware;

use app\common\exception\OAuthProtocolException;
use app\oauth\service\AccessTokenAuthenticationService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/** 要求接口携带有效的 OAuth Bearer Access Token。 */
final class AuthenticateAccessToken implements MiddlewareInterface
{
    public const CONTEXT_KEY = 'oauth_access_identity';

    public function __construct(private readonly AccessTokenAuthenticationService $authentication)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        try {
            $request->context[self::CONTEXT_KEY] = $this->authentication->authenticate(
                $this->bearerToken($request),
            );

            return $handler($request);
        } catch (OAuthProtocolException $exception) {
            return json([
                'error' => $exception->oauthError,
                'error_description' => $exception->getMessage(),
            ])->withStatus($exception->httpStatus)
                ->withHeader(
                    'WWW-Authenticate',
                    'Bearer realm="oauth-resource", error="invalid_token"',
                )
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Pragma', 'no-cache');
        }
    }

    private function bearerToken(Request $request): string
    {
        $authorization = $request->header('Authorization');
        if (!is_string($authorization) || preg_match('/^Bearer ([^\s,]+)$/iD', $authorization, $matches) !== 1) {
            throw new OAuthProtocolException('invalid_token', 'Access Token 无效或已过期。', 401);
        }

        return $matches[1];
    }
}

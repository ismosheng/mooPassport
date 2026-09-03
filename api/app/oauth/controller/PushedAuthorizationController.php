<?php

declare(strict_types=1);

namespace app\oauth\controller;

use app\common\dto\AuditContext;
use app\common\exception\OAuthProtocolException;
use app\common\support\RequestId;
use app\oauth\service\AuthorizationService;
use app\oauth\support\ClientCredentialsParser;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Post;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供 OAuth 2.0 PAR 端点，将授权参数绑定到短时一次性 request_uri。 */
#[DisableDefaultRoute]
final class PushedAuthorizationController
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly ClientCredentialsParser $credentialsParser,
    ) {
    }

    #[Post('/oauth/par', 'oauth.par')]
    public function push(Request $request): Response
    {
        try {
            $contentType = strtolower((string) $request->header('Content-Type'));
            if (!str_starts_with($contentType, 'application/x-www-form-urlencoded')) {
                throw new OAuthProtocolException('invalid_request', 'PAR 端点只接受表单编码请求。');
            }

            /** @var array<string, mixed> $parameters */
            $parameters = (array) $request->post();
            $credentials = $this->credentialsParser->parse($request, $parameters);
            $result = $this->authorization->push(
                $parameters,
                $credentials,
                $this->auditContext($request),
            );

            return json([
                'request_uri' => $result->requestUri,
                'expires_in' => $result->expiresIn,
            ])
                ->withStatus(201)
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Pragma', 'no-cache');
        } catch (OAuthProtocolException $exception) {
            $response = json([
                'error' => $exception->oauthError,
                'error_description' => $exception->getMessage(),
            ])->withStatus($exception->httpStatus);

            if ($exception->oauthError === 'invalid_client') {
                $response = $response->withHeader('WWW-Authenticate', 'Basic realm="oauth-par"');
            }

            return $response
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Pragma', 'no-cache');
        }
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

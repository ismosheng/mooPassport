<?php

declare(strict_types=1);

namespace app\oauth\controller;

use app\common\dto\AuditContext;
use app\common\exception\OAuthProtocolException;
use app\common\support\RequestId;
use app\oauth\dto\AuthorizationRequest;
use app\oauth\service\AuthorizationService;
use app\passport\dto\AuthenticatedSession;
use app\passport\middleware\AuthenticateSession;
use app\passport\middleware\ResolveSession;
use support\annotation\Middleware;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供 OAuth 授权请求检查、授权确认及授权码回调。 */
#[DisableDefaultRoute]
#[Middleware(ResolveSession::class)]
final class AuthorizationController
{
    public function __construct(private readonly AuthorizationService $authorization)
    {
    }

    #[Get('/oauth/authorize', 'oauth.authorize.inspect')]
    public function inspect(Request $request): Response
    {
        /** @var array<string, mixed> $parameters */
        $parameters = (array) $request->get();

        try {
            $authorizationRequest = $this->authorization->validate($parameters);
        } catch (OAuthProtocolException $exception) {
            // 授权页面通过 XHR 预检查参数时必须收到同源 JSON；若返回 302，浏览器会把
            // 第三方 callback 当作 XHR 继续请求，并将正常的 OAuth 错误误报为 CORS。
            if ($request->header('X-Moo-Authorization-Inspect') === '1') {
                return $this->jsonProtocolError($exception);
            }
            return $this->protocolError($parameters, $exception);
        }

        $identity = $this->identityOrError($request);
        if ($identity instanceof Response) {
            // 专用授权页面需要在登录前展示可信的应用名称和权限范围，避免用户盲目输入账号。
            return json([
                'error' => 'login_required',
                'error_description' => '请先登录哞哞通行证。',
                'client' => [
                    'client_id' => $authorizationRequest->client->client_id,
                    'name' => $authorizationRequest->client->name,
                    'description' => $authorizationRequest->client->description,
                    'logo_url' => $authorizationRequest->client->logo_url,
                ],
                'scopes' => array_map(static fn ($scope): array => [
                    'name' => $scope->name,
                    'display_name' => $scope->display_name,
                    'description' => $scope->description,
                ], $authorizationRequest->scopes),
            ])->withStatus(401);
        }

        return json([
            'client' => [
                'client_id' => $authorizationRequest->client->client_id,
                'name' => $authorizationRequest->client->name,
                'description' => $authorizationRequest->client->description,
                'logo_url' => $authorizationRequest->client->logo_url,
            ],
            'scopes' => array_map(static fn ($scope): array => [
                'name' => $scope->name,
                'display_name' => $scope->display_name,
                'description' => $scope->description,
            ], $authorizationRequest->scopes),
            'consent_required' => $this->authorization->consentRequired(
                $authorizationRequest,
                $identity->user->id,
            ),
            'state' => $authorizationRequest->state,
        ]);
    }

    #[Post('/oauth/authorize', 'oauth.authorize.decide')]
    public function decide(Request $request): Response
    {
        /** @var array<string, mixed> $parameters */
        $parameters = (array) $request->post();

        try {
            $authorizationRequest = $this->authorization->validate($parameters);
        } catch (OAuthProtocolException $exception) {
            return $this->protocolError($parameters, $exception);
        }

        $identity = $this->identityOrError($request);
        if ($identity instanceof Response) {
            return $identity;
        }
        $decision = $parameters['decision'] ?? null;
        $auditContext = $this->auditContext($request);
        if ($decision === 'approve') {
            return redirect($this->authorization->approve($authorizationRequest, $identity, $auditContext));
        }
        if ($decision === 'deny') {
            return redirect($this->authorization->deny($authorizationRequest, $identity, $auditContext));
        }

        return redirect($this->authorization->errorRedirect(
            $authorizationRequest,
            'invalid_request',
            'decision 参数必须是 approve 或 deny。',
        ));
    }

    /** @param array<string, mixed> $parameters */
    private function protocolError(array $parameters, OAuthProtocolException $exception): Response
    {
        $errorRedirect = $this->authorization->tryBuildErrorRedirect($parameters, $exception);
        if ($errorRedirect !== null) {
            return redirect($errorRedirect);
        }

        return $this->jsonProtocolError($exception);
    }

    private function jsonProtocolError(OAuthProtocolException $exception): Response
    {
        return json([
            'error' => $exception->oauthError,
            'error_description' => $exception->getMessage(),
        ])->withStatus($exception->httpStatus);
    }

    private function identityOrError(Request $request): AuthenticatedSession|Response
    {
        $identity = $request->context[AuthenticateSession::CONTEXT_KEY] ?? null;
        if (!$identity instanceof AuthenticatedSession) {
            return json([
                'error' => 'login_required',
                'error_description' => '请先登录哞哞通行证。',
            ])->withStatus(401);
        }

        return $identity;
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

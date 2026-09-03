<?php

declare(strict_types=1);

namespace app\passport\controller;

use app\common\exception\BusinessException;
use app\common\support\ApiResponse;
use app\passport\dto\AuthenticatedSession;
use app\passport\middleware\AuthenticateSession;
use app\passport\service\ConsentManagementService;
use support\annotation\Middleware;
use support\annotation\route\Delete;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供当前用户已授权 OAuth 应用列表与撤销接口。 */
#[DisableDefaultRoute]
#[RouteGroup('/passport/v1/oauth/consents')]
#[Middleware(AuthenticateSession::class)]
final class ConsentController
{
    public function __construct(private readonly ConsentManagementService $consents)
    {
    }

    #[Get('', 'passport.v1.oauth_consents.list')]
    public function list(Request $request): Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(50, max(5, (int) $request->get('per_page', 5)));

        return ApiResponse::success(
            $request,
            $this->consents->listForUser($this->identity($request)->user->id, $page, $perPage),
        );
    }

    #[Delete('/{clientId}', 'passport.v1.oauth_consents.revoke')]
    public function revoke(Request $request, string $clientId): Response
    {
        if ($clientId === '' || strlen($clientId) > 100) {
            throw new BusinessException('invalid_client_id', '应用 ID 无效。', 422);
        }

        $userAgent = $request->header('User-Agent');
        $this->consents->revokeForUser(
            $this->identity($request)->user->id,
            $clientId,
            $request->getRealIp(),
            is_string($userAgent) ? $userAgent : null,
        );

        return ApiResponse::success($request, ['message' => '已撤销该应用的授权。']);
    }

    private function identity(Request $request): AuthenticatedSession
    {
        $identity = $request->context[AuthenticateSession::CONTEXT_KEY] ?? null;
        if (!$identity instanceof AuthenticatedSession) {
            throw new BusinessException('unauthenticated', '请先登录。', 401);
        }

        return $identity;
    }
}

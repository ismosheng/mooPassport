<?php

declare(strict_types=1);

namespace app\passport\controller;

use app\common\exception\BusinessException;
use app\common\support\ApiResponse;
use app\passport\dto\AuthenticatedSession;
use app\passport\middleware\AuthenticateSession;
use app\passport\service\SessionManagementService;
use support\annotation\Middleware;
use support\annotation\route\Delete;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供当前登录用户的设备会话列表与撤销接口。 */
#[DisableDefaultRoute]
#[RouteGroup('/passport/v1/sessions')]
#[Middleware(AuthenticateSession::class)]
final class SessionController
{
    public function __construct(private readonly SessionManagementService $sessions)
    {
    }

    #[Get('', 'passport.v1.sessions.list')]
    public function list(Request $request): Response
    {
        return ApiResponse::success($request, [
            'items' => $this->sessions->list($this->identity($request)),
        ]);
    }

    #[Delete('/{sessionId}', 'passport.v1.sessions.revoke')]
    public function revoke(Request $request, string $sessionId): Response
    {
        if (!ctype_digit($sessionId) || (int) $sessionId < 1) {
            throw new BusinessException('invalid_session_id', '会话 ID 无效。', 422);
        }

        $userAgent = $request->header('User-Agent');
        $clearedCurrent = $this->sessions->revoke(
            $this->identity($request),
            (int) $sessionId,
            $request->getRealIp(),
            is_string($userAgent) ? $userAgent : null,
        );

        $response = ApiResponse::success($request, [
            'message' => $clearedCurrent ? '当前设备已退出登录。' : '已撤销该登录会话。',
            'cleared_current' => $clearedCurrent,
        ]);

        if (!$clearedCurrent) {
            return $response;
        }

        return $response->cookie(
            (string) config('auth.cookie.name'),
            '',
            -1,
            '/',
            (string) config('auth.cookie.domain'),
            (bool) config('auth.cookie.secure'),
            true,
            (string) config('auth.cookie.same_site'),
        );
    }

    #[Post('/revoke-others', 'passport.v1.sessions.revoke_others')]
    public function revokeOthers(Request $request): Response
    {
        $userAgent = $request->header('User-Agent');
        $count = $this->sessions->revokeOthers(
            $this->identity($request),
            $request->getRealIp(),
            is_string($userAgent) ? $userAgent : null,
        );

        return ApiResponse::success($request, [
            'message' => $count > 0 ? "已退出 {$count} 个其他设备。" : '没有其他登录设备。',
            'revoked_count' => $count,
        ]);
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

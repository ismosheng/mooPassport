<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\middleware\RequirePermission;
use app\admin\service\DashboardService;
use app\common\exception\BusinessException;
use app\common\service\RoleService;
use app\common\support\ApiResponse;
use app\passport\dto\AuthenticatedSession;
use app\passport\middleware\AuthenticateSession;
use support\annotation\Middleware;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供后台入口权限探测；具体业务统计由后续独立查询服务负责。 */
#[DisableDefaultRoute]
#[RouteGroup('/admin/v1')]
#[Middleware(AuthenticateSession::class, RequirePermission::class)]
final class DashboardController
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly RoleService $roles,
    ) {}

    #[Get('/access', 'admin.v1.access')]
    public function access(Request $request): Response
    {
        $identity = $request->context[AuthenticateSession::CONTEXT_KEY] ?? null;
        if (!$identity instanceof AuthenticatedSession) {
            throw new BusinessException('unauthenticated', '请先登录。', 401);
        }

        return ApiResponse::success($request, [
            'authorized' => true,
            'roles' => $this->roles->codesForUser($identity->user->id),
            'permissions' => $this->roles->effectivePermissionCodes($identity->user->id),
        ]);
    }

    #[Get('/dashboard/summary', 'admin.v1.dashboard.summary')]
    public function summary(Request $request): Response
    {
        return ApiResponse::success($request, $this->dashboard->summary());
    }
}

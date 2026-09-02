<?php
declare(strict_types=1);
namespace app\admin\controller;
use app\admin\middleware\RequirePermission;
use app\admin\service\SystemSettingsService;
use app\common\support\ApiResponse;
use app\passport\dto\AuthenticatedSession;
use app\passport\middleware\AuthenticateSession;
use support\annotation\Middleware;
use support\annotation\route\{DisableDefaultRoute,Get,Put,RouteGroup};
use Webman\Http\{Request,Response};

/** 提供白名单系统设置读取和更新接口。 */
#[DisableDefaultRoute]
#[RouteGroup('/admin/v1/settings')]
#[Middleware(AuthenticateSession::class, RequirePermission::class)]
final class SystemSettingsController
{
    public function __construct(private readonly SystemSettingsService $settings) {}
    #[Get('', 'admin.v1.settings.list')]
    public function list(Request $request): Response { return ApiResponse::success($request, ['items' => array_values($this->settings->all())]); }
    #[Put('', 'admin.v1.settings.update')]
    public function update(Request $request): Response
    {
        $data = (array) $request->post();
        $identity = $request->context[AuthenticateSession::CONTEXT_KEY];
        if (!$identity instanceof AuthenticatedSession) return ApiResponse::error($request, 'unauthenticated', '请先登录。', 401);
        $this->settings->update($identity->user->id, (array) ($data['values'] ?? []), (array) ($data['versions'] ?? []));
        return ApiResponse::success($request, ['updated' => true]);
    }
}

<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\middleware\RequirePermission;
use app\admin\service\ApplicationLogoService;
use app\common\exception\BusinessException;
use app\common\support\ApiResponse;
use app\passport\middleware\AuthenticateSession;
use support\annotation\Middleware;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Http\UploadFile;

/** 接收管理员上传的应用品牌资源，不负责修改应用资料。 */
#[DisableDefaultRoute]
#[RouteGroup('/admin/v1/application-assets')]
#[Middleware(AuthenticateSession::class, RequirePermission::class)]
final class ApplicationAssetController
{
    public function __construct(private readonly ApplicationLogoService $logos)
    {
    }

    #[Post('/logo', 'admin.v1.application_assets.logo')]
    public function logo(Request $request): Response
    {
        $file = $request->file('logo');
        if (!$file instanceof UploadFile) {
            throw new BusinessException('application_logo_required', '请选择应用图标。', 422);
        }
        return ApiResponse::success($request, ['logo_url' => $this->logos->store($file)], 201);
    }
}

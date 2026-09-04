<?php

declare(strict_types=1);

namespace app\passport\controller;

use app\common\support\ApiResponse;
use app\passport\service\PublicSiteService;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供匿名站点入口需要的非敏感配置，不承担后台设置管理。 */
#[DisableDefaultRoute]
#[RouteGroup('/api/v1/public')]
final class PublicSiteController
{
    public function __construct(private readonly PublicSiteService $site)
    {
    }

    #[Get('/site', 'api.v1.public.site')]
    public function show(Request $request): Response
    {
        return ApiResponse::success($request, $this->site->configuration());
    }
}

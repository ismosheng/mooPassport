<?php

declare(strict_types=1);

namespace app\admin\middleware;

use app\common\exception\BusinessException;
use app\common\service\RoleService;
use app\passport\dto\AuthenticatedSession;
use app\passport\middleware\AuthenticateSession;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 按后台命名路由执行细粒度权限校验。
 *
 * 映射未登记时默认拒绝，确保以后增加后台接口不会因漏配权限而意外开放。
 */
final class RequirePermission implements MiddlewareInterface
{
    /** @var array<string, string> */
    private const ROUTE_PERMISSIONS = [
        'admin.v1.dashboard.summary' => 'admin.dashboard.read',
        'admin.v1.applications.list' => 'admin.applications.read',
        'admin.v1.applications.detail' => 'admin.applications.read',
        'admin.v1.applications.create' => 'admin.applications.create',
        'admin.v1.applications.update' => 'admin.applications.update',
        'admin.v1.applications.status' => 'admin.applications.status.update',
        'admin.v1.applications.delete' => 'admin.applications.delete',
        'admin.v1.application_assets.logo' => 'admin.applications.update',
        'admin.v1.oauth_clients.list' => 'admin.applications.read',
        'admin.v1.oauth_clients.detail' => 'admin.applications.read',
        'admin.v1.oauth_clients.create' => 'admin.applications.create',
        'admin.v1.oauth_clients.update' => 'admin.applications.update',
        'admin.v1.oauth_clients.rotate_secret' => 'admin.applications.secret.rotate',
        'admin.v1.oauth_clients.status' => 'admin.applications.status.update',
        'admin.v1.users.list' => 'admin.users.read',
        'admin.v1.users.detail' => 'admin.users.read',
        'admin.v1.users.status' => 'admin.users.status.update',
        'admin.v1.users.force_logout' => 'admin.users.sessions.revoke',
        'admin.v1.audit_logs.list' => 'admin.audit.read',
        'admin.v1.roles.list' => 'admin.roles.read',
        'admin.v1.roles.members' => 'admin.roles.read',
        'admin.v1.roles.create' => 'admin.roles.create',
        'admin.v1.roles.update' => 'admin.roles.update',
        'admin.v1.roles.delete' => 'admin.roles.delete',
        'admin.v1.roles.permissions' => 'admin.roles.permissions.update',
        'admin.v1.roles.users.grant' => 'admin.roles.members.manage',
        'admin.v1.roles.users.revoke' => 'admin.roles.members.manage',
        'admin.v1.settings.list' => 'admin.settings.read',
        'admin.v1.settings.update' => 'admin.settings.write',
    ];

    public function __construct(private readonly RoleService $roles) {}

    public function process(Request $request, callable $handler): Response
    {
        $identity = $request->context[AuthenticateSession::CONTEXT_KEY] ?? null;
        if (!$identity instanceof AuthenticatedSession) {
            throw new BusinessException('unauthenticated', '请先登录。', 401);
        }

        $userId = $identity->user->id;
        $routeName = $request->route?->getName();
        if ($routeName === 'admin.v1.access') {
            if (!$this->roles->hasAdminAccess($userId)) {
                throw new BusinessException('admin_forbidden', '当前账号没有后台管理权限。', 403);
            }
            return $handler($request);
        }

        $permission = is_string($routeName) ? (self::ROUTE_PERMISSIONS[$routeName] ?? null) : null;
        if ($permission === null || !$this->roles->hasPermission($userId, $permission)) {
            throw new BusinessException('admin_forbidden', '当前账号没有执行此操作的权限。', 403);
        }

        return $handler($request);
    }
}

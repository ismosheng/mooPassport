<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\middleware\RequirePermission;
use app\admin\service\UserManagementService;
use app\common\enum\UserStatus;
use app\common\exception\BusinessException;
use app\common\model\User;
use app\common\support\ApiResponse;
use app\common\support\RequestId;
use app\passport\dto\AuthenticatedSession;
use app\passport\middleware\AuthenticateSession;
use support\annotation\Middleware;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Put;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供超级管理员用户检索和账号状态管理接口，不暴露任何认证凭据。 */
#[DisableDefaultRoute]
#[RouteGroup('/admin/v1/users')]
#[Middleware(AuthenticateSession::class, RequirePermission::class)]
final class UserController
{
    public function __construct(private readonly UserManagementService $management) {}

    #[Get('', 'admin.v1.users.list')]
    public function list(Request $request): Response
    {
        $status = (string) $request->get('status', '');
        $status = in_array($status, array_column(UserStatus::cases(), 'value'), true) ? $status : null;
        $verified = (string) $request->get('email_verified', '');
        $emailVerified = $verified === '' ? null : $verified === '1';
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(100, max(10, (int) $request->get('per_page', 20)));
        $result = $this->management->search((string) $request->get('keyword', ''), $status, $emailVerified, $page, $perPage);
        return ApiResponse::success($request, [
            'items' => array_map(fn (User $user): array => $this->serialize($user, $result['roles'][$user->id] ?? []), $result['items']),
            'total' => $result['total'], 'page' => $page, 'per_page' => $perPage,
        ]);
    }

    #[Put('/{userId}/status', 'admin.v1.users.status')]
    public function status(Request $request, string $userId): Response
    {
        $status = UserStatus::tryFrom((string) $request->post('status'));
        if ($status === null) throw new BusinessException('invalid_user_status', '用户状态无效。', 422);
        $user = $this->management->changeStatus($this->identity($request)->user->id, $userId, $status, RequestId::get($request));
        return ApiResponse::success($request, $this->serialize($user, []));
    }

    #[Get('/{userId}', 'admin.v1.users.detail')]
    public function detail(Request $request, string $userId): Response
    {
        $result = $this->management->detail($userId);
        return ApiResponse::success($request, [
            ...$this->serialize($result['user'], $result['statistics']['roles']),
            'statistics' => $result['statistics'],
        ]);
    }

    #[Post('/{userId}/force-logout', 'admin.v1.users.force_logout')]
    public function forceLogout(Request $request, string $userId): Response
    {
        $count = $this->management->forceLogout($this->identity($request)->user->id, $userId, RequestId::get($request));
        return ApiResponse::success($request, ['revoked_sessions' => $count]);
    }

    /**
     * @param list<string> $roles
     * @return array<string, mixed>
     */
    private function serialize(User $user, array $roles): array
    {
        return [
            'id' => $user->public_id, 'username' => $user->username, 'email' => $user->email,
            'display_name' => $user->display_name, 'avatar_url' => $user->avatar_url,
            'status' => $user->status instanceof UserStatus ? $user->status->value : $user->status,
            'email_verified' => $user->email_verified_at !== null, 'roles' => $roles,
            'last_login_at' => $user->last_login_at?->format(DATE_ATOM), 'created_at' => $user->created_at?->format(DATE_ATOM),
        ];
    }

    private function identity(Request $request): AuthenticatedSession
    {
        $identity = $request->context[AuthenticateSession::CONTEXT_KEY] ?? null;
        if (!$identity instanceof AuthenticatedSession) throw new BusinessException('unauthenticated', '请先登录。', 401);
        return $identity;
    }
}

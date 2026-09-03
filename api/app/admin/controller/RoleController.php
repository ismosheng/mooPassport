<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\middleware\RequirePermission;
use app\admin\service\RoleManagementService;
use app\admin\validator\CreateRoleValidator;
use app\admin\validator\UpdateRolePermissionsValidator;
use app\admin\validator\UpdateRoleValidator;
use app\common\exception\BusinessException;
use app\common\model\Role;
use app\common\support\ApiResponse;
use app\common\support\RequestId;
use app\passport\dto\AuthenticatedSession;
use app\passport\middleware\AuthenticateSession;
use support\annotation\Middleware;
use support\annotation\route\Delete;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供角色、权限和成员管理接口；所有写操作由 Service 保证安全不变量。 */
#[DisableDefaultRoute]
#[RouteGroup('/admin/v1/roles')]
#[Middleware(AuthenticateSession::class, RequirePermission::class)]
final class RoleController
{
    public function __construct(private readonly RoleManagementService $management) {}

    #[Get('', 'admin.v1.roles.list')]
    public function list(Request $request): Response
    {
        $status = trim((string) $request->get('status', ''));
        $status = in_array($status, ['active', 'disabled'], true) ? $status : null;
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(100, max(10, (int) $request->get('per_page', 20)));
        return ApiResponse::success($request, $this->management->search(
            (string) $request->get('keyword', ''), $status, $page, $perPage,
        ));
    }

    #[Post('', 'admin.v1.roles.create')]
    public function create(Request $request): Response
    {
        $data = CreateRoleValidator::make((array) $request->post())->validate();
        $role = $this->management->create(
            $this->identity($request)->user->id, (string) $data['code'], (string) $data['name'],
            isset($data['description']) ? (string) $data['description'] : null, $this->requestId($request),
        );
        return ApiResponse::success($request, $this->serialize($role), 201);
    }

    #[Put('/{roleCode}/permissions', 'admin.v1.roles.permissions')]
    public function permissions(Request $request, string $roleCode): Response
    {
        $data = UpdateRolePermissionsValidator::make((array) $request->post())->validate();
        $this->management->replacePermissions($this->identity($request)->user->id, $roleCode, array_values(array_map('strval', $data['permissions'])), $this->requestId($request));
        return ApiResponse::success($request, ['updated' => true]);
    }

    #[Put('/{roleCode}', 'admin.v1.roles.update')]
    public function update(Request $request, string $roleCode): Response
    {
        $data = UpdateRoleValidator::make((array) $request->post())->validate();
        $role = $this->management->update(
            $this->identity($request)->user->id, $roleCode, (string) $data['name'],
            isset($data['description']) ? (string) $data['description'] : null,
            (string) $data['status'], (int) $data['version'], $this->requestId($request),
        );
        return ApiResponse::success($request, $this->serialize($role));
    }

    #[Delete('/{roleCode}', 'admin.v1.roles.delete')]
    public function delete(Request $request, string $roleCode): Response
    {
        $this->management->delete($this->identity($request)->user->id, $roleCode, $this->requestId($request));
        return ApiResponse::success($request, ['deleted' => true]);
    }

    #[Get('/{roleCode}/members', 'admin.v1.roles.members')]
    public function members(Request $request, string $roleCode): Response
    {
        return ApiResponse::success($request, ['items' => $this->management->members($roleCode)]);
    }

    #[Post('/{roleCode}/users/{userId}', 'admin.v1.roles.users.grant')]
    public function grantUser(Request $request, string $roleCode, string $userId): Response
    {
        $this->management->grantUser($this->identity($request)->user->id, $roleCode, $userId, $this->requestId($request));
        return ApiResponse::success($request, ['granted' => true]);
    }

    #[Delete('/{roleCode}/users/{userId}', 'admin.v1.roles.users.revoke')]
    public function revokeUser(Request $request, string $roleCode, string $userId): Response
    {
        $this->management->revokeUser($this->identity($request)->user->id, $roleCode, $userId, $this->requestId($request));
        return ApiResponse::success($request, ['revoked' => true]);
    }

    /** @return array<string, mixed> */
    private function serialize(Role $role): array
    {
        return [
            'code' => $role->code, 'name' => $role->name, 'description' => $role->description,
            'is_system' => $role->is_system, 'status' => $role->status, 'version' => $role->version,
            'created_at' => $role->created_at->format(DATE_ATOM), 'updated_at' => $role->updated_at->format(DATE_ATOM),
        ];
    }

    private function identity(Request $request): AuthenticatedSession
    {
        $identity = $request->context[AuthenticateSession::CONTEXT_KEY] ?? null;
        if (!$identity instanceof AuthenticatedSession) throw new BusinessException('unauthenticated', '请先登录。', 401);
        return $identity;
    }

    private function requestId(Request $request): string
    {
        return RequestId::get($request);
    }
}

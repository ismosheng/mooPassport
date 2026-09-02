<?php

declare(strict_types=1);

namespace app\admin\service;

use app\admin\repository\contract\RoleManagementRepositoryInterface;
use app\common\exception\BusinessException;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\model\Role;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\service\RoleService;
use DateTimeImmutable;

/**
 * 管理后台角色和权限事务。
 *
 * super_admin 是系统恢复入口，因此其权限不可裁剪，最后一名成员也不可撤销。
 */
final class RoleManagementService
{
    public function __construct(
        private readonly RoleManagementRepositoryInterface $roles,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly TransactionManagerInterface $transactions,
    ) {}

    /** @return array{items:list<array<string,mixed>>,permissions:list<array<string,mixed>>,total:int,page:int,per_page:int} */
    public function search(string $keyword, ?string $status, int $page, int $perPage): array
    {
        $permissions = $this->roles->permissions();
        $allPermissionCodes = array_values(array_map('strval', array_column($permissions, 'code')));
        $result = $this->roles->searchRoles(trim($keyword), $status, $page, $perPage);
        $items = array_map(static function (array $role) use ($allPermissionCodes): array {
            // 根角色按定义永久拥有全部权限，即使初始化关系表曾被人工误删也不能降权。
            if ($role['code'] === RoleService::SUPER_ADMIN) {
                $role['permission_codes'] = $allPermissionCodes;
                $role['permission_count'] = count($allPermissionCodes);
            }
            return $role;
        }, $result['items']);

        return ['items' => $items, 'permissions' => $permissions, 'total' => $result['total'], 'page' => $page, 'per_page' => $perPage];
    }

    public function create(int $actorUserId, string $code, string $name, ?string $description, ?string $requestId): Role
    {
        if ($this->roles->findRoleByCode($code) !== null) {
            throw new BusinessException('role_code_exists', '角色标识已存在。', 409);
        }

        return $this->transactions->run(function () use ($actorUserId, $code, $name, $description, $requestId): Role {
            $role = $this->roles->createRole($code, $name, $description);
            $this->auditLogs->record([
                'event_type' => 'admin.role.created', 'user_id' => $actorUserId,
                'request_id' => $requestId, 'success' => true,
                'details' => ['role_code' => $code], 'created_at' => new DateTimeImmutable(),
            ]);
            return $role;
        });
    }

    public function update(int $actorUserId, string $roleCode, string $name, ?string $description, string $status, int $version, ?string $requestId): Role
    {
        return $this->transactions->run(function () use ($actorUserId, $roleCode, $name, $description, $status, $version, $requestId): Role {
            $role = $this->requireRole($roleCode, true);
            if ($role->code === RoleService::SUPER_ADMIN && $status !== 'active') {
                throw new BusinessException('super_admin_status_immutable', '超级管理员角色不能停用。', 422);
            }
            if (!$this->roles->updateRole($role->id, $version, trim($name), $description === null ? null : trim($description), $status)) {
                throw new BusinessException('role_version_conflict', '角色已被其他管理员修改，请刷新后重试。', 409);
            }
            $this->recordRoleAudit('admin.role.updated', $actorUserId, $roleCode, $requestId, ['status' => $status]);
            return $this->requireRole($roleCode, false);
        });
    }

    public function delete(int $actorUserId, string $roleCode, ?string $requestId): void
    {
        $this->transactions->run(function () use ($actorUserId, $roleCode, $requestId): void {
            $role = $this->requireRole($roleCode, true);
            if ($role->is_system) {
                throw new BusinessException('system_role_immutable', '系统内置角色不能删除。', 422);
            }
            if ($this->roles->countUsersWithRole($role->id) > 0) {
                throw new BusinessException('role_in_use', '该角色仍有成员，移除全部成员后才能删除。', 422);
            }
            $this->roles->deleteRole($role->id);
            $this->recordRoleAudit('admin.role.deleted', $actorUserId, $roleCode, $requestId);
        });
    }

    /** @return list<array<string, mixed>> */
    public function members(string $roleCode): array
    {
        return $this->roles->roleMembers($this->requireRole($roleCode, false)->id);
    }

    /** @param list<string> $permissionCodes */
    public function replacePermissions(int $actorUserId, string $roleCode, array $permissionCodes, ?string $requestId): void
    {
        $permissionCodes = array_values(array_unique($permissionCodes));
        $known = array_column($this->roles->permissions(), 'code');
        if (array_diff($permissionCodes, $known) !== []) {
            throw new BusinessException('permission_not_found', '包含不存在的权限点。', 422);
        }

        $this->transactions->run(function () use ($actorUserId, $roleCode, $permissionCodes, $requestId): void {
            $role = $this->requireRole($roleCode, true);
            if ($role->code === RoleService::SUPER_ADMIN) {
                throw new BusinessException('super_admin_permissions_immutable', '超级管理员永久拥有全部权限，不能裁剪。', 422);
            }
            $this->roles->replacePermissions($role->id, $permissionCodes, $actorUserId);
            $this->auditLogs->record([
                'event_type' => 'admin.role.permissions_replaced', 'user_id' => $actorUserId,
                'request_id' => $requestId, 'success' => true,
                'details' => ['role_code' => $roleCode, 'permissions' => $permissionCodes],
                'created_at' => new DateTimeImmutable(),
            ]);
        });
    }

    public function grantUser(int $actorUserId, string $roleCode, string $userPublicId, ?string $requestId): void
    {
        $this->transactions->run(function () use ($actorUserId, $roleCode, $userPublicId, $requestId): void {
            $role = $this->requireRole($roleCode, true);
            $user = $this->roles->findUserByPublicId($userPublicId, true);
            if ($user === null) throw new BusinessException('user_not_found', '用户不存在。', 404);
            $this->roles->grantRole($user->id, $role->id, $actorUserId);
            $this->recordMembershipAudit('admin.role.user_granted', $actorUserId, $user->id, $roleCode, $requestId);
        });
    }

    public function revokeUser(int $actorUserId, string $roleCode, string $userPublicId, ?string $requestId): void
    {
        $this->transactions->run(function () use ($actorUserId, $roleCode, $userPublicId, $requestId): void {
            $role = $this->requireRole($roleCode, true);
            $user = $this->roles->findUserByPublicId($userPublicId, true);
            if ($user === null) throw new BusinessException('user_not_found', '用户不存在。', 404);
            if (!$this->roles->userHasRole($user->id, $role->id)) return;

            if ($role->code === RoleService::SUPER_ADMIN && $user->id === $actorUserId) {
                throw new BusinessException('cannot_revoke_self_super_admin', '不能撤销自己的超级管理员角色。', 422);
            }

            // 锁住同一角色的成员关系后再计数，避免两个并发请求同时撤销最后的管理员。
            if ($role->code === RoleService::SUPER_ADMIN && $this->roles->countUsersWithRole($role->id) <= 1) {
                throw new BusinessException('last_super_admin', '不能撤销最后一个超级管理员。', 422);
            }
            $this->roles->revokeRole($user->id, $role->id);
            $this->recordMembershipAudit('admin.role.user_revoked', $actorUserId, $user->id, $roleCode, $requestId);
        });
    }

    private function requireRole(string $code, bool $forUpdate): Role
    {
        $role = $this->roles->findRoleByCode($code, $forUpdate);
        if ($role === null) throw new BusinessException('role_not_found', '角色不存在。', 404);
        return $role;
    }

    private function recordMembershipAudit(string $event, int $actorUserId, int $targetUserId, string $roleCode, ?string $requestId): void
    {
        $this->auditLogs->record([
            'event_type' => $event, 'user_id' => $targetUserId, 'request_id' => $requestId,
            'success' => true, 'details' => ['actor_user_id' => $actorUserId, 'role_code' => $roleCode],
            'created_at' => new DateTimeImmutable(),
        ]);
    }

    /** @param array<string, mixed> $extra */
    private function recordRoleAudit(string $event, int $actorUserId, string $roleCode, ?string $requestId, array $extra = []): void
    {
        $this->auditLogs->record([
            'event_type' => $event, 'user_id' => $actorUserId, 'request_id' => $requestId,
            'success' => true, 'details' => ['role_code' => $roleCode, ...$extra],
            'created_at' => new DateTimeImmutable(),
        ]);
    }
}

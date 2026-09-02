<?php

declare(strict_types=1);

namespace app\admin\repository\eloquent;

use app\admin\repository\contract\RoleManagementRepositoryInterface;
use app\common\model\Role;
use app\common\model\User;
use support\Db;

/** 使用 MySQL 管理角色关系；业务不变量由上层 Service 在事务中保证。 */
final class RoleManagementRepository implements RoleManagementRepositoryInterface
{
    public function searchRoles(string $keyword, ?string $status, int $page, int $perPage): array
    {
        $query = Db::table('moo_roles');
        if ($keyword !== '') {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword);
            $query->where(static function ($query) use ($escaped): void {
                $query->where('moo_roles.name', 'like', "%{$escaped}%")
                    ->orWhere('moo_roles.code', 'like', "%{$escaped}%")
                    ->orWhere('moo_roles.description', 'like', "%{$escaped}%");
            });
        }
        if ($status !== null) $query->where('moo_roles.status', $status);
        $total = (clone $query)->count();
        $rows = $query->leftJoin('moo_user_roles', 'moo_user_roles.role_id', '=', 'moo_roles.id')
            ->leftJoin('moo_role_permissions', 'moo_role_permissions.role_id', '=', 'moo_roles.id')
            ->groupBy('moo_roles.id', 'moo_roles.code', 'moo_roles.name', 'moo_roles.description', 'moo_roles.is_system', 'moo_roles.status', 'moo_roles.version', 'moo_roles.created_at', 'moo_roles.updated_at')
            ->orderBy('moo_roles.id')->forPage($page, $perPage)->get([
                'moo_roles.id',
                'moo_roles.code', 'moo_roles.name', 'moo_roles.description', 'moo_roles.is_system',
                'moo_roles.status', 'moo_roles.version', 'moo_roles.created_at', 'moo_roles.updated_at',
                Db::raw('COUNT(DISTINCT moo_user_roles.user_id) AS user_count'),
                Db::raw('COUNT(DISTINCT moo_role_permissions.permission_id) AS permission_count'),
            ]);
        $roleIds = $rows->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $permissionCodes = [];
        if ($roleIds !== []) {
            foreach (Db::table('moo_role_permissions')
                ->join('moo_permissions', 'moo_permissions.id', '=', 'moo_role_permissions.permission_id')
                ->whereIn('moo_role_permissions.role_id', $roleIds)
                ->orderBy('moo_permissions.code')
                ->get(['moo_role_permissions.role_id', 'moo_permissions.code']) as $permission) {
                $permissionCodes[(int) $permission->role_id][] = (string) $permission->code;
            }
        }

        $items = $rows->map(static function (object $row) use ($permissionCodes): array {
            $item = (array) $row;
            $item['permission_codes'] = $permissionCodes[(int) $row->id] ?? [];
            unset($item['id']);
            return $item;
        })->all();

        return ['items' => $items, 'total' => $total];
    }

    public function permissions(): array
    {
        return Db::table('moo_permissions')->orderBy('module')->orderBy('code')->get()
            ->map(static fn (object $row): array => (array) $row)->all();
    }

    public function findRoleByCode(string $code, bool $forUpdate = false): ?Role
    {
        $query = Role::query()->where('code', $code);
        if ($forUpdate) $query->lockForUpdate();
        $role = $query->first();
        return $role instanceof Role ? $role : null;
    }

    public function createRole(string $code, string $name, ?string $description): Role
    {
        return Role::query()->create([
            'code' => $code, 'name' => $name, 'description' => $description,
            'is_system' => false, 'status' => 'active', 'version' => 1,
        ]);
    }

    public function updateRole(int $roleId, int $expectedVersion, string $name, ?string $description, string $status): bool
    {
        return Role::query()->whereKey($roleId)->where('version', $expectedVersion)->update([
            'name' => $name,
            'description' => $description,
            'status' => $status,
            'version' => $expectedVersion + 1,
        ]) === 1;
    }

    public function deleteRole(int $roleId): void
    {
        Role::query()->whereKey($roleId)->delete();
    }

    public function roleMembers(int $roleId): array
    {
        return Db::table('moo_user_roles')
            ->join('moo_users', 'moo_users.id', '=', 'moo_user_roles.user_id')
            ->where('moo_user_roles.role_id', $roleId)
            ->orderBy('moo_users.created_at')
            ->get(['moo_users.public_id as id', 'moo_users.username', 'moo_users.email', 'moo_users.display_name', 'moo_users.avatar_url', 'moo_users.status', 'moo_user_roles.created_at as granted_at'])
            ->map(static fn (object $row): array => (array) $row)->all();
    }

    public function replacePermissions(int $roleId, array $permissionCodes, int $actorUserId): void
    {
        $ids = Db::table('moo_permissions')->whereIn('code', $permissionCodes)->pluck('id')->all();
        Db::table('moo_role_permissions')->where('role_id', $roleId)->delete();
        if ($ids === []) return;
        $now = new \DateTimeImmutable();
        Db::table('moo_role_permissions')->insert(array_map(static fn (mixed $id): array => [
            'role_id' => $roleId, 'permission_id' => (int) $id,
            'granted_by_user_id' => $actorUserId, 'created_at' => $now,
        ], $ids));
    }

    public function findUserByPublicId(string $publicId, bool $forUpdate = false): ?User
    {
        $query = User::query()->where('public_id', $publicId);
        if ($forUpdate) $query->lockForUpdate();
        $user = $query->first();
        return $user instanceof User ? $user : null;
    }

    public function userHasRole(int $userId, int $roleId): bool
    {
        return Db::table('moo_user_roles')->where('user_id', $userId)->where('role_id', $roleId)->exists();
    }

    public function grantRole(int $userId, int $roleId, int $actorUserId): void
    {
        Db::table('moo_user_roles')->insertOrIgnore([
            'user_id' => $userId, 'role_id' => $roleId,
            'granted_by_user_id' => $actorUserId, 'created_at' => new \DateTimeImmutable(),
        ]);
    }

    public function revokeRole(int $userId, int $roleId): void
    {
        Db::table('moo_user_roles')->where('user_id', $userId)->where('role_id', $roleId)->delete();
    }

    public function countUsersWithRole(int $roleId): int
    {
        return Db::table('moo_user_roles')->where('role_id', $roleId)->lockForUpdate()->get(['user_id'])->count();
    }
}

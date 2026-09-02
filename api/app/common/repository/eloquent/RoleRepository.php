<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\repository\contract\RoleRepositoryInterface;
use support\Db;

/** 使用角色关系表读取权限，不承担角色授予和撤销业务。 */
final class RoleRepository implements RoleRepositoryInterface
{
    public function codesForUser(int $userId): array
    {
        /** @var list<string> $codes */
        $codes = Db::table('moo_user_roles')
            ->join('moo_roles', 'moo_roles.id', '=', 'moo_user_roles.role_id')
            ->where('moo_user_roles.user_id', $userId)
            ->where('moo_roles.status', 'active')
            ->orderBy('moo_roles.code')
            ->pluck('moo_roles.code')
            ->map(static fn (mixed $code): string => (string) $code)
            ->all();

        return $codes;
    }

    public function permissionCodesForUser(int $userId): array
    {
        /** @var list<string> $codes */
        $codes = Db::table('moo_user_roles')
            ->join('moo_roles', 'moo_roles.id', '=', 'moo_user_roles.role_id')
            ->join('moo_role_permissions', 'moo_role_permissions.role_id', '=', 'moo_roles.id')
            ->join('moo_permissions', 'moo_permissions.id', '=', 'moo_role_permissions.permission_id')
            ->where('moo_user_roles.user_id', $userId)
            ->where('moo_roles.status', 'active')
            ->distinct()
            ->orderBy('moo_permissions.code')
            ->pluck('moo_permissions.code')
            ->map(static fn (mixed $code): string => (string) $code)
            ->all();

        return $codes;
    }

    public function allPermissionCodes(): array
    {
        /** @var list<string> $codes */
        $codes = Db::table('moo_permissions')
            ->orderBy('code')
            ->pluck('code')
            ->map(static fn (mixed $code): string => (string) $code)
            ->all();

        return $codes;
    }

    public function userHasRole(int $userId, string $roleCode): bool
    {
        return Db::table('moo_user_roles')
            ->join('moo_roles', 'moo_roles.id', '=', 'moo_user_roles.role_id')
            ->where('moo_user_roles.user_id', $userId)
            ->where('moo_roles.code', $roleCode)
            ->where('moo_roles.status', 'active')
            ->exists();
    }

    public function userHasPermission(int $userId, string $permissionCode): bool
    {
        return Db::table('moo_user_roles')
            ->join('moo_roles', 'moo_roles.id', '=', 'moo_user_roles.role_id')
            ->join('moo_role_permissions', 'moo_role_permissions.role_id', '=', 'moo_roles.id')
            ->join('moo_permissions', 'moo_permissions.id', '=', 'moo_role_permissions.permission_id')
            ->where('moo_user_roles.user_id', $userId)
            ->where('moo_roles.status', 'active')
            ->where('moo_permissions.code', $permissionCode)
            ->exists();
    }

    public function userHasAnyPermission(int $userId): bool
    {
        return Db::table('moo_user_roles')
            ->join('moo_roles', 'moo_roles.id', '=', 'moo_user_roles.role_id')
            ->join('moo_role_permissions', 'moo_role_permissions.role_id', '=', 'moo_roles.id')
            ->where('moo_user_roles.user_id', $userId)
            ->where('moo_roles.status', 'active')
            ->exists();
    }
}

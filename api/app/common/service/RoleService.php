<?php

declare(strict_types=1);

namespace app\common\service;

use app\common\repository\contract\RoleRepositoryInterface;

/** 向多个应用提供统一角色判定，避免各端自行解释角色标识。 */
final class RoleService
{
    public const SUPER_ADMIN = 'super_admin';

    public function __construct(private readonly RoleRepositoryInterface $roles)
    {
    }

    /** @return list<string> */
    public function codesForUser(int $userId): array
    {
        return $this->roles->codesForUser($userId);
    }

    public function isSuperAdmin(int $userId): bool
    {
        return $this->roles->userHasRole($userId, self::SUPER_ADMIN);
    }

    /**
     * 返回前端可用于界面裁剪的有效权限码；后端中间件仍是最终安全边界。
     *
     * @return list<string>
     */
    public function effectivePermissionCodes(int $userId): array
    {
        // 超级管理员必须得到明确权限列表，避免前端各处自行解释通配符而产生语义分歧。
        return $this->isSuperAdmin($userId)
            ? $this->roles->allPermissionCodes()
            : $this->roles->permissionCodesForUser($userId);
    }

    public function hasPermission(int $userId, string $permissionCode): bool
    {
        // 超级管理员是灾难恢复入口，始终按拥有全部后台权限解释。
        return $this->isSuperAdmin($userId) || $this->roles->userHasPermission($userId, $permissionCode);
    }

    public function hasAdminAccess(int $userId): bool
    {
        return $this->isSuperAdmin($userId) || $this->roles->userHasAnyPermission($userId);
    }
}

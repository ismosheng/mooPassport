<?php

declare(strict_types=1);

namespace app\common\repository\contract;

/** 定义跨应用的用户角色读取边界，不在此接口中决定授权流程。 */
interface RoleRepositoryInterface
{
    /** @return list<string> */
    public function codesForUser(int $userId): array;

    /** @return list<string> */
    public function permissionCodesForUser(int $userId): array;

    /** @return list<string> */
    public function allPermissionCodes(): array;

    public function userHasRole(int $userId, string $roleCode): bool;

    public function userHasPermission(int $userId, string $permissionCode): bool;

    public function userHasAnyPermission(int $userId): bool;
}

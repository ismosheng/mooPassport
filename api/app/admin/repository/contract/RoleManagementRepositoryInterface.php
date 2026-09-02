<?php

declare(strict_types=1);

namespace app\admin\repository\contract;

use app\common\model\Role;
use app\common\model\User;

/** 定义后台角色、权限和成员关系的持久化边界，不在 Repository 中决定授权规则。 */
interface RoleManagementRepositoryInterface
{
    /** @return array{items:list<array<string, mixed>>,total:int} */
    public function searchRoles(string $keyword, ?string $status, int $page, int $perPage): array;

    /** @return list<array<string, mixed>> */
    public function permissions(): array;

    public function findRoleByCode(string $code, bool $forUpdate = false): ?Role;

    public function createRole(string $code, string $name, ?string $description): Role;

    public function updateRole(int $roleId, int $expectedVersion, string $name, ?string $description, string $status): bool;

    public function deleteRole(int $roleId): void;

    /** @return list<array<string, mixed>> */
    public function roleMembers(int $roleId): array;

    /** @param list<string> $permissionCodes */
    public function replacePermissions(int $roleId, array $permissionCodes, int $actorUserId): void;

    public function findUserByPublicId(string $publicId, bool $forUpdate = false): ?User;

    public function userHasRole(int $userId, int $roleId): bool;

    public function grantRole(int $userId, int $roleId, int $actorUserId): void;

    public function revokeRole(int $userId, int $roleId): void;

    public function countUsersWithRole(int $roleId): int;
}

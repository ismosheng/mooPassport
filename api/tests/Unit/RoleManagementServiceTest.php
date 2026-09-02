<?php

declare(strict_types=1);

namespace tests\Unit;

use app\admin\repository\contract\RoleManagementRepositoryInterface;
use app\admin\service\RoleManagementService;
use app\common\exception\BusinessException;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\model\Role;
use app\common\model\User;
use app\common\repository\contract\AuditLogRepositoryInterface;
use Closure;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/** 覆盖角色管理中会导致后台失去恢复入口的安全分支。 */
final class RoleManagementServiceTest extends TestCase
{
    public function testCannotRevokeOwnSuperAdministratorRole(): void
    {
        $roles = $this->createStub(RoleManagementRepositoryInterface::class);
        $roles->method('findRoleByCode')->willReturn($this->role('super_admin', true));
        $roles->method('findUserByPublicId')->willReturn($this->user(7));
        $roles->method('userHasRole')->willReturn(true);

        $this->expectBusinessError('cannot_revoke_self_super_admin', fn () => $this->service($roles)->revokeUser(7, 'super_admin', '01USER', null));
    }

    public function testCannotRevokeLastSuperAdministrator(): void
    {
        $roles = $this->createStub(RoleManagementRepositoryInterface::class);
        $roles->method('findRoleByCode')->willReturn($this->role('super_admin', true));
        $roles->method('findUserByPublicId')->willReturn($this->user(8));
        $roles->method('userHasRole')->willReturn(true);
        $roles->method('countUsersWithRole')->willReturn(1);

        $this->expectBusinessError('last_super_admin', fn () => $this->service($roles)->revokeUser(7, 'super_admin', '01USER', null));
    }

    public function testSystemRoleCannotBeDeleted(): void
    {
        $roles = $this->createStub(RoleManagementRepositoryInterface::class);
        $roles->method('findRoleByCode')->willReturn($this->role('super_admin', true));

        $this->expectBusinessError('system_role_immutable', fn () => $this->service($roles)->delete(7, 'super_admin', null));
    }

    public function testConcurrentRoleEditReturnsVersionConflict(): void
    {
        $roles = $this->createStub(RoleManagementRepositoryInterface::class);
        $roles->method('findRoleByCode')->willReturn($this->role('operator', false));
        $roles->method('updateRole')->willReturn(false);

        $this->expectBusinessError('role_version_conflict', fn () => $this->service($roles)->update(7, 'operator', '运营人员', null, 'active', 1, null));
    }

    public function testSuperAdministratorPermissionsCannotBeReduced(): void
    {
        $roles = $this->createStub(RoleManagementRepositoryInterface::class);
        $roles->method('permissions')->willReturn([['code' => 'admin.users.read']]);
        $roles->method('findRoleByCode')->willReturn($this->role('super_admin', true));

        $this->expectBusinessError('super_admin_permissions_immutable', fn () => $this->service($roles)->replacePermissions(7, 'super_admin', [], null));
    }

    /** @param RoleManagementRepositoryInterface&Stub $roles */
    private function service(RoleManagementRepositoryInterface $roles): RoleManagementService
    {
        $transactions = $this->createStub(TransactionManagerInterface::class);
        $transactions->method('run')->willReturnCallback(static fn (Closure $callback): mixed => $callback());

        return new RoleManagementService(
            $roles,
            $this->createStub(AuditLogRepositoryInterface::class),
            $transactions,
        );
    }

    private function role(string $code, bool $system): Role
    {
        $role = new Role();
        $role->setRawAttributes([
            'id' => 2, 'code' => $code, 'name' => $code, 'description' => null,
            'is_system' => $system, 'status' => 'active', 'version' => 1,
        ]);
        return $role;
    }

    private function user(int $id): User
    {
        $user = new User();
        $user->setRawAttributes(['id' => $id, 'public_id' => '01USER', 'status' => 'active']);
        return $user;
    }

    private function expectBusinessError(string $errorCode, callable $operation): void
    {
        try {
            $operation();
            self::fail('预期的角色安全异常没有抛出。');
        } catch (BusinessException $exception) {
            self::assertSame($errorCode, $exception->errorCode);
        }
    }
}

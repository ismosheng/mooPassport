<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\repository\contract\RoleRepositoryInterface;
use app\common\service\RoleService;
use PHPUnit\Framework\TestCase;

/** 验证后台界面获得的有效权限与超级管理员安全语义保持一致。 */
final class RoleServiceTest extends TestCase
{
    public function testSuperAdministratorReceivesEveryDefinedPermission(): void
    {
        $repository = $this->createStub(RoleRepositoryInterface::class);
        $repository->method('userHasRole')->willReturn(true);
        $repository->method('allPermissionCodes')->willReturn(['admin.audit.read', 'admin.users.status.update']);

        self::assertSame(
            ['admin.audit.read', 'admin.users.status.update'],
            (new RoleService($repository))->effectivePermissionCodes(7),
        );
    }

    public function testRegularAdministratorReceivesAssignedPermissionsOnly(): void
    {
        $repository = $this->createStub(RoleRepositoryInterface::class);
        $repository->method('userHasRole')->willReturn(false);
        $repository->method('permissionCodesForUser')->willReturn(['admin.users.read']);

        self::assertSame(
            ['admin.users.read'],
            (new RoleService($repository))->effectivePermissionCodes(8),
        );
    }

    public function testSuperAdministratorAlwaysPassesPermissionCheck(): void
    {
        $repository = $this->createMock(RoleRepositoryInterface::class);
        $repository->method('userHasRole')->willReturn(true);
        $repository->expects(self::never())->method('userHasPermission');

        self::assertTrue((new RoleService($repository))->hasPermission(7, 'admin.applications.secret.rotate'));
    }

    public function testRegularAdministratorUsesAssignedPermission(): void
    {
        $repository = $this->createStub(RoleRepositoryInterface::class);
        $repository->method('userHasRole')->willReturn(false);
        $repository->method('userHasPermission')->willReturnCallback(
            static fn (int $userId, string $permission): bool => $userId === 8 && $permission === 'admin.applications.read',
        );
        $service = new RoleService($repository);

        self::assertTrue($service->hasPermission(8, 'admin.applications.read'));
        self::assertFalse($service->hasPermission(8, 'admin.applications.secret.rotate'));
    }

    public function testAccountWithoutRoleOrPermissionHasNoAdminAccess(): void
    {
        $repository = $this->createStub(RoleRepositoryInterface::class);
        $repository->method('userHasRole')->willReturn(false);
        $repository->method('userHasAnyPermission')->willReturn(false);

        self::assertFalse((new RoleService($repository))->hasAdminAccess(9));
    }
}

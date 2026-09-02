<?php

declare(strict_types=1);

namespace tests\Unit;

use app\admin\repository\contract\UserManagementRepositoryInterface;
use app\admin\service\UserManagementService;
use app\common\enum\UserStatus;
use app\common\exception\BusinessException;
use app\common\model\User;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\repository\contract\UserSessionRepositoryInterface;
use PHPUnit\Framework\TestCase;

final class UserManagementServiceTest extends TestCase
{
    public function testAdministratorCannotDisableSelf(): void
    {
        $user = new User();
        $user->setRawAttributes(['id' => 7, 'public_id' => '01TESTUSER0000000000000000', 'status' => 'active']);
        $users = $this->createStub(UserManagementRepositoryInterface::class);
        $users->method('findByPublicId')->willReturn($user);
        $service = new UserManagementService(
            $users,
            $this->createStub(UserSessionRepositoryInterface::class),
            $this->createStub(AccessTokenRepositoryInterface::class),
            $this->createStub(RefreshTokenRepositoryInterface::class),
            $this->createStub(AuditLogRepositoryInterface::class),
        );
        try {
            $service->changeStatus(7, '01TESTUSER0000000000000000', UserStatus::Disabled, null);
            self::fail('管理员禁用自己时没有被拒绝。');
        } catch (BusinessException $exception) {
            self::assertSame('cannot_disable_self', $exception->errorCode);
        }
    }

    public function testAdministratorCannotForceLogoutSelf(): void
    {
        $user = new User();
        $user->setRawAttributes(['id' => 7, 'public_id' => '01TESTUSER0000000000000000', 'status' => 'active']);
        $users = $this->createStub(UserManagementRepositoryInterface::class);
        $users->method('findByPublicId')->willReturn($user);
        $service = new UserManagementService(
            $users,
            $this->createStub(UserSessionRepositoryInterface::class),
            $this->createStub(AccessTokenRepositoryInterface::class),
            $this->createStub(RefreshTokenRepositoryInterface::class),
            $this->createStub(AuditLogRepositoryInterface::class),
        );
        try {
            $service->forceLogout(7, '01TESTUSER0000000000000000', null);
            self::fail('管理员强制下线自己时没有被拒绝。');
        } catch (BusinessException $exception) {
            self::assertSame('cannot_logout_self', $exception->errorCode);
        }
    }
}

<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\model\OAuthAuditLog;
use app\common\model\User;
use app\common\exception\BusinessException;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\support\IpAddress;
use app\passport\service\ProfileService;
use PHPUnit\Framework\TestCase;

/** 验证用户公开资料由服务统一持久化并记录审计。 */
final class ProfileServiceTest extends TestCase
{
    public function testUpdatesDisplayName(): void
    {
        $user = new User();
        $user->id = 12;
        $user->display_name = '旧名称';
        $user->avatar_url = null;
        $user->phone_country_code = null;
        $user->phone_number = null;

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('save')->with($user);

        $audit = $this->createMock(AuditLogRepositoryInterface::class);
        $audit->expects(self::once())->method('record')->with(self::callback(
            static fn (array $attributes): bool => $attributes['event_type'] === 'user.profile.updated'
                && $attributes['details']['display_name_updated'] === true,
        ))->willReturn(new OAuthAuditLog());

        $result = (new ProfileService($users, $audit, new IpAddress()))->updateProfile(
            $user,
            ' 新名称 ',
            '+86',
            '13800138000',
            '127.0.0.1',
        );

        self::assertSame('新名称', $result->display_name);
        self::assertSame('+86', $result->phone_country_code);
        self::assertSame('13800138000', $result->phone_number);
        self::assertNull($result->phone_verified_at);
    }

    public function testKeepsPhoneVerificationWhenPhoneDoesNotChange(): void
    {
        $verifiedAt = new \DateTimeImmutable('2026-09-03T00:00:00+00:00');
        $user = new User();
        $user->id = 12;
        $user->display_name = '现有名称';
        $user->phone_country_code = '+86';
        $user->phone_number = '13800138000';
        $user->phone_verified_at = $verifiedAt;

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('phoneExists');
        $users->expects(self::never())->method('save');

        $audit = $this->createMock(AuditLogRepositoryInterface::class);
        $audit->expects(self::never())->method('record');

        $result = (new ProfileService($users, $audit, new IpAddress()))->updateProfile(
            $user,
            '现有名称',
            '+86',
            '13800138000',
            '127.0.0.1',
        );

        self::assertSame($verifiedAt->getTimestamp(), $result->phone_verified_at?->getTimestamp());
    }

    public function testRejectsPhoneUsedByAnotherAccount(): void
    {
        $user = new User();
        $user->id = 12;
        $user->display_name = '现有名称';
        $user->phone_country_code = null;
        $user->phone_number = null;

        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())
            ->method('phoneExists')
            ->with('+86', '13800138000', 12)
            ->willReturn(true);
        $users->expects(self::never())->method('save');

        $audit = $this->createMock(AuditLogRepositoryInterface::class);
        $audit->expects(self::never())->method('record');

        $this->expectException(BusinessException::class);
        (new ProfileService($users, $audit, new IpAddress()))->updateProfile(
            $user,
            '现有名称',
            '+86',
            '13800138000',
            '127.0.0.1',
        );
    }
}

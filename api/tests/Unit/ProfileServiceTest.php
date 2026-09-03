<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\exception\BusinessException;
use app\common\infrastructure\crypto\SensitiveDataCipher;
use app\common\model\OAuthAuditLog;
use app\common\model\User;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\service\UserSensitiveDataService;
use app\common\support\IpAddress;
use app\passport\dto\UpdateProfileInput;
use app\passport\service\ProfileService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** 验证公开资料和加密实名资料由服务统一持久化并记录最小化审计。 */
final class ProfileServiceTest extends TestCase
{
    private UserSensitiveDataService $sensitiveData;

    protected function setUp(): void
    {
        $this->sensitiveData = new UserSensitiveDataService(
            new SensitiveDataCipher(base64_encode(str_repeat('k', 32))),
        );
    }

    public function testUpdatesProfileAndInitialRealnameData(): void
    {
        $user = $this->user();
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('identityDocumentHashExists')->willReturn(false);
        $users->expects(self::once())->method('save')->with($user);
        $audit = $this->createMock(AuditLogRepositoryInterface::class);
        $audit->expects(self::once())->method('record')->with(self::callback(
            static fn (array $attributes): bool => $attributes['event_type'] === 'user.profile.updated'
                && $attributes['details']['realname_updated'] === true
                && $attributes['details']['identity_document_updated'] === true,
        ))->willReturn(new OAuthAuditLog());

        $result = $this->service($users, $audit)->updateProfile(
            $user,
            new UpdateProfileInput(
                ' 新名称 ', '+86', '13800138000', 'female', '2000-01-02', '简介',
                '张三', 'id_card', '110 101 20000102 123X',
            ),
            '127.0.0.1',
        );

        self::assertSame('新名称', $result->display_name);
        self::assertSame('female', $result->gender);
        self::assertSame('2000-01-02', $result->birth_date?->format('Y-m-d'));
        self::assertSame('unverified', $result->realname_status);
        self::assertSame('张三', $this->sensitiveData->ownerView($result)['real_name']);
        self::assertSame('110***********123X', $this->sensitiveData->ownerView($result)['identity_document_number_masked']);
        self::assertNotSame('张三', $result->real_name_encrypted);
    }

    public function testBlankDocumentKeepsStoredValueAndVerification(): void
    {
        $user = $this->user();
        $user->real_name_encrypted = $this->sensitiveData->encryptRealName('张三', $user);
        $user->identity_document_type = 'id_card';
        $user->identity_document_number_encrypted = $this->sensitiveData->encryptDocumentNumber('11010120000102123X', $user);
        $user->identity_document_number_hash = $this->sensitiveData->documentFingerprint('11010120000102123X');
        $user->realname_status = 'verified';
        $user->realname_verified_at = new DateTimeImmutable('2026-09-03T00:00:00+00:00');
        $ciphertext = $user->identity_document_number_encrypted;
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::never())->method('identityDocumentHashExists');
        $users->expects(self::never())->method('save');
        $audit = $this->createMock(AuditLogRepositoryInterface::class);
        $audit->expects(self::never())->method('record');

        $this->service($users, $audit)->updateProfile(
            $user,
            new UpdateProfileInput('现有名称', null, null, null, null, null, null, null, null),
            '127.0.0.1',
        );

        self::assertSame($ciphertext, $user->identity_document_number_encrypted);
        self::assertSame('verified', $user->realname_status);
    }

    public function testRejectsDuplicateDocument(): void
    {
        $user = $this->user();
        $users = $this->createMock(UserRepositoryInterface::class);
        $users->expects(self::once())->method('identityDocumentHashExists')->willReturn(true);
        $users->expects(self::never())->method('save');
        $audit = $this->createMock(AuditLogRepositoryInterface::class);

        $this->expectException(BusinessException::class);
        $this->service($users, $audit)->updateProfile(
            $user,
            new UpdateProfileInput('现有名称', null, null, null, null, null, '张三', 'id_card', '11010120000102123X'),
            '127.0.0.1',
        );
    }

    public function testRejectsFutureBirthDate(): void
    {
        $user = $this->user();
        $users = $this->createMock(UserRepositoryInterface::class);
        $audit = $this->createMock(AuditLogRepositoryInterface::class);

        $this->expectException(BusinessException::class);
        $this->service($users, $audit)->updateProfile(
            $user,
            new UpdateProfileInput('现有名称', null, null, null, '2999-01-01', null, null, null, null),
            '127.0.0.1',
        );
    }

    private function user(): User
    {
        $user = new User();
        $user->id = 12;
        $user->public_id = '01JTESTUSER000000000000001';
        $user->display_name = '现有名称';
        $user->phone_country_code = null;
        $user->phone_number = null;
        $user->gender = null;
        $user->birth_date = null;
        $user->bio = null;
        $user->realname_status = 'unverified';

        return $user;
    }

    private function service(
        UserRepositoryInterface $users,
        AuditLogRepositoryInterface $audit,
    ): ProfileService {
        return new ProfileService($users, $audit, new IpAddress(), $this->sensitiveData);
    }
}

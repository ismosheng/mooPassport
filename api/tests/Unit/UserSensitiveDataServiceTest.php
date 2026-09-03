<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\infrastructure\crypto\SensitiveDataCipher;
use app\common\model\User;
use app\common\service\UserSensitiveDataService;
use PHPUnit\Framework\TestCase;

/** 验证 OAuth 实名 Claims 的脱敏边界。 */
final class UserSensitiveDataServiceTest extends TestCase
{
    public function testReadsRealNameWithoutRequiringDocumentCiphertext(): void
    {
        $service = new UserSensitiveDataService(
            new SensitiveDataCipher(base64_encode(str_repeat('s', 32))),
        );
        $user = new User();
        $user->public_id = '01JTESTUSER000000000000003';
        $user->real_name_encrypted = $service->encryptRealName('李四', $user);
        $user->identity_document_number_encrypted = 'invalid-document-ciphertext';

        self::assertSame('李四', $service->realName($user));
    }

    public function testReturnsMaskedAndFullClaimsSeparately(): void
    {
        $service = new UserSensitiveDataService(
            new SensitiveDataCipher(base64_encode(str_repeat('s', 32))),
        );
        $user = new User();
        $user->public_id = '01JTESTUSER000000000000002';
        $user->identity_document_type = 'id_card';
        $user->realname_status = 'verified';
        $user->real_name_encrypted = $service->encryptRealName('张三', $user);
        $user->identity_document_number_encrypted = $service->encryptDocumentNumber(
            '11010120000102123X',
            $user,
        );

        $masked = $service->oauthClaims($user, false);
        $full = $service->oauthClaims($user, true);

        self::assertSame('张*', $masked['real_name']);
        self::assertSame('110***********123X', $masked['identity_document_number']);
        self::assertTrue($masked['realname_verified']);
        self::assertSame('张三', $full['real_name']);
        self::assertSame('11010120000102123X', $full['identity_document_number']);
    }
}

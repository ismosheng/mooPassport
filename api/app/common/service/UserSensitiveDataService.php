<?php

declare(strict_types=1);

namespace app\common\service;

use app\common\infrastructure\crypto\SensitiveDataCipher;
use app\common\model\User;

/**
 * 负责实名资料的加解密、去重摘要和最小化展示，不负责认证状态流转或持久化。
 */
final class UserSensitiveDataService
{
    private const REAL_NAME_FIELD = 'real_name';
    private const DOCUMENT_NUMBER_FIELD = 'identity_document_number';

    public function __construct(private readonly SensitiveDataCipher $cipher)
    {
    }

    public function encryptRealName(string $realName, User $user): string
    {
        return $this->cipher->encrypt($realName, $user->public_id, self::REAL_NAME_FIELD);
    }

    public function encryptDocumentNumber(string $documentNumber, User $user): string
    {
        return $this->cipher->encrypt($documentNumber, $user->public_id, self::DOCUMENT_NUMBER_FIELD);
    }

    public function documentFingerprint(string $documentNumber): string
    {
        return $this->cipher->fingerprint($documentNumber);
    }

    public function realName(User $user): ?string
    {
        return $this->decryptNullable($user->real_name_encrypted, $user, self::REAL_NAME_FIELD);
    }

    /** @return array<string, mixed> */
    public function ownerView(User $user): array
    {
        $realName = $this->realName($user);
        $documentNumber = $this->decryptNullable(
            $user->identity_document_number_encrypted,
            $user,
            self::DOCUMENT_NUMBER_FIELD,
        );

        return [
            'real_name' => $realName,
            'identity_document_type' => $user->identity_document_type,
            'identity_document_number_masked' => $documentNumber === null
                ? null
                : $this->maskDocumentNumber($documentNumber),
            'has_identity_document' => $documentNumber !== null,
            'realname_status' => $user->realname_status ?: 'unverified',
            'realname_verified_at' => $user->realname_verified_at?->format(DATE_ATOM),
        ];
    }

    /** @return array<string, mixed> */
    public function oauthClaims(User $user, bool $full): array
    {
        $realName = $this->realName($user);
        $documentNumber = $this->decryptNullable(
            $user->identity_document_number_encrypted,
            $user,
            self::DOCUMENT_NUMBER_FIELD,
        );

        return [
            'real_name' => $realName === null || $full ? $realName : $this->maskRealName($realName),
            'identity_document_type' => $user->identity_document_type,
            'identity_document_number' => $documentNumber === null || $full
                ? $documentNumber
                : $this->maskDocumentNumber($documentNumber),
            'realname_verified' => $user->realname_status === 'verified',
        ];
    }

    public function maskRealName(string $realName): string
    {
        $length = mb_strlen($realName);
        if ($length <= 1) {
            return '*';
        }

        return mb_substr($realName, 0, 1) . str_repeat('*', min(3, $length - 1));
    }

    public function maskDocumentNumber(string $documentNumber): string
    {
        $length = strlen($documentNumber);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        if ($length <= 8) {
            return substr($documentNumber, 0, 2)
                . str_repeat('*', $length - 4)
                . substr($documentNumber, -2);
        }

        return substr($documentNumber, 0, 3)
            . str_repeat('*', $length - 7)
            . substr($documentNumber, -4);
    }

    private function decryptNullable(?string $ciphertext, User $user, string $field): ?string
    {
        if ($ciphertext === null || $ciphertext === '') {
            return null;
        }

        return $this->cipher->decrypt($ciphertext, $user->public_id, $field);
    }
}

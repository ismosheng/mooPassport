<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\exception\BusinessException;
use app\common\model\User;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\service\UserSensitiveDataService;
use app\common\support\IpAddress;
use app\passport\dto\UpdateProfileInput;
use DateTimeImmutable;

/** 处理当前登录用户的个人资料更新。 */
final class ProfileService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly IpAddress $ipAddress,
        private readonly UserSensitiveDataService $sensitiveData,
    ) {
    }

    public function updateProfile(
        User $user,
        UpdateProfileInput $input,
        ?string $requestIp,
    ): User
    {
        $normalized = trim($input->displayName);
        $normalizedCountryCode = $this->nullableTrim($input->phoneCountryCode);
        $normalizedPhoneNumber = $this->nullableTrim($input->phoneNumber);
        if (($normalizedCountryCode === null) !== ($normalizedPhoneNumber === null)) {
            throw new BusinessException('profile_phone_incomplete', '请同时填写国家或地区代码和手机号。', 422);
        }

        $phoneUpdated = $normalizedCountryCode !== $user->phone_country_code
            || $normalizedPhoneNumber !== $user->phone_number;
        if ($normalizedCountryCode !== null
            && $normalizedPhoneNumber !== null
            && $phoneUpdated
            && $this->users->phoneExists($normalizedCountryCode, $normalizedPhoneNumber, $user->id)
        ) {
            throw new BusinessException('profile_phone_exists', '该手机号已被其他账号使用。', 409);
        }

        $displayNameUpdated = $normalized !== $user->display_name;
        $gender = $this->nullableTrim($input->gender);
        $birthDate = $this->nullableTrim($input->birthDate);
        $bio = $this->nullableTrim($input->bio);
        if ($birthDate !== null && new DateTimeImmutable($birthDate) > new DateTimeImmutable('today')) {
            throw new BusinessException('profile_birth_date_future', '出生日期不能晚于今天。', 422);
        }

        $realName = $this->nullableTrim($input->realName);
        $documentType = $this->nullableTrim($input->documentType);
        $documentNumber = $this->normalizeDocumentNumber($input->documentNumber);
        $hasStoredDocument = $user->identity_document_number_encrypted !== null;
        $isFirstRealnameSubmission = $user->real_name_encrypted === null && !$hasStoredDocument;
        $submittedRealnameData = $realName !== null || $documentType !== null || $documentNumber !== null;
        if ($isFirstRealnameSubmission && $submittedRealnameData
            && ($realName === null || $documentType === null || $documentNumber === null)
        ) {
            throw new BusinessException('profile_realname_incomplete', '首次填写实名资料时，请完整填写姓名、证件类型和证件号码。', 422);
        }
        if (!$isFirstRealnameSubmission && ($realName !== null || $documentNumber !== null) && $documentType === null) {
            throw new BusinessException('profile_realname_incomplete', '请保留或选择证件类型。', 422);
        }

        $documentUpdated = false;
        if ($documentNumber !== null) {
            $documentHash = $this->sensitiveData->documentFingerprint($documentNumber);
            if (!hash_equals((string) $user->identity_document_number_hash, $documentHash)) {
                if ($this->users->identityDocumentHashExists($documentHash, $user->id)) {
                    throw new BusinessException('profile_identity_document_exists', '该证件号码已被其他账号使用。', 409);
                }
                $user->identity_document_number_encrypted = $this->sensitiveData->encryptDocumentNumber(
                    $documentNumber,
                    $user,
                );
                $user->identity_document_number_hash = $documentHash;
                $documentUpdated = true;
            }
        }

        $realNameUpdated = false;
        if ($realName !== null) {
            // 姓名比较不应顺带解密证件号，缩小完整敏感数据进入内存的范围。
            $currentRealName = $this->sensitiveData->realName($user);
            if ($realName !== $currentRealName) {
                $user->real_name_encrypted = $this->sensitiveData->encryptRealName($realName, $user);
                $realNameUpdated = true;
            }
        }
        $documentTypeUpdated = $documentType !== null && $documentType !== $user->identity_document_type;
        $basicProfileUpdated = $gender !== $user->gender
            || $birthDate !== $user->birth_date?->format('Y-m-d')
            || $bio !== $user->bio;
        $realnameUpdated = $realNameUpdated || $documentUpdated || $documentTypeUpdated;

        if ($displayNameUpdated || $phoneUpdated || $basicProfileUpdated || $realnameUpdated) {
            $user->display_name = $normalized;
            $user->phone_country_code = $normalizedCountryCode;
            $user->phone_number = $normalizedPhoneNumber;
            $user->gender = $gender;
            $user->birth_date = $birthDate === null ? null : new DateTimeImmutable($birthDate);
            $user->bio = $bio;
            if ($documentType !== null) {
                $user->identity_document_type = $documentType;
            }
            if ($phoneUpdated) {
                // 号码所有权必须重新验证，资料编辑不能继承旧号码的验证状态。
                $user->phone_verified_at = null;
            }
            if ($realnameUpdated) {
                // 任一实名字段变化后原核验结论不再可信，必须重新进入核验流程。
                $user->realname_status = 'unverified';
                $user->realname_verified_at = null;
            }
            $this->users->save($user);
            $this->auditLogs->record([
                'event_type' => 'user.profile.updated',
                'user_id' => $user->id,
                'ip_address' => $this->ipAddress->toBinary($requestIp),
                'success' => true,
                'details' => [
                    'display_name_updated' => $displayNameUpdated,
                    'phone_updated' => $phoneUpdated,
                    'basic_profile_updated' => $basicProfileUpdated,
                    'realname_updated' => $realnameUpdated,
                    'identity_document_updated' => $documentUpdated,
                ],
            ]);
        }

        return $user;
    }

    private function normalizeDocumentNumber(?string $value): ?string
    {
        $normalized = strtoupper((string) preg_replace('/\s+/', '', trim((string) $value)));

        return $normalized === '' ? null : $normalized;
    }

    private function nullableTrim(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}

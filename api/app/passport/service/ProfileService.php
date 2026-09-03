<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\exception\BusinessException;
use app\common\model\User;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\support\IpAddress;

/** 处理当前登录用户的个人资料更新。 */
final class ProfileService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly IpAddress $ipAddress,
    ) {
    }

    public function updateProfile(
        User $user,
        string $displayName,
        ?string $phoneCountryCode,
        ?string $phoneNumber,
        ?string $requestIp,
    ): User
    {
        $normalized = trim($displayName);
        $normalizedCountryCode = $this->nullableTrim($phoneCountryCode);
        $normalizedPhoneNumber = $this->nullableTrim($phoneNumber);
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
        if ($displayNameUpdated || $phoneUpdated) {
            $user->display_name = $normalized;
            $user->phone_country_code = $normalizedCountryCode;
            $user->phone_number = $normalizedPhoneNumber;
            if ($phoneUpdated) {
                // 号码所有权必须重新验证，资料编辑不能继承旧号码的验证状态。
                $user->phone_verified_at = null;
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
                ],
            ]);
        }

        return $user;
    }

    private function nullableTrim(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}

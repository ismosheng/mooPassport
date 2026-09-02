<?php

declare(strict_types=1);

namespace app\passport\service;

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

    public function updateDisplayName(User $user, string $displayName, ?string $requestIp): User
    {
        $normalized = trim($displayName);
        if ($normalized !== $user->display_name) {
            $user->display_name = $normalized;
            $this->users->save($user);
            $this->auditLogs->record([
                'event_type' => 'user.profile.updated',
                'user_id' => $user->id,
                'ip_address' => $this->ipAddress->toBinary($requestIp),
                'success' => true,
            ]);
        }

        return $user;
    }
}

<?php

declare(strict_types=1);

namespace app\admin\service;

use app\admin\repository\contract\UserManagementRepositoryInterface;
use app\common\enum\UserStatus;
use app\common\exception\BusinessException;
use app\common\model\User;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\repository\contract\UserSessionRepositoryInterface;
use DateTimeImmutable;
use support\Db;

/** 组织后台用户状态变更，并保证禁用账号时同步撤销所有登录凭据。 */
final class UserManagementService
{
    public function __construct(
        private readonly UserManagementRepositoryInterface $users,
        private readonly UserSessionRepositoryInterface $sessions,
        private readonly AccessTokenRepositoryInterface $accessTokens,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly AuditLogRepositoryInterface $auditLogs,
    ) {}

    /** @return array{items:list<User>,total:int,roles:array<int,list<string>>,page:int,per_page:int} */
    public function search(string $keyword, ?string $status, ?bool $emailVerified, int $page, int $perPage): array
    {
        $result = $this->users->search(trim($keyword), $status, $emailVerified, $page, $perPage);
        return [...$result, 'page' => $page, 'per_page' => $perPage];
    }

    public function changeStatus(int $actorUserId, string $publicId, UserStatus $status, ?string $requestId): User
    {
        $user = $this->users->findByPublicId($publicId);
        if ($user === null) throw new BusinessException('user_not_found', '用户不存在。', 404);
        if ($user->id === $actorUserId && $status !== UserStatus::Active) {
            throw new BusinessException('cannot_disable_self', '不能禁用或锁定当前管理员账号。', 422);
        }
        $now = new DateTimeImmutable();
        return Db::connection()->transaction(function () use ($actorUserId, $user, $status, $now, $requestId): User {
            $previous = $user->status instanceof UserStatus ? $user->status->value : (string) $user->status;
            $user->status = $status;
            $this->users->save($user);
            if ($status !== UserStatus::Active) {
                $this->sessions->revokeAllForUser($user->id, $now);
                $this->accessTokens->revokeForUser($user->id, $now);
                $this->refreshTokens->revokeForUser($user->id, $now);
            }
            $this->auditLogs->record([
                'event_type' => 'admin.user.status_changed', 'user_id' => $user->id,
                'request_id' => $requestId, 'success' => true,
                'details' => ['actor_user_id' => $actorUserId, 'from' => $previous, 'to' => $status->value],
                'created_at' => $now,
            ]);
            return $user;
        });
    }

    /** @return array{user:User,statistics:array{roles:list<string>,active_sessions:int,active_consents:int,owned_applications:int,failed_logins_30d:int}} */
    public function detail(string $publicId): array
    {
        $user = $this->users->findByPublicId($publicId);
        if ($user === null) throw new BusinessException('user_not_found', '用户不存在。', 404);
        return ['user' => $user, 'statistics' => $this->users->statistics($user->id)];
    }

    public function forceLogout(int $actorUserId, string $publicId, ?string $requestId): int
    {
        $user = $this->users->findByPublicId($publicId);
        if ($user === null) throw new BusinessException('user_not_found', '用户不存在。', 404);
        if ($user->id === $actorUserId) throw new BusinessException('cannot_logout_self', '不能在这里强制下线当前管理员账号。', 422);
        $now = new DateTimeImmutable();
        return Db::connection()->transaction(function () use ($actorUserId, $user, $now, $requestId): int {
            $count = $this->sessions->revokeAllForUser($user->id, $now);
            $this->accessTokens->revokeForUser($user->id, $now);
            $this->refreshTokens->revokeForUser($user->id, $now);
            $this->auditLogs->record(['event_type' => 'admin.user.force_logout', 'user_id' => $user->id, 'request_id' => $requestId, 'success' => true, 'details' => ['actor_user_id' => $actorUserId, 'revoked_sessions' => $count], 'created_at' => $now]);
            return $count;
        });
    }
}

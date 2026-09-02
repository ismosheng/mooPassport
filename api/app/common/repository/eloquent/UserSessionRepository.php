<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\UserSession;
use app\common\repository\contract\UserSessionRepositoryInterface;
use DateTimeImmutable;

/**
 * 以不可逆令牌哈希的形式存储浏览器登录会话。
 */
final class UserSessionRepository implements UserSessionRepositoryInterface
{
    public function findActiveByHash(string $sessionHash, DateTimeImmutable $now): ?UserSession
    {
        $session = (new UserSession())->newQuery()
            ->where('session_hash', $sessionHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $now)
            ->first();

        return $session instanceof UserSession ? $session : null;
    }

    public function findActiveByIdForUser(int $id, int $userId, DateTimeImmutable $now): ?UserSession
    {
        $session = (new UserSession())->newQuery()
            ->whereKey($id)
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $now)
            ->first();

        return $session instanceof UserSession ? $session : null;
    }

    public function listActiveForUser(int $userId, DateTimeImmutable $now): array
    {
        /** @var list<UserSession> $sessions */
        $sessions = (new UserSession())->newQuery()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $now)
            ->orderByDesc('last_seen_at')
            ->orderByDesc('id')
            ->get()
            ->all();

        return $sessions;
    }

    public function create(array $attributes): UserSession
    {
        return UserSession::query()->create($attributes);
    }

    public function touch(int $id, DateTimeImmutable $seenAt): void
    {
        UserSession::query()->whereKey($id)->update(['last_seen_at' => $seenAt]);
    }

    public function revoke(int $id, DateTimeImmutable $revokedAt): bool
    {
        return UserSession::query()
            ->whereKey($id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]) === 1;
    }

    public function revokeAllForUser(int $userId, DateTimeImmutable $revokedAt): int
    {
        return UserSession::query()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }

    public function revokeOthersForUser(int $userId, int $exceptSessionId, DateTimeImmutable $revokedAt): int
    {
        return UserSession::query()
            ->where('user_id', $userId)
            ->where('id', '!=', $exceptSessionId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]);
    }
}

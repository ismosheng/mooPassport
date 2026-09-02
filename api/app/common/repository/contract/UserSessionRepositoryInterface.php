<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\UserSession;
use DateTimeImmutable;

/**
 * 定义浏览器登录会话的持久化操作。
 */
interface UserSessionRepositoryInterface
{
    public function findActiveByHash(string $sessionHash, DateTimeImmutable $now): ?UserSession;

    public function findActiveByIdForUser(int $id, int $userId, DateTimeImmutable $now): ?UserSession;

    /**
     * 返回用户当前有效的登录会话，按最近活跃时间倒序。
     *
     * @return list<UserSession>
     */
    public function listActiveForUser(int $userId, DateTimeImmutable $now): array;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): UserSession;

    public function touch(int $id, DateTimeImmutable $seenAt): void;

    public function revoke(int $id, DateTimeImmutable $revokedAt): bool;

    public function revokeAllForUser(int $userId, DateTimeImmutable $revokedAt): int;

    public function revokeOthersForUser(int $userId, int $exceptSessionId, DateTimeImmutable $revokedAt): int;
}

<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\OAuthRefreshToken;
use DateTimeImmutable;

/**
 * 持久化刷新令牌哈希，并强制执行轮换族状态变更。
 */
interface RefreshTokenRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): OAuthRefreshToken;

    public function findByHash(string $tokenHash): ?OAuthRefreshToken;

    /**
     * 将当前有效令牌准确标记为已使用一次。
     *
     * 返回 false 表示 Service 必须将请求视为重放，并撤销整个令牌族。
     */
    public function consume(string $tokenHash, DateTimeImmutable $usedAt): bool;

    public function revokeFamily(string $familyId, DateTimeImmutable $revokedAt): int;

    public function revokeForClient(int $clientId, DateTimeImmutable $revokedAt): int;

    public function revokeForClientAndUser(int $clientId, int $userId, DateTimeImmutable $revokedAt): int;

    public function revokeForUser(int $userId, DateTimeImmutable $revokedAt): int;
}

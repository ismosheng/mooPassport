<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\OAuthAccessToken;
use DateTimeImmutable;

/**
 * 持久化不透明访问令牌哈希及其撤销状态。
 */
interface AccessTokenRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): OAuthAccessToken;

    public function findActiveByHash(string $tokenHash, DateTimeImmutable $now): ?OAuthAccessToken;

    public function findByHash(string $tokenHash): ?OAuthAccessToken;

    public function revokeByHash(string $tokenHash, DateTimeImmutable $revokedAt): bool;

    public function revokeForClientAndUser(int $clientId, int $userId, DateTimeImmutable $revokedAt): int;

    public function revokeForClient(int $clientId, DateTimeImmutable $revokedAt): int;

    public function revokeForUser(int $userId, DateTimeImmutable $revokedAt): int;
}

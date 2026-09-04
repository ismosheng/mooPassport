<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\OAuthAuthorizationCode;
use DateTimeImmutable;

/**
 * 持久化短时授权码，并以原子方式消费。
 */
interface AuthorizationCodeRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): OAuthAuthorizationCode;

    public function findByHash(string $codeHash): ?OAuthAuthorizationCode;

    /**
     * 将未过期且未使用的授权码准确标记为已消费一次。
     *
     * 必须保证原子性，以阻止并发重放授权码。
     */
    public function consume(string $codeHash, DateTimeImmutable $usedAt): bool;

    /** 作废客户端尚未使用的授权码，防止配置变更后继续按旧配置交换令牌。 */
    public function revokeUnusedForClient(int $clientId, DateTimeImmutable $revokedAt): int;

    /** 作废指定用户已获授权但尚未交换的授权码。 */
    public function revokeUnusedForClientAndUser(
        int $clientId,
        int $userId,
        DateTimeImmutable $revokedAt,
    ): int;
}

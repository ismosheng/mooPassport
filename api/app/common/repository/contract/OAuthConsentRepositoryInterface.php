<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\OAuthConsent;
use DateTimeImmutable;

/**
 * 定义用户向 OAuth 客户端授权的持久化操作。
 */
interface OAuthConsentRepositoryInterface
{
    public function findActive(int $userId, int $clientId, DateTimeImmutable $now): ?OAuthConsent;

    /**
     * 返回用户当前有效的 OAuth 授权记录。
     *
     * @return list<OAuthConsent>
     */
    public function listActiveForUser(int $userId, DateTimeImmutable $now): array;

    /** @param list<string> $scopes */
    public function grant(int $userId, int $clientId, array $scopes, ?DateTimeImmutable $expiresAt): OAuthConsent;

    public function revoke(int $userId, int $clientId, DateTimeImmutable $revokedAt): bool;
}

<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\OAuthPushedAuthorizationRequest;
use DateTimeImmutable;

/**
 * 持久化短时推送授权请求，并保证 request_uri 只能完成一次授权决定。
 */
interface OAuthPushedAuthorizationRequestRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): OAuthPushedAuthorizationRequest;

    public function findUsableByHash(
        string $requestUriHash,
        DateTimeImmutable $now,
    ): ?OAuthPushedAuthorizationRequest;

    /**
     * 将未过期且未使用的推送请求准确标记为已使用。
     *
     * 条件更新是并发授权决定的安全边界，防止重复批准或拒绝同一请求。
     */
    public function consume(string $requestUriHash, DateTimeImmutable $usedAt): bool;
}

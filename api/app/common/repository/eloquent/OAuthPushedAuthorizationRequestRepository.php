<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthPushedAuthorizationRequest;
use app\common\repository\contract\OAuthPushedAuthorizationRequestRepositoryInterface;
use DateTimeImmutable;

/**
 * 通过 Eloquent 保存和原子消费 Pushed Authorization Request。
 */
final class OAuthPushedAuthorizationRequestRepository implements OAuthPushedAuthorizationRequestRepositoryInterface
{
    public function create(array $attributes): OAuthPushedAuthorizationRequest
    {
        return OAuthPushedAuthorizationRequest::query()->create($attributes);
    }

    public function findUsableByHash(
        string $requestUriHash,
        DateTimeImmutable $now,
    ): ?OAuthPushedAuthorizationRequest {
        $request = OAuthPushedAuthorizationRequest::query()
            ->where('request_uri_hash', $requestUriHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', $now)
            ->first();

        return $request instanceof OAuthPushedAuthorizationRequest ? $request : null;
    }

    public function consume(string $requestUriHash, DateTimeImmutable $usedAt): bool
    {
        return OAuthPushedAuthorizationRequest::query()
            ->where('request_uri_hash', $requestUriHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', $usedAt)
            ->update(['used_at' => $usedAt]) === 1;
    }

    public function revokeUnusedForClient(int $clientId, DateTimeImmutable $revokedAt): int
    {
        return OAuthPushedAuthorizationRequest::query()
            ->where('client_id', $clientId)
            ->whereNull('used_at')
            ->update(['used_at' => $revokedAt]);
    }
}

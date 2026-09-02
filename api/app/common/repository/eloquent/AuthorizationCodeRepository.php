<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthAuthorizationCode;
use app\common\repository\contract\AuthorizationCodeRepositoryInterface;
use DateTimeImmutable;

/**
 * 持久化授权码哈希并执行原子的一次性消费。
 */
final class AuthorizationCodeRepository implements AuthorizationCodeRepositoryInterface
{
    public function create(array $attributes): OAuthAuthorizationCode
    {
        return OAuthAuthorizationCode::query()->create($attributes);
    }

    public function findByHash(string $codeHash): ?OAuthAuthorizationCode
    {
        $code = OAuthAuthorizationCode::query()->where('code_hash', $codeHash)->first();

        return $code instanceof OAuthAuthorizationCode ? $code : null;
    }

    public function consume(string $codeHash, DateTimeImmutable $usedAt): bool
    {
        // 条件更新确保并发请求中只有一个能够成功消费授权码。
        return OAuthAuthorizationCode::query()
            ->where('code_hash', $codeHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', $usedAt)
            ->update(['used_at' => $usedAt]) === 1;
    }
}

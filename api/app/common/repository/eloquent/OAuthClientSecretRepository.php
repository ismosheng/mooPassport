<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthClientSecret;
use app\common\repository\contract\OAuthClientSecretRepositoryInterface;
use DateTimeImmutable;

/** 查询未撤销且未过期的客户端密钥，并记录最近使用时间。 */
final class OAuthClientSecretRepository implements OAuthClientSecretRepositoryInterface
{
    public function findUsableForClient(int $clientId, DateTimeImmutable $now): array
    {
        /** @var list<OAuthClientSecret> $secrets */
        $secrets = OAuthClientSecret::query()
            ->where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->where(static function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->get()
            ->all();

        return $secrets;
    }

    public function touchLastUsed(int $id, DateTimeImmutable $usedAt): void
    {
        OAuthClientSecret::query()->whereKey($id)->update(['last_used_at' => $usedAt]);
    }
}

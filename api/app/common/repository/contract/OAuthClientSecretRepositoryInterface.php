<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\OAuthClientSecret;
use DateTimeImmutable;

/** 定义 OAuth 客户端密钥查询及使用时间更新操作。 */
interface OAuthClientSecretRepositoryInterface
{
    /** @return list<OAuthClientSecret> */
    public function findUsableForClient(int $clientId, DateTimeImmutable $now): array;

    public function touchLastUsed(int $id, DateTimeImmutable $usedAt): void;
}

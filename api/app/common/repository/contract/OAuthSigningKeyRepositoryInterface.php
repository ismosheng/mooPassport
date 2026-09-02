<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\OAuthSigningKey;
use DateTimeImmutable;

/** 定义当前可向外发布的 OIDC 签名公钥查询。 */
interface OAuthSigningKeyRepositoryInterface
{
    /** @return list<OAuthSigningKey> */
    public function findPublishable(DateTimeImmutable $now): array;

    public function findActiveForSigning(DateTimeImmutable $now): ?OAuthSigningKey;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): OAuthSigningKey;
}

<?php

declare(strict_types=1);

namespace app\common\repository\contract;

/** 定义 OAuth 客户端回调地址的精确匹配查询。 */
interface OAuthClientRedirectUriRepositoryInterface
{
    public function existsForClient(int $clientId, string $redirectUri): bool;
}

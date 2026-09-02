<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthClientRedirectUri;
use app\common\repository\contract\OAuthClientRedirectUriRepositoryInterface;

/** 通过二进制排序规则精确查询客户端已登记的回调地址。 */
final class OAuthClientRedirectUriRepository implements OAuthClientRedirectUriRepositoryInterface
{
    public function existsForClient(int $clientId, string $redirectUri): bool
    {
        return OAuthClientRedirectUri::query()
            ->where('client_id', $clientId)
            ->where('redirect_uri', $redirectUri)
            ->exists();
    }
}

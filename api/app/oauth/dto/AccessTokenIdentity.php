<?php

declare(strict_types=1);

namespace app\oauth\dto;

use app\common\model\OAuthAccessToken;
use app\common\model\OAuthClient;
use app\common\model\User;

/** 封装单次资源请求中由 Access Token 恢复出的身份与权限范围。 */
final readonly class AccessTokenIdentity
{
    /** @param list<string> $scopes */
    public function __construct(
        public OAuthAccessToken $token,
        public OAuthClient $client,
        public User $user,
        public array $scopes,
    ) {
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}

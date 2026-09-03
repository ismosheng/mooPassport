<?php

declare(strict_types=1);

namespace app\oauth\dto;

use app\common\model\OAuthClient;
use app\common\model\OAuthScope;

/** 封装经过完整校验且回调地址可信的 OAuth 授权请求。 */
final readonly class AuthorizationRequest
{
    /**
     * @param list<OAuthScope> $scopes
     */
    public function __construct(
        public OAuthClient $client,
        public string $redirectUri,
        public array $scopes,
        public string $codeChallenge,
        public ?string $state,
        public ?string $nonce,
        public ?string $requestUri = null,
    ) {
    }

    /** @return list<string> */
    public function scopeNames(): array
    {
        return array_map(static fn (OAuthScope $scope): string => $scope->name, $this->scopes);
    }
}

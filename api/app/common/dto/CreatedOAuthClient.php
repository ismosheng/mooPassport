<?php

declare(strict_types=1);

namespace app\common\dto;

use app\common\model\OAuthClient;

/** 返回新客户端及仅展示一次的可选 AppSecret。 */
final readonly class CreatedOAuthClient
{
    public function __construct(
        public OAuthClient $client,
        public ?string $plainSecret,
    ) {
    }
}

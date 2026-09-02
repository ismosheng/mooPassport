<?php

declare(strict_types=1);

namespace app\common\dto;

/** 封装已完成 HTTP 基础校验的 OAuth 应用创建参数。 */
final readonly class CreateOAuthClientInput
{
    /**
     * @param list<string> $redirectUris
     * @param list<string> $scopes
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public string $applicationType,
        public array $redirectUris,
        public array $scopes,
        public ?string $logoUrl = null,
    ) {
    }
}

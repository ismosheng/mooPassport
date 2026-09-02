<?php

declare(strict_types=1);

namespace app\common\dto;

/** 封装 OAuth 应用资料与协议配置的可选更新字段。 */
final readonly class UpdateOAuthClientInput
{
    /**
     * @param list<string>|null $redirectUris
     * @param list<string>|null $scopes
     */
    public function __construct(
        public ?string $name,
        public ?string $description,
        public bool $descriptionProvided,
        public ?array $redirectUris,
        public ?array $scopes,
    ) {
    }
}

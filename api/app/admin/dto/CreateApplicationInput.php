<?php

declare(strict_types=1);

namespace app\admin\dto;

/** 封装逻辑应用及其接入能力的创建参数。 */
final readonly class CreateApplicationInput
{
    /**
     * @param list<string> $capabilities
     * @param list<string> $redirectUris
     * @param list<string> $loginScopes
     */
    public function __construct(
        public string $name,
        public ?string $description,
        public ?string $logoUrl,
        public array $capabilities,
        public string $loginApplicationType,
        public array $redirectUris,
        public array $loginScopes,
    ) {
    }
}

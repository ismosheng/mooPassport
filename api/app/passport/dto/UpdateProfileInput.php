<?php

declare(strict_types=1);

namespace app\passport\dto;

/** 封装已校验的个人资料及用户自主填写的实名资料。 */
final readonly class UpdateProfileInput
{
    public function __construct(
        public string $displayName,
        public ?string $phoneCountryCode,
        public ?string $phoneNumber,
        public ?string $gender,
        public ?string $birthDate,
        public ?string $bio,
        public ?string $realName,
        public ?string $documentType,
        public ?string $documentNumber,
    ) {
    }
}

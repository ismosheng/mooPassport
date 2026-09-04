<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\repository\contract\SystemSettingReaderInterface;

/** 读取匿名首页所需的公开配置；严格限制输出字段，避免泄露后台运行时设置。 */
final class PublicSiteService
{
    public function __construct(private readonly SystemSettingReaderInterface $settings)
    {
    }

    /** @return array{site_name:string,homepage_enabled:bool} */
    public function configuration(): array
    {
        $rows = $this->settings->allByKey();
        $configuredName = trim((string) ($rows['site.name']['value'] ?? ''));
        $fallbackName = trim((string) config('app.name', 'Moo Passport'));

        return [
            'site_name' => $configuredName !== '' ? $configuredName : ($fallbackName !== '' ? $fallbackName : 'Moo Passport'),
            'homepage_enabled' => !isset($rows['site.homepage_enabled'])
                || $rows['site.homepage_enabled']['value'] === '1',
        ];
    }
}

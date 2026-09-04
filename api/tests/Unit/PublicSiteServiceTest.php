<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\repository\contract\SystemSettingReaderInterface;
use app\passport\service\PublicSiteService;
use PHPUnit\Framework\TestCase;

/** 覆盖匿名站点配置的默认行为、开关解析和公开字段边界。 */
final class PublicSiteServiceTest extends TestCase
{
    public function testDefaultsToEnabledAndOnlyReturnsPublicFields(): void
    {
        $settings = $this->createStub(SystemSettingReaderInterface::class);
        $settings->method('allByKey')->willReturn([
            'oauth.access_token_ttl' => ['value' => '900', 'version' => 1],
        ]);

        $configuration = (new PublicSiteService($settings))->configuration();

        self::assertSame(['site_name', 'homepage_enabled'], array_keys($configuration));
        self::assertTrue($configuration['homepage_enabled']);
        self::assertNotSame('', $configuration['site_name']);
    }

    public function testReadsConfiguredNameAndDisabledHomepage(): void
    {
        $settings = $this->createStub(SystemSettingReaderInterface::class);
        $settings->method('allByKey')->willReturn([
            'site.name' => ['value' => '哞哞通行证', 'version' => 2],
            'site.homepage_enabled' => ['value' => '0', 'version' => 3],
        ]);

        self::assertSame(
            ['site_name' => '哞哞通行证', 'homepage_enabled' => false],
            (new PublicSiteService($settings))->configuration(),
        );
    }
}

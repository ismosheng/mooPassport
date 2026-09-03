<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\repository\contract\OAuthScopeRepositoryInterface;
use app\oauth\service\OidcMetadataService;
use PHPUnit\Framework\TestCase;

/** 验证 Discovery 发布的端点与实际公开路由保持一致。 */
final class OidcMetadataServiceTest extends TestCase
{
    public function testJwksUriUsesWellKnownRoute(): void
    {
        $scopes = $this->createStub(OAuthScopeRepositoryInterface::class);
        $scopes->method('findAllActive')->willReturn([]);

        $metadata = (new OidcMetadataService($scopes))->metadata();

        self::assertSame(
            rtrim((string) config('oauth.issuer'), '/') . '/.well-known/jwks.json',
            $metadata['jwks_uri'],
        );
        self::assertSame(['authorization_code', 'refresh_token'], $metadata['grant_types_supported']);
    }
}

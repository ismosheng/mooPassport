<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\support\TrustedProxy;
use PHPUnit\Framework\TestCase;

/** 验证客户端 IP 解析不会信任来自普通公网连接的伪造转发头。 */
final class TrustedProxyTest extends TestCase
{
    public function testUntrustedPeerCannotSpoofForwardedFor(): void
    {
        $resolver = new TrustedProxy(['10.0.0.0/8']);

        self::assertSame('203.0.113.10', $resolver->resolve(
            '203.0.113.10',
            '198.51.100.20',
            '198.51.100.21',
        ));
    }

    public function testTrustedProxyChainReturnsNearestUntrustedClient(): void
    {
        $resolver = new TrustedProxy(['10.0.0.0/8', '192.168.0.0/16']);

        self::assertSame('198.51.100.20', $resolver->resolve(
            '10.0.0.5',
            '203.0.113.1, 198.51.100.20, 192.168.1.8',
            null,
        ));
    }

    public function testIpv6CidrIsSupported(): void
    {
        $resolver = new TrustedProxy(['2001:db8:1::/48']);

        self::assertSame('2001:db8:2::10', $resolver->resolve(
            '2001:db8:1::5',
            '2001:db8:2::10',
            null,
        ));
    }

    public function testTrustedProxyCanUseRealIpFallback(): void
    {
        $resolver = new TrustedProxy(['127.0.0.1']);

        self::assertSame('198.51.100.30', $resolver->resolve(
            '127.0.0.1',
            null,
            '198.51.100.30',
        ));
    }
}

<?php

declare(strict_types=1);

namespace app\common\support;

/**
 * 仅在直连来源属于受信任代理时解析转发 IP。
 *
 * 该类不修改请求对象，也不决定代理部署拓扑；可信 CIDR 必须由部署环境显式配置。
 */
final class TrustedProxy
{
    /** @param list<string> $trustedProxies */
    public function __construct(private readonly array $trustedProxies)
    {
    }

    /**
     * @param string|null $forwardedFor X-Forwarded-For 原始值
     */
    public function resolve(string $remoteIp, ?string $forwardedFor, ?string $realIp): string
    {
        if (!$this->isTrusted($remoteIp)) {
            return $remoteIp;
        }

        // 从右向左剥离可信代理，得到离服务最近的非可信客户端地址。
        $chain = $forwardedFor === null
            ? []
            : array_values(array_filter(array_map('trim', explode(',', $forwardedFor))));
        $chain[] = $remoteIp;

        for ($index = count($chain) - 1; $index >= 0; --$index) {
            $candidate = $chain[$index];
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                continue;
            }
            if (!$this->isTrusted($candidate)) {
                return $candidate;
            }
        }

        return $this->validIp($realIp) ?? $remoteIp;
    }

    private function isTrusted(string $ip): bool
    {
        foreach ($this->trustedProxies as $range) {
            if ($this->matches($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private function matches(string $ip, string $range): bool
    {
        if (!str_contains($range, '/')) {
            return hash_equals($range, $ip);
        }

        [$network, $prefixText] = explode('/', $range, 2);
        $ipBinary = inet_pton($ip);
        $networkBinary = inet_pton($network);
        if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) {
            return false;
        }

        $prefix = filter_var($prefixText, FILTER_VALIDATE_INT);
        $maximum = strlen($ipBinary) * 8;
        if ($prefix === false || $prefix < 0 || $prefix > $maximum) {
            return false;
        }

        $bytes = intdiv($prefix, 8);
        $bits = $prefix % 8;
        if ($bytes > 0 && substr($ipBinary, 0, $bytes) !== substr($networkBinary, 0, $bytes)) {
            return false;
        }
        if ($bits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $bits)) & 0xFF;

        return (ord($ipBinary[$bytes]) & $mask) === (ord($networkBinary[$bytes]) & $mask);
    }

    private function validIp(?string $ip): ?string
    {
        return is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : null;
    }
}

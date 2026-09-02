<?php

declare(strict_types=1);

namespace app\common\support;

/** 将 IPv4、IPv6 文本与数据库使用的紧凑二进制格式互相转换。 */
final class IpAddress
{
    public function toBinary(?string $address): ?string
    {
        if ($address === null || $address === '') {
            return null;
        }

        $binary = inet_pton($address);

        return $binary === false ? null : $binary;
    }

    public function toString(mixed $binary): ?string
    {
        if (!is_string($binary) || $binary === '') {
            return null;
        }

        $address = inet_ntop($binary);

        return $address === false ? null : $address;
    }
}

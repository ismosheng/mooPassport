<?php

declare(strict_types=1);

namespace app\oauth\service;

use app\common\model\OAuthSigningKey;
use app\common\repository\contract\OAuthSigningKeyRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;

/** 仅使用公开字段白名单构造 JWKS，禁止输出任何 RSA 私钥参数。 */
final class JwksService
{
    private const PUBLIC_FIELDS = [
        'kty', 'use', 'key_ops', 'alg', 'kid', 'n', 'e', 'crv', 'x', 'y',
        'x5c', 'x5t', 'x5t#S256',
    ];

    public function __construct(private readonly OAuthSigningKeyRepositoryInterface $keys)
    {
    }

    /** @return array{keys: list<array<string, mixed>>} */
    public function publicKeySet(): array
    {
        $keys = array_map(
            fn (OAuthSigningKey $key): array => $this->sanitize($key),
            $this->keys->findPublishable(new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'))),
        );

        return ['keys' => $keys];
    }

    /** @return array<string, mixed> */
    private function sanitize(OAuthSigningKey $key): array
    {
        $publicJwk = array_intersect_key($key->public_jwk, array_flip(self::PUBLIC_FIELDS));
        $publicJwk['kid'] = $key->kid;
        $publicJwk['alg'] = $key->algorithm;
        $publicJwk['use'] = 'sig';

        return $publicJwk;
    }
}

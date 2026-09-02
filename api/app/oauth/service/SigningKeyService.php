<?php

declare(strict_types=1);

namespace app\oauth\service;

use app\common\infrastructure\crypto\PrivateKeyCipher;
use app\common\model\OAuthSigningKey;
use app\common\repository\contract\OAuthSigningKeyRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Symfony\Component\Uid\Ulid;

/** 生成 RSA 签名密钥，并确保私钥只以认证密文形式进入数据库。 */
final class SigningKeyService
{
    public function __construct(
        private readonly OAuthSigningKeyRepositoryInterface $keys,
        private readonly PrivateKeyCipher $cipher,
    ) {
    }

    public function ensureActiveKey(): OAuthSigningKey
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $existing = $this->keys->findActiveForSigning($now);
        if ($existing !== null) {
            return $existing;
        }

        $options = [
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ];
        $opensslConfig = $this->opensslConfigPath();
        if ($opensslConfig !== null) {
            $options['config'] = $opensslConfig;
        }

        $resource = openssl_pkey_new($options);
        if (!$resource instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('生成 OIDC RSA 密钥失败。');
        }

        $privateKeyPem = '';
        $exportOptions = $opensslConfig === null ? [] : ['config' => $opensslConfig];
        if (!openssl_pkey_export($resource, $privateKeyPem, null, $exportOptions)) {
            throw new RuntimeException('导出 OIDC RSA 私钥失败。');
        }
        $details = openssl_pkey_get_details($resource);
        if (!is_array($details) || !isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('读取 OIDC RSA 公钥参数失败。');
        }

        $keyId = (string) new Ulid();
        return $this->keys->create([
            'kid' => $keyId,
            'algorithm' => 'RS256',
            'public_jwk' => [
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => $keyId,
                'n' => $this->base64Url($details['rsa']['n']),
                'e' => $this->base64Url($details['rsa']['e']),
            ],
            'encrypted_private_key' => $this->cipher->encrypt($privateKeyPem, $keyId),
            'status' => 'active',
            'not_before' => $now,
            'created_at' => $now,
        ]);
    }

    public function privateKeyPem(OAuthSigningKey $key): string
    {
        return $this->cipher->decrypt($key->encrypted_private_key, $key->kid);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function opensslConfigPath(): ?string
    {
        $configured = getenv('OPENSSL_CONF');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        // 部分 Windows PHP 发行版的默认 OpenSSL 路径无效，实际配置随 PHP 安装目录提供。
        $bundled = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'extras'
            . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf';

        return is_file($bundled) ? $bundled : null;
    }
}

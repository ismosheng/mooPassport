<?php

declare(strict_types=1);

namespace app\common\infrastructure\crypto;

use RuntimeException;

/** 使用 XChaCha20-Poly1305 对数据库中的 OIDC 私钥进行认证加密。 */
final class PrivateKeyCipher
{
    public function __construct(private readonly string $base64Key)
    {
    }

    public function encrypt(string $privateKeyPem, string $keyId): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $privateKeyPem,
            $this->associatedData($keyId),
            $nonce,
            $this->key(),
        );

        return $nonce . $ciphertext;
    }

    public function decrypt(string $encryptedPrivateKey, string $keyId): string
    {
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($encryptedPrivateKey) <= $nonceLength) {
            throw new RuntimeException('OIDC 加密私钥格式无效。');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($encryptedPrivateKey, $nonceLength),
            $this->associatedData($keyId),
            substr($encryptedPrivateKey, 0, $nonceLength),
            $this->key(),
        );
        if ($plaintext === false) {
            throw new RuntimeException('OIDC 私钥解密失败。');
        }

        return $plaintext;
    }

    private function associatedData(string $keyId): string
    {
        // kid 参与认证，防止攻击者在数据库中交换两条密钥记录的密文。
        return 'moo-passport:oidc-signing-key:' . $keyId;
    }

    private function key(): string
    {
        $decoded = base64_decode($this->base64Key, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new RuntimeException('OIDC 私钥加密主密钥未配置或长度无效。');
        }

        return $decoded;
    }
}

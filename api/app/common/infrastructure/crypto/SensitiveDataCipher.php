<?php

declare(strict_types=1);

namespace app\common\infrastructure\crypto;

use RuntimeException;

/** 使用独立主密钥认证加密实名资料，并提供不可逆的证件去重摘要。 */
final class SensitiveDataCipher
{
    public function __construct(private readonly string $base64Key)
    {
    }

    public function encrypt(string $plaintext, string $userPublicId, string $field): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $this->associatedData($userPublicId, $field),
            $nonce,
            $this->key(),
        );

        return $nonce . $ciphertext;
    }

    public function decrypt(string $ciphertext, string $userPublicId, string $field): string
    {
        $nonceLength = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($ciphertext) <= $nonceLength) {
            throw new RuntimeException('实名资料密文格式无效。');
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($ciphertext, $nonceLength),
            $this->associatedData($userPublicId, $field),
            substr($ciphertext, 0, $nonceLength),
            $this->key(),
        );
        if ($plaintext === false) {
            throw new RuntimeException('实名资料解密失败。');
        }

        return $plaintext;
    }

    public function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, $this->key(), true);
    }

    private function associatedData(string $userPublicId, string $field): string
    {
        // 用户和字段名参与认证，防止数据库中的姓名与证件密文被跨用户或跨列替换。
        return 'moo-passport:user-sensitive-data:' . $userPublicId . ':' . $field;
    }

    private function key(): string
    {
        $decoded = base64_decode($this->base64Key, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES) {
            throw new RuntimeException('实名资料加密主密钥未配置或长度无效。');
        }

        return $decoded;
    }
}

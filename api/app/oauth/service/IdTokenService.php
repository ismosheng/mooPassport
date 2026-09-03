<?php

declare(strict_types=1);

namespace app\oauth\service;

use app\common\model\OAuthAuthorizationCode;
use app\common\model\OAuthClient;
use app\common\model\User;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;

/** 使用当前活动 RSA 密钥签发包含最小必要 Claims 的 RS256 ID Token。 */
final class IdTokenService
{
    public function __construct(private readonly SigningKeyService $signingKeys)
    {
    }

    public function issue(
        OAuthClient $client,
        User $user,
        OAuthAuthorizationCode $authorizationCode,
        string $accessToken,
    ): string {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
        $expiresAt = $now->add(new DateInterval('PT' . max(60, (int) config('oauth.id_token_ttl')) . 'S'));
        $key = $this->signingKeys->ensureActiveKey();
        $scopes = $authorizationCode->scopes;

        $claims = [
            'iss' => (string) config('oauth.issuer'),
            'sub' => $user->public_id,
            'aud' => $client->client_id,
            'iat' => $now->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
            'auth_time' => $authorizationCode->auth_time->getTimestamp(),
            'at_hash' => $this->accessTokenHash($accessToken),
        ];
        if ($authorizationCode->nonce !== null && $authorizationCode->nonce !== '') {
            $claims['nonce'] = $authorizationCode->nonce;
        }
        if (in_array('profile', $scopes, true)) {
            $claims['name'] = $user->display_name;
            $claims['preferred_username'] = $user->username;
            if ($user->avatar_url !== null) {
                $claims['picture'] = $user->avatar_url;
            }
        }
        if (in_array('email', $scopes, true)) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }

        $header = $this->base64UrlJson(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $key->kid]);
        $payload = $this->base64UrlJson($claims);
        $signingInput = $header . '.' . $payload;
        $signature = '';
        if (!openssl_sign(
            $signingInput,
            $signature,
            $this->signingKeys->privateKeyPem($key),
            OPENSSL_ALGO_SHA256,
        )) {
            throw new RuntimeException('OIDC ID Token 签名失败。');
        }

        return $signingInput . '.' . $this->base64Url($signature);
    }

    private function accessTokenHash(string $accessToken): string
    {
        // RS256 的 at_hash 使用访问令牌 SHA-256 摘要的左半部分。
        return $this->base64Url(substr(hash('sha256', $accessToken, true), 0, 16));
    }

    /** @param array<string, mixed> $value */
    private function base64UrlJson(array $value): string
    {
        try {
            return $this->base64Url(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        } catch (JsonException $exception) {
            throw new RuntimeException('OIDC ID Token JSON 编码失败。', 0, $exception);
        }
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

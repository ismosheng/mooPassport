<?php

declare(strict_types=1);

namespace app\oauth\service;

use app\common\enum\OAuthClientType;
use app\common\enum\TokenEndpointAuthMethod;
use app\common\exception\OAuthProtocolException;
use app\common\model\OAuthClient;
use app\common\model\OAuthClientSecret;
use app\common\repository\contract\OAuthClientRedirectUriRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\repository\contract\OAuthClientSecretRepositoryInterface;
use DateTimeImmutable;
use DateTimeZone;

/**
 * 统一校验 OAuth 客户端身份、状态、回调地址及客户端密钥。
 *
 * 授权端点与 Token 端点必须使用各自入口，避免混用错误语义；未登记的
 * redirect_uri 绝不能用于错误重定向，否则会形成开放重定向漏洞。
 */
final class OAuthClientValidationService
{
    private readonly string $dummySecretHash;

    public function __construct(
        private readonly OAuthClientRepositoryInterface $clients,
        private readonly OAuthClientRedirectUriRepositoryInterface $redirectUris,
        private readonly OAuthClientSecretRepositoryInterface $secrets,
    ) {
        // 未知客户端也执行一次密码哈希校验，降低通过响应耗时枚举 AppID 的风险。
        $this->dummySecretHash = password_hash('oauth-client-timing-placeholder', PASSWORD_ARGON2ID);
    }

    public function resolveAuthorizationClient(string $clientId, string $redirectUri): OAuthClient
    {
        $client = $this->activeClient($clientId, 'unauthorized_client', 400);

        // OAuth 要求按登记值精确匹配，禁止通配符、前缀匹配和运行时 URL 归一化。
        if ($redirectUri === '' || !$this->redirectUris->existsForClient($client->id, $redirectUri)) {
            throw new OAuthProtocolException('invalid_request', 'redirect_uri 未登记或不匹配。', 400);
        }
        if (parse_url($redirectUri, PHP_URL_FRAGMENT) !== null) {
            throw new OAuthProtocolException('invalid_request', 'redirect_uri 不允许包含 URL fragment。', 400);
        }

        return $client;
    }

    public function authenticateTokenClient(
        string $clientId,
        ?string $clientSecret,
        TokenEndpointAuthMethod $presentedMethod,
    ): OAuthClient {
        $client = $this->clients->findActiveByClientId(trim($clientId));
        if ($client === null) {
            $this->verifyAgainstDummyHash($clientSecret);
            throw $this->invalidClient();
        }

        $configuredMethod = $client->token_endpoint_auth_method;
        if (!$configuredMethod instanceof TokenEndpointAuthMethod || $configuredMethod !== $presentedMethod) {
            throw $this->invalidClient();
        }

        if ($client->client_type === OAuthClientType::Public) {
            if ($configuredMethod !== TokenEndpointAuthMethod::None || $clientSecret !== null) {
                throw $this->invalidClient();
            }

            return $client;
        }

        if (
            $configuredMethod !== TokenEndpointAuthMethod::ClientSecretBasic
            && $configuredMethod !== TokenEndpointAuthMethod::ClientSecretPost
        ) {
            throw $this->invalidClient();
        }

        if ($clientSecret === null || $clientSecret === '') {
            $this->verifyAgainstDummyHash($clientSecret);
            throw $this->invalidClient();
        }

        $matchedSecret = $this->matchUsableSecret($client->id, $clientSecret);
        if ($matchedSecret === null) {
            throw $this->invalidClient();
        }

        $this->secrets->touchLastUsed($matchedSecret->id, $this->now());

        return $client;
    }

    private function activeClient(string $clientId, string $error, int $status): OAuthClient
    {
        $client = $this->clients->findActiveByClientId(trim($clientId));
        if ($client === null) {
            throw new OAuthProtocolException($error, '客户端不存在或已被禁用。', $status);
        }

        return $client;
    }

    private function matchUsableSecret(int $clientId, string $plainSecret): ?OAuthClientSecret
    {
        $matched = null;
        foreach ($this->secrets->findUsableForClient($clientId, $this->now()) as $secret) {
            // 不提前退出循环，避免密钥轮换期间通过耗时推断匹配的是哪一把密钥。
            if (password_verify($plainSecret, $secret->secret_hash)) {
                $matched = $secret;
            }
        }

        if ($matched === null) {
            $this->verifyAgainstDummyHash($plainSecret);
        }

        return $matched;
    }

    private function verifyAgainstDummyHash(?string $plainSecret): void
    {
        password_verify($plainSecret ?? '', $this->dummySecretHash);
    }

    private function invalidClient(): OAuthProtocolException
    {
        return new OAuthProtocolException('invalid_client', '客户端认证失败。', 401);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

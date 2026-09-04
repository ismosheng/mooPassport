<?php

declare(strict_types=1);

namespace app\oauth\service;

use app\common\enum\GrantType;
use app\common\enum\OAuthApplicationType;
use app\common\enum\OAuthClientStatus;
use app\common\enum\UserStatus;
use app\common\exception\OAuthProtocolException;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\support\SecureToken;
use app\oauth\dto\AccessTokenIdentity;
use DateTimeImmutable;
use DateTimeZone;

/** 使用不透明令牌哈希恢复并校验资源访问身份。 */
final class AccessTokenAuthenticationService
{
    public function __construct(
        private readonly AccessTokenRepositoryInterface $accessTokens,
        private readonly OAuthClientRepositoryInterface $clients,
        private readonly UserRepositoryInterface $users,
        private readonly SecureToken $secureToken,
    ) {
    }

    public function authenticate(string $rawToken): AccessTokenIdentity
    {
        if (preg_match('/^[A-Za-z0-9_-]{20,200}$/D', $rawToken) !== 1) {
            throw $this->invalidToken();
        }

        $token = $this->accessTokens->findActiveByHash(
            $this->secureToken->hash($rawToken),
            new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai')),
        );
        if ($token === null) {
            throw $this->invalidToken();
        }

        $client = $this->clients->findById($token->client_id);
        if (
            $client === null
            || $client->status !== OAuthClientStatus::Active
        ) {
            throw $this->invalidToken();
        }

        if ($token->user_id === null) {
            if (
                $token->grant_type !== GrantType::ClientCredentials
                || $client->application_type !== OAuthApplicationType::Service
                || !in_array(GrantType::ClientCredentials->value, $client->allowed_grant_types, true)
                || !in_array('service', $token->scopes, true)
            ) {
                throw $this->invalidToken();
            }

            return new AccessTokenIdentity($token, $client, null, $token->scopes);
        }

        $user = $this->users->findById($token->user_id);
        if ($user === null || $user->status !== UserStatus::Active) {
            throw $this->invalidToken();
        }

        return new AccessTokenIdentity($token, $client, $user, $token->scopes);
    }

    private function invalidToken(): OAuthProtocolException
    {
        // 所有失败统一返回相同错误，避免泄露令牌、客户端或用户的具体状态。
        return new OAuthProtocolException('invalid_token', 'Access Token 无效或已过期。', 401);
    }
}

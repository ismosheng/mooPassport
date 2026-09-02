<?php

declare(strict_types=1);

namespace app\oauth\service;

use app\common\enum\OAuthClientType;
use app\common\enum\TokenEndpointAuthMethod;
use app\common\enum\UserStatus;
use app\common\exception\OAuthProtocolException;
use app\common\model\OAuthAccessToken;
use app\common\model\OAuthClient;
use app\common\model\OAuthRefreshToken;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\support\SecureToken;
use DateTimeImmutable;
use DateTimeZone;

/** 认证机密客户端，并返回其自身令牌的 RFC 7662 内省结果。 */
final class TokenIntrospectionService
{
    public function __construct(
        private readonly OAuthClientValidationService $clientValidation,
        private readonly AccessTokenRepositoryInterface $accessTokens,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly UserRepositoryInterface $users,
        private readonly SecureToken $secureToken,
    ) {
    }

    /** @return array<string, mixed> */
    public function introspect(
        string $clientId,
        ?string $clientSecret,
        TokenEndpointAuthMethod $authenticationMethod,
        string $rawToken,
        ?string $tokenTypeHint,
    ): array {
        $requestingClient = $this->clientValidation->authenticateTokenClient(
            $clientId,
            $clientSecret,
            $authenticationMethod,
        );
        if ($requestingClient->client_type !== OAuthClientType::Confidential) {
            throw new OAuthProtocolException('invalid_client', 'Token 内省只允许机密客户端调用。', 401);
        }

        $tokenHash = $this->secureToken->hash($rawToken);
        if ($tokenTypeHint === 'refresh_token') {
            return $this->refreshTokenResult($tokenHash, $requestingClient)
                ?? $this->accessTokenResult($tokenHash, $requestingClient)
                ?? ['active' => false];
        }

        return $this->accessTokenResult($tokenHash, $requestingClient)
            ?? $this->refreshTokenResult($tokenHash, $requestingClient)
            ?? ['active' => false];
    }

    /** @return array<string, mixed>|null */
    private function accessTokenResult(string $tokenHash, OAuthClient $requestingClient): ?array
    {
        $token = $this->accessTokens->findByHash($tokenHash);
        if ($token === null) {
            return null;
        }
        if (
            $token->client_id !== $requestingClient->id
            || $token->revoked_at !== null
            || $token->expires_at <= $this->now()
        ) {
            return ['active' => false];
        }

        return $this->activeClaims(
            $token,
            $requestingClient,
            'access_token',
            $token->scopes,
            $token->user_id,
        );
    }

    /** @return array<string, mixed>|null */
    private function refreshTokenResult(string $tokenHash, OAuthClient $requestingClient): ?array
    {
        $token = $this->refreshTokens->findByHash($tokenHash);
        if ($token === null) {
            return null;
        }
        if (
            $token->client_id !== $requestingClient->id
            || $token->used_at !== null
            || $token->revoked_at !== null
            || $token->expires_at <= $this->now()
        ) {
            return ['active' => false];
        }

        return $this->activeClaims(
            $token,
            $requestingClient,
            'refresh_token',
            $token->scopes,
            $token->user_id,
        );
    }

    /**
     * @param OAuthAccessToken|OAuthRefreshToken $token
     * @param list<string> $scopes
     * @return array<string, mixed>
     */
    private function activeClaims(
        OAuthAccessToken|OAuthRefreshToken $token,
        OAuthClient $client,
        string $tokenType,
        array $scopes,
        ?int $userId,
    ): array {
        $claims = [
            'active' => true,
            'client_id' => $client->client_id,
            'token_type' => $tokenType,
            'scope' => implode(' ', $scopes),
            'exp' => $token->expires_at->getTimestamp(),
            'iat' => $token->created_at->getTimestamp(),
        ];

        if ($userId !== null) {
            $user = $this->users->findById($userId);
            if ($user === null || $user->status !== UserStatus::Active) {
                return ['active' => false];
            }
            $claims['sub'] = $user->public_id;
            $claims['username'] = $user->username;
        }

        return $claims;
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

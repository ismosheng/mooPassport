<?php

declare(strict_types=1);

namespace app\oauth\service;

use app\common\enum\GrantType;
use app\common\enum\TokenEndpointAuthMethod;
use app\common\enum\UserStatus;
use app\common\exception\OAuthProtocolException;
use app\common\model\OAuthAuthorizationCode;
use app\common\model\OAuthRefreshToken;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\AuthorizationCodeRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\support\SecureToken;
use app\oauth\dto\TokenResult;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use support\Db;
use Symfony\Component\Uid\Ulid;

/** 校验授权码与 PKCE 绑定关系，并原子签发不透明访问令牌。 */
final class TokenService
{
    public function __construct(
        private readonly OAuthClientValidationService $clientValidation,
        private readonly AuthorizationCodeRepositoryInterface $authorizationCodes,
        private readonly AccessTokenRepositoryInterface $accessTokens,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly SecureToken $secureToken,
        private readonly IdTokenService $idTokens,
    ) {
    }

    public function exchangeAuthorizationCode(
        string $clientId,
        ?string $clientSecret,
        TokenEndpointAuthMethod $authenticationMethod,
        string $code,
        string $redirectUri,
        string $codeVerifier,
    ): TokenResult {
        $client = $this->clientValidation->authenticateTokenClient(
            $clientId,
            $clientSecret,
            $authenticationMethod,
        );

        if (preg_match('/^[A-Za-z0-9._~-]{43,128}$/D', $codeVerifier) !== 1) {
            throw $this->invalidGrant();
        }

        $codeHash = $this->secureToken->hash($code);
        $authorizationCode = $this->authorizationCodes->findByHash($codeHash);
        $now = $this->now();
        if (!$this->isUsableCode($authorizationCode, $client->id, $redirectUri, $now)) {
            throw $this->invalidGrant();
        }

        $calculatedChallenge = rtrim(strtr(
            base64_encode(hash('sha256', $codeVerifier, true)),
            '+/',
            '-_',
        ), '=');
        if (!hash_equals($authorizationCode->code_challenge, $calculatedChallenge)) {
            throw $this->invalidGrant();
        }

        $user = $this->users->findById($authorizationCode->user_id);
        if ($user === null || $user->status !== UserStatus::Active) {
            throw $this->invalidGrant();
        }

        $accessToken = $this->secureToken->generate();
        $refreshToken = $this->shouldIssueRefreshToken($client->allowed_grant_types, $authorizationCode->scopes)
            ? $this->secureToken->generate(48)
            : null;
        $accessTokenTtl = max(60, (int) $client->access_token_ttl);
        $refreshTokenTtl = max(300, (int) $client->refresh_token_ttl);

        /** @var string|null $idToken */
        $idToken = Db::connection()->transaction(function () use (
            $codeHash,
            $authorizationCode,
            $client,
            $user,
            $now,
            $accessToken,
            $refreshToken,
            $accessTokenTtl,
            $refreshTokenTtl,
        ): ?string {
            // 条件更新是授权码单次使用的最终安全边界，并发交换只能成功一次。
            if (!$this->authorizationCodes->consume($codeHash, $now)) {
                throw $this->invalidGrant();
            }

            $access = $this->accessTokens->create([
                'token_hash' => $this->secureToken->hash($accessToken),
                'client_id' => $client->id,
                'user_id' => $authorizationCode->user_id,
                'grant_type' => GrantType::AuthorizationCode,
                'scopes' => $authorizationCode->scopes,
                'expires_at' => $now->add(new DateInterval('PT' . $accessTokenTtl . 'S')),
                'created_at' => $now,
            ]);

            if ($refreshToken !== null) {
                $this->refreshTokens->create([
                    'token_hash' => $this->secureToken->hash($refreshToken),
                    'family_id' => (string) new Ulid(),
                    'access_token_id' => $access->id,
                    'client_id' => $client->id,
                    'user_id' => $authorizationCode->user_id,
                    'scopes' => $authorizationCode->scopes,
                    'expires_at' => $now->add(new DateInterval('PT' . $refreshTokenTtl . 'S')),
                    'created_at' => $now,
                ]);
            }

            $this->auditLogs->record([
                'event_type' => 'oauth.token.issued',
                'user_id' => $authorizationCode->user_id,
                'client_id' => $client->id,
                'success' => true,
                'details' => [
                    'grant_type' => GrantType::AuthorizationCode->value,
                    'refresh_token_issued' => $refreshToken !== null,
                ],
            ]);

            // ID Token 必须在同一事务内完成签名，签名失败时回滚授权码消费和令牌写入。
            if (in_array('openid', (array) $authorizationCode->scopes, true)) {
                return $this->idTokens->issue($client, $user, $authorizationCode, $accessToken);
            }

            return null;
        });

        return new TokenResult(
            $accessToken,
            $accessTokenTtl,
            $refreshToken,
            implode(' ', (array) $authorizationCode->scopes),
            $idToken,
        );
    }

    public function rotateRefreshToken(
        string $clientId,
        ?string $clientSecret,
        TokenEndpointAuthMethod $authenticationMethod,
        string $rawRefreshToken,
        ?string $requestedScope,
    ): TokenResult {
        $client = $this->clientValidation->authenticateTokenClient(
            $clientId,
            $clientSecret,
            $authenticationMethod,
        );
        if (!in_array(GrantType::RefreshToken->value, $client->allowed_grant_types, true)) {
            throw new OAuthProtocolException('unauthorized_client', '该客户端不允许使用刷新令牌。');
        }

        $now = $this->now();
        $tokenHash = $this->secureToken->hash($rawRefreshToken);
        $storedToken = $this->refreshTokens->findByHash($tokenHash);
        if ($storedToken === null || $storedToken->client_id !== $client->id) {
            throw $this->invalidGrant();
        }

        if ($storedToken->used_at !== null) {
            $this->revokeReplayedFamily($storedToken, $now);
            throw $this->invalidGrant();
        }
        if ($storedToken->revoked_at !== null || $storedToken->expires_at <= $now) {
            throw $this->invalidGrant();
        }

        $user = $this->users->findById($storedToken->user_id);
        if ($user === null || $user->status !== UserStatus::Active) {
            throw $this->invalidGrant();
        }

        $scopes = $this->narrowRefreshScopes($storedToken->scopes, $requestedScope);
        $newAccessToken = $this->secureToken->generate();
        $newRefreshToken = $this->secureToken->generate(48);
        $accessTokenTtl = max(60, (int) $client->access_token_ttl);

        /** @var TokenResult|null $result */
        $result = Db::connection()->transaction(function () use (
            $tokenHash,
            $storedToken,
            $client,
            $now,
            $scopes,
            $newAccessToken,
            $newRefreshToken,
            $accessTokenTtl,
        ): ?TokenResult {
            if (!$this->refreshTokens->consume($tokenHash, $now)) {
                // 此分支表示并发重放；必须提交整个令牌族的撤销，不能在事务内抛异常回滚。
                $this->refreshTokens->revokeFamily($storedToken->family_id, $now);
                $this->accessTokens->revokeForClientAndUser(
                    $storedToken->client_id,
                    $storedToken->user_id,
                    $now,
                );
                $this->recordRefreshReplay($storedToken, $now);
                return null;
            }

            $access = $this->accessTokens->create([
                'token_hash' => $this->secureToken->hash($newAccessToken),
                'client_id' => $client->id,
                'user_id' => $storedToken->user_id,
                'grant_type' => GrantType::RefreshToken,
                'scopes' => $scopes,
                'expires_at' => $now->add(new DateInterval('PT' . $accessTokenTtl . 'S')),
                'created_at' => $now,
            ]);

            // 新令牌继承原令牌族的绝对过期时间，防止持续刷新形成永久会话。
            $this->refreshTokens->create([
                'token_hash' => $this->secureToken->hash($newRefreshToken),
                'family_id' => $storedToken->family_id,
                'parent_id' => $storedToken->id,
                'access_token_id' => $access->id,
                'client_id' => $client->id,
                'user_id' => $storedToken->user_id,
                'scopes' => $scopes,
                'expires_at' => $storedToken->expires_at,
                'created_at' => $now,
            ]);

            $this->auditLogs->record([
                'event_type' => 'oauth.token.refreshed',
                'user_id' => $storedToken->user_id,
                'client_id' => $client->id,
                'success' => true,
                'details' => ['grant_type' => GrantType::RefreshToken->value],
            ]);

            return new TokenResult(
                $newAccessToken,
                $accessTokenTtl,
                $newRefreshToken,
                implode(' ', $scopes),
            );
        });

        if ($result === null) {
            throw $this->invalidGrant();
        }

        return $result;
    }

    private function isUsableCode(
        ?OAuthAuthorizationCode $code,
        int $clientId,
        string $redirectUri,
        DateTimeImmutable $now,
    ): bool {
        return $code !== null
            && $code->client_id === $clientId
            && $code->used_at === null
            && $code->expires_at > $now
            && hash_equals($code->redirect_uri, $redirectUri)
            && $code->code_challenge_method === 'S256';
    }

    /**
     * @param array<int, mixed> $grantTypes
     * @param array<int, mixed> $scopes
     */
    private function shouldIssueRefreshToken(array $grantTypes, array $scopes): bool
    {
        return in_array(GrantType::RefreshToken->value, $grantTypes, true)
            && in_array('offline_access', $scopes, true);
    }

    /**
     * @param list<string> $originalScopes
     * @return list<string>
     */
    private function narrowRefreshScopes(array $originalScopes, ?string $requestedScope): array
    {
        if ($requestedScope === null || trim($requestedScope) === '') {
            return $originalScopes;
        }
        if (strlen($requestedScope) > 1000) {
            throw new OAuthProtocolException('invalid_scope', 'scope 参数格式无效。');
        }

        $requested = preg_split('/ +/', trim($requestedScope), -1, PREG_SPLIT_NO_EMPTY);
        if ($requested === false || $requested === []) {
            throw new OAuthProtocolException('invalid_scope', 'scope 参数格式无效。');
        }
        $requested = array_values(array_unique($requested));
        foreach ($requested as $scope) {
            if (!in_array($scope, $originalScopes, true)) {
                throw new OAuthProtocolException('invalid_scope', '刷新请求不能扩大原令牌的 Scope。');
            }
        }

        return $requested;
    }

    private function revokeReplayedFamily(OAuthRefreshToken $token, DateTimeImmutable $now): void
    {
        Db::connection()->transaction(function () use ($token, $now): void {
            $this->refreshTokens->revokeFamily($token->family_id, $now);
            // Access Token 未保存 family_id，只能按用户与客户端扩大撤销范围以立即止损。
            $this->accessTokens->revokeForClientAndUser($token->client_id, $token->user_id, $now);
            $this->recordRefreshReplay($token, $now);
        });
    }

    private function recordRefreshReplay(OAuthRefreshToken $token, DateTimeImmutable $now): void
    {
        $this->auditLogs->record([
            'event_type' => 'oauth.refresh_token.replayed',
            'user_id' => $token->user_id,
            'client_id' => $token->client_id,
            'success' => false,
            'details' => [
                'family_id' => $token->family_id,
                'detected_at' => $now->format(DATE_ATOM),
            ],
        ]);
    }

    private function invalidGrant(): OAuthProtocolException
    {
        // 对外统一错误，避免泄露授权码存在性、过期状态或具体绑定失败原因。
        return new OAuthProtocolException('invalid_grant', '授权码无效、已过期或已被使用。');
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

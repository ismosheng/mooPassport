<?php

declare(strict_types=1);

use app\common\enum\OAuthClientStatus;
use app\common\enum\TokenEndpointAuthMethod;
use app\common\enum\UserStatus;
use app\common\exception\OAuthProtocolException;
use app\common\model\OAuthClient;
use app\common\model\User;
use app\common\model\UserSession;
use app\oauth\service\AccessTokenAuthenticationService;
use app\oauth\service\AuthorizationService;
use app\oauth\service\TokenIntrospectionService;
use app\oauth\service\TokenService;
use app\passport\dto\AuthenticatedSession;
use app\common\dto\CreateOAuthClientInput;
use app\common\service\OAuthClientManagementService;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;
use support\App;
use support\Db;
use Symfony\Component\Uid\Ulid;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
Dotenv::createUnsafeImmutable($root)->load();
App::loadAllConfig();
/** @var ContainerInterface $container */
$container = require $root . '/config/container.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectOAuthError = static function (callable $operation, string $expectedError, string $message): void {
    try {
        $operation();
    } catch (OAuthProtocolException $exception) {
        if ($exception->oauthError === $expectedError) {
            return;
        }
        throw new RuntimeException($message . '，实际错误：' . $exception->oauthError);
    }

    throw new RuntimeException($message . '，请求未被拒绝。');
};
$base64Url = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
$countsBefore = [
    'users' => User::query()->count(),
    'clients' => OAuthClient::query()->count(),
];

$connection = Db::connection();
$connection->beginTransaction();

try {
    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
    $user = User::query()->create([
        'public_id' => (string) new Ulid(),
        'username' => 'e2e_' . bin2hex(random_bytes(4)),
        'email' => bin2hex(random_bytes(6)) . '@example.invalid',
        'password_hash' => password_hash('E2e-Password-123456', PASSWORD_ARGON2ID),
        'display_name' => 'OAuth 端到端测试用户',
        'status' => UserStatus::Active,
        'email_verified_at' => $now,
    ]);
    $session = UserSession::query()->create([
        'session_hash' => hash('sha256', random_bytes(32), true),
        'user_id' => $user->id,
        'last_seen_at' => $now,
        'expires_at' => $now->modify('+1 hour'),
        'created_at' => $now,
    ]);
    $identity = new AuthenticatedSession($user, $session);

    $management = $container->get(OAuthClientManagementService::class);
    $created = $management->create($user->id, new CreateOAuthClientInput(
        'OAuth 端到端测试应用',
        null,
        'web',
        ['http://127.0.0.1:39123/callback'],
        ['openid', 'profile', 'email', 'offline_access'],
    ));
    $assert(is_string($created->plainSecret), '机密客户端没有签发 AppSecret。');

    $verifier = $base64Url(random_bytes(48));
    $challenge = $base64Url(hash('sha256', $verifier, true));
    $authorization = $container->get(AuthorizationService::class);
    $authorizationParameters = [
        'client_id' => $created->client->client_id,
        'redirect_uri' => 'http://127.0.0.1:39123/callback',
        'response_type' => 'code',
        'scope' => 'openid profile email offline_access',
        'state' => 'e2e-state',
        'nonce' => 'e2e-nonce',
        'code_challenge' => $challenge,
        'code_challenge_method' => 'S256',
    ];

    $expectOAuthError(
        fn () => $authorization->validate([
            ...$authorizationParameters,
            'redirect_uri' => 'http://attacker.example/callback',
        ]),
        'invalid_request',
        '未登记 redirect_uri 没有被拒绝',
    );
    $expectOAuthError(
        fn () => $authorization->validate([
            ...$authorizationParameters,
            'code_challenge_method' => 'plain',
        ]),
        'invalid_request',
        '非 S256 PKCE 没有被拒绝',
    );
    $expectOAuthError(
        fn () => $authorization->validate([
            ...$authorizationParameters,
            'scope' => 'openid admin',
        ]),
        'invalid_scope',
        '未授权 Scope 没有被拒绝',
    );

    $authorizationRequest = $authorization->validate($authorizationParameters);
    $deniedRedirect = $authorization->deny($authorizationRequest, $identity);
    parse_str((string) parse_url($deniedRedirect, PHP_URL_QUERY), $deniedParameters);
    $assert(($deniedParameters['error'] ?? null) === 'access_denied', '拒绝授权没有返回 access_denied。');
    $assert(($deniedParameters['state'] ?? null) === 'e2e-state', '拒绝授权没有原样返回 state。');

    $redirect = $authorization->approve($authorizationRequest, $identity);
    parse_str((string) parse_url($redirect, PHP_URL_QUERY), $callbackParameters);
    $authorizationCode = $callbackParameters['code'] ?? null;
    $assert(is_string($authorizationCode) && $authorizationCode !== '', '授权回调没有返回授权码。');
    $assert(($callbackParameters['state'] ?? null) === 'e2e-state', '授权回调 state 不匹配。');

    $tokens = $container->get(TokenService::class);
    $expectOAuthError(
        fn () => $tokens->exchangeAuthorizationCode(
            $created->client->client_id,
            'wrong-client-secret',
            TokenEndpointAuthMethod::ClientSecretBasic,
            $authorizationCode,
            'http://127.0.0.1:39123/callback',
            $verifier,
        ),
        'invalid_client',
        '错误 AppSecret 没有被拒绝',
    );
    $expectOAuthError(
        fn () => $tokens->exchangeAuthorizationCode(
            $created->client->client_id,
            $created->plainSecret,
            TokenEndpointAuthMethod::ClientSecretBasic,
            $authorizationCode,
            'http://127.0.0.1:39123/callback',
            $base64Url(random_bytes(48)),
        ),
        'invalid_grant',
        '错误 PKCE verifier 没有被拒绝',
    );

    $issued = $tokens->exchangeAuthorizationCode(
        $created->client->client_id,
        $created->plainSecret,
        TokenEndpointAuthMethod::ClientSecretBasic,
        $authorizationCode,
        'http://127.0.0.1:39123/callback',
        $verifier,
    );
    $assert($issued->idToken !== null, 'openid 授权没有签发 ID Token。');
    $assert($issued->refreshToken !== null, 'offline_access 授权没有签发 Refresh Token。');
    $assert(count(explode('.', $issued->idToken)) === 3, 'ID Token 不是合法的 JWS 紧凑格式。');

    $expectOAuthError(
        fn () => $tokens->exchangeAuthorizationCode(
            $created->client->client_id,
            $created->plainSecret,
            TokenEndpointAuthMethod::ClientSecretBasic,
            $authorizationCode,
            'http://127.0.0.1:39123/callback',
            $verifier,
        ),
        'invalid_grant',
        '授权码重放没有被拒绝',
    );

    $accessIdentity = $container->get(AccessTokenAuthenticationService::class)
        ->authenticate($issued->accessToken);
    $assert($accessIdentity->user->id === $user->id, 'Access Token 恢复的用户不匹配。');
    $assert($accessIdentity->hasScope('openid'), 'Access Token 缺少 openid Scope。');

    $expectOAuthError(
        fn () => $tokens->rotateRefreshToken(
            $created->client->client_id,
            $created->plainSecret,
            TokenEndpointAuthMethod::ClientSecretBasic,
            $issued->refreshToken,
            'openid admin',
        ),
        'invalid_scope',
        'Refresh Token 扩大 Scope 没有被拒绝',
    );

    $rotated = $tokens->rotateRefreshToken(
        $created->client->client_id,
        $created->plainSecret,
        TokenEndpointAuthMethod::ClientSecretBasic,
        $issued->refreshToken,
        'openid email offline_access',
    );
    $assert($rotated->refreshToken !== null, 'Refresh Token 轮换没有返回新令牌。');
    $assert($rotated->refreshToken !== $issued->refreshToken, 'Refresh Token 轮换返回了原令牌。');

    $replayDetected = false;
    try {
        $tokens->rotateRefreshToken(
            $created->client->client_id,
            $created->plainSecret,
            TokenEndpointAuthMethod::ClientSecretBasic,
            $issued->refreshToken,
            null,
        );
    } catch (OAuthProtocolException $exception) {
        $replayDetected = $exception->oauthError === 'invalid_grant';
    }
    $assert($replayDetected, '旧 Refresh Token 重放没有被拒绝。');

    $introspection = $container->get(TokenIntrospectionService::class)->introspect(
        $created->client->client_id,
        $created->plainSecret,
        TokenEndpointAuthMethod::ClientSecretBasic,
        $rotated->accessToken,
        'access_token',
    );
    $assert(($introspection['active'] ?? true) === false, '重放发生后 Access Token 仍然有效。');

    $serviceClient = $management->create($user->id, new CreateOAuthClientInput(
        'OAuth 机器调用测试应用',
        null,
        'service',
        [],
        ['service'],
    ));
    $assert(is_string($serviceClient->plainSecret), 'Service 客户端没有签发 AppSecret。');
    $expectOAuthError(
        fn () => $tokens->issueClientCredentials(
            $serviceClient->client->client_id,
            $serviceClient->plainSecret,
            TokenEndpointAuthMethod::ClientSecretBasic,
            'service openid',
        ),
        'invalid_scope',
        '客户端凭证授权扩大 Scope 没有被拒绝',
    );
    $machineToken = $tokens->issueClientCredentials(
        $serviceClient->client->client_id,
        $serviceClient->plainSecret,
        TokenEndpointAuthMethod::ClientSecretBasic,
        null,
    );
    $assert($machineToken->scope === 'service', '客户端凭证令牌 Scope 不正确。');
    $assert($machineToken->refreshToken === null, '客户端凭证授权不应签发 Refresh Token。');
    $assert($machineToken->idToken === null, '客户端凭证授权不应签发 ID Token。');
    $machineIdentity = $container->get(AccessTokenAuthenticationService::class)
        ->authenticate($machineToken->accessToken);
    $assert($machineIdentity->user === null, '客户端凭证令牌不应关联用户身份。');
    $assert($machineIdentity->hasScope('service'), '客户端凭证令牌缺少 service Scope。');
    $machineIntrospection = $container->get(TokenIntrospectionService::class)->introspect(
        $serviceClient->client->client_id,
        $serviceClient->plainSecret,
        TokenEndpointAuthMethod::ClientSecretBasic,
        $machineToken->accessToken,
        'access_token',
    );
    $assert(($machineIntrospection['active'] ?? false) === true, '客户端凭证令牌 introspection 未激活。');
    $assert(!array_key_exists('sub', $machineIntrospection), '客户端凭证令牌不应包含用户 sub。');

    fwrite(STDOUT, "PASS authorization_code_pkce\n");
    fwrite(STDOUT, "PASS oauth_negative_security_cases\n");
    fwrite(STDOUT, "PASS authorization_denial_callback\n");
    fwrite(STDOUT, "PASS access_refresh_id_token\n");
    fwrite(STDOUT, "PASS bearer_identity\n");
    fwrite(STDOUT, "PASS refresh_rotation_replay\n");
    fwrite(STDOUT, "PASS client_credentials\n");
} finally {
    $connection->rollBack();
}

$assert(User::query()->count() === $countsBefore['users'], '测试用户没有完全回滚。');
$assert(OAuthClient::query()->count() === $countsBefore['clients'], '测试客户端没有完全回滚。');
fwrite(STDOUT, "PASS transaction_rollback\n");


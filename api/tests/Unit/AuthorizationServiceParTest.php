<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\enum\OAuthClientType;
use app\common\enum\OAuthClientStatus;
use app\common\enum\TokenEndpointAuthMethod;
use app\common\exception\OAuthProtocolException;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\model\OAuthAuthorizationCode;
use app\common\model\OAuthClient;
use app\common\model\OAuthConsent;
use app\common\model\OAuthPushedAuthorizationRequest;
use app\common\model\OAuthScope;
use app\common\model\User;
use app\common\model\UserSession;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\AuthorizationCodeRepositoryInterface;
use app\common\repository\contract\OAuthClientRedirectUriRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\repository\contract\OAuthClientSecretRepositoryInterface;
use app\common\repository\contract\OAuthConsentRepositoryInterface;
use app\common\repository\contract\OAuthPushedAuthorizationRequestRepositoryInterface;
use app\common\repository\contract\OAuthScopeRepositoryInterface;
use app\common\support\IpAddress;
use app\common\support\SecureToken;
use app\oauth\dto\ClientCredentials;
use app\passport\dto\AuthenticatedSession;
use app\oauth\service\AuthorizationService;
use app\oauth\service\OAuthClientValidationService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** 验证 PAR 锁定授权参数、拒绝 URL 篡改，并且只允许一次授权决定。 */
final class AuthorizationServiceParTest extends TestCase
{
    public function testPushedRequestLocksScopeAndRejectsUrlOverrides(): void
    {
        $pushed = $this->createMock(OAuthPushedAuthorizationRequestRepositoryInterface::class);
        $pushed->expects(self::once())
            ->method('create')
            ->with(self::callback(
                static fn (array $attributes): bool => $attributes['parameters']['scope'] === 'profile openid',
            ))
            ->willReturn(new OAuthPushedAuthorizationRequest());
        $pushed->expects(self::once())
            ->method('findUsableByHash')
            ->willReturnCallback(function (string $hash, DateTimeImmutable $now): OAuthPushedAuthorizationRequest {
                self::assertSame(32, strlen($hash));
                self::assertInstanceOf(DateTimeImmutable::class, $now);

                $request = new OAuthPushedAuthorizationRequest();
                $request->parameters = [
                    'client_id' => 'moo_test_client',
                    'redirect_uri' => 'http://127.0.0.1/callback',
                    'response_type' => 'code',
                    'scope' => 'profile openid',
                    'state' => 'test-state',
                    'code_challenge' => rtrim(strtr(base64_encode(str_repeat('a', 32)), '+/', '-_'), '='),
                    'code_challenge_method' => 'S256',
                ];

                return $request;
            });

        $service = $this->service($pushed);
        $result = $service->push(
            $this->parameters('profile openid'),
            new ClientCredentials('moo_test_client', null, TokenEndpointAuthMethod::None),
        );

        $request = $service->validate(['request_uri' => $result->requestUri]);
        self::assertSame(['profile', 'openid'], $request->scopeNames());

        try {
            $service->validate([
                'request_uri' => $result->requestUri,
                'scope' => 'openid',
            ]);
            self::fail('request_uri 与 scope 同时提交时没有被拒绝。');
        } catch (OAuthProtocolException $exception) {
            self::assertSame('invalid_request', $exception->oauthError);
        }
    }

    public function testPushedRequestCanBeConsumedOnlyOnce(): void
    {
        $pushed = $this->createMock(OAuthPushedAuthorizationRequestRepositoryInterface::class);
        $pushed->expects(self::exactly(2))
            ->method('consume')
            ->willReturnOnConsecutiveCalls(true, false);

        $consents = $this->createMock(OAuthConsentRepositoryInterface::class);
        $consents->expects(self::once())->method('grant')->willReturn(new OAuthConsent());
        $codes = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $codes->expects(self::once())->method('create')->willReturn(new OAuthAuthorizationCode());

        $service = $this->service($pushed, $consents, $codes);
        $identity = new AuthenticatedSession(new User(), new UserSession());
        $identity->user->id = 12;
        $request = $service->validate($this->parameters('openid'));
        $request = new \app\oauth\dto\AuthorizationRequest(
            $request->client,
            $request->redirectUri,
            $request->scopes,
            $request->codeChallenge,
            $request->state,
            $request->nonce,
            'urn:ietf:params:oauth:request_uri:test',
        );

        $service->approve($request, $identity);

        try {
            $service->approve($request, $identity);
            self::fail('已消费的 request_uri 被重复批准。');
        } catch (OAuthProtocolException $exception) {
            self::assertSame('invalid_request', $exception->oauthError);
        }
    }

    /** @return array<string, mixed> */
    private function parameters(string $scope): array
    {
        return [
            'client_id' => 'moo_test_client',
            'redirect_uri' => 'http://127.0.0.1/callback',
            'response_type' => 'code',
            'scope' => $scope,
            'state' => 'test-state',
            'code_challenge' => rtrim(strtr(base64_encode(str_repeat('a', 32)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
        ];
    }

    private function service(
        OAuthPushedAuthorizationRequestRepositoryInterface $pushed,
        ?OAuthConsentRepositoryInterface $consents = null,
        ?AuthorizationCodeRepositoryInterface $codes = null,
    ): AuthorizationService {
        $client = new OAuthClient();
        $client->id = 7;
        $client->client_id = 'moo_test_client';
        $client->client_type = OAuthClientType::Public;
        $client->token_endpoint_auth_method = TokenEndpointAuthMethod::None;
        $client->status = OAuthClientStatus::Active;
        $client->allowed_grant_types = ['authorization_code'];
        $client->allowed_response_types = ['code'];
        $client->require_consent = true;

        $clients = $this->createStub(OAuthClientRepositoryInterface::class);
        $clients->method('findActiveByClientId')->willReturn($client);
        $redirectUris = $this->createStub(OAuthClientRedirectUriRepositoryInterface::class);
        $redirectUris->method('existsForClient')->willReturn(true);
        $secrets = $this->createStub(OAuthClientSecretRepositoryInterface::class);
        $validation = new OAuthClientValidationService($clients, $redirectUris, $secrets);

        $scopes = $this->createStub(OAuthScopeRepositoryInterface::class);
        $scopes->method('findAllowedForClient')->willReturn([
            $this->scope('openid'),
            $this->scope('profile'),
        ]);

        $audit = $this->createMock(AuditLogRepositoryInterface::class);
        $audit->method('record')->willReturn(new \app\common\model\OAuthAuditLog());
        $transactions = $this->createMock(TransactionManagerInterface::class);
        $transactions->method('run')->willReturnCallback(
            static fn (\Closure $callback): mixed => $callback(),
        );

        return new AuthorizationService(
            $validation,
            $scopes,
            $consents ?? $this->createStub(OAuthConsentRepositoryInterface::class),
            $pushed,
            $codes ?? $this->createStub(AuthorizationCodeRepositoryInterface::class),
            $audit,
            new SecureToken(),
            new IpAddress(),
            $transactions,
        );
    }

    private function scope(string $name): OAuthScope
    {
        $scope = new OAuthScope();
        $scope->name = $name;
        $scope->display_name = $name;
        $scope->is_default = false;

        return $scope;
    }
}

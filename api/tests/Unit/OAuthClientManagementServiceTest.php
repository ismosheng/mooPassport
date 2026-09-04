<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\dto\CreateOAuthClientInput;
use app\common\dto\UpdateOAuthClientInput;
use app\common\exception\BusinessException;
use app\common\model\OAuthClient;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\AuthorizationCodeRepositoryInterface;
use app\common\repository\contract\OAuthClientManagementRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\repository\contract\OAuthScopeRepositoryInterface;
use app\common\repository\contract\OAuthPushedAuthorizationRequestRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\service\OAuthClientManagementService;
use app\common\support\PasswordHasher;
use app\common\support\SecureToken;
use PHPUnit\Framework\TestCase;

/** 覆盖后台全局管理与用户所有者隔离之间不可混用的查询边界。 */
final class OAuthClientManagementServiceTest extends TestCase
{
    public function testAdministratorCanResolveClientWithoutBeingItsOwner(): void
    {
        $client = new OAuthClient();
        $client->setRawAttributes(['id' => 8, 'client_id' => 'moo_other_owner', 'owner_user_id' => 99]);

        $clients = $this->createMock(OAuthClientRepositoryInterface::class);
        $clients->expects(self::once())->method('findByClientId')->with('moo_other_owner')->willReturn($client);
        $management = $this->createMock(OAuthClientManagementRepositoryInterface::class);
        $management->expects(self::never())->method('findOwnedByClientId');

        $service = new OAuthClientManagementService(
            $clients,
            $management,
            $this->createStub(OAuthScopeRepositoryInterface::class),
            $this->createStub(AuditLogRepositoryInterface::class),
            $this->createStub(AccessTokenRepositoryInterface::class),
            $this->createStub(RefreshTokenRepositoryInterface::class),
            $this->createStub(AuthorizationCodeRepositoryInterface::class),
            $this->createStub(OAuthPushedAuthorizationRequestRepositoryInterface::class),
            new SecureToken(),
            new PasswordHasher(),
            $this->createStub(TransactionManagerInterface::class),
        );

        self::assertSame($client, $service->detail(7, 'moo_other_owner', false));
    }

    public function testServiceClientCannotReplaceMachineScope(): void
    {
        $client = new OAuthClient();
        $client->setRawAttributes(['id' => 8, 'client_id' => 'moo_service', 'application_type' => 'service']);
        $clients = $this->createMock(OAuthClientRepositoryInterface::class);
        $clients->method('findByClientId')->willReturn($client);
        $service = $this->service($clients);

        $this->expectBusinessError('invalid_service_scope', fn () => $service->update(
            7,
            'moo_service',
            new UpdateOAuthClientInput(null, null, false, null, ['service', 'openid']),
            false,
        ));
    }

    public function testLoginClientCannotAddMachineScope(): void
    {
        $client = new OAuthClient();
        $client->setRawAttributes(['id' => 8, 'client_id' => 'moo_login', 'application_type' => 'web']);
        $clients = $this->createMock(OAuthClientRepositoryInterface::class);
        $clients->method('findByClientId')->willReturn($client);
        $service = $this->service($clients);

        $this->expectBusinessError('invalid_scope', fn () => $service->update(
            7,
            'moo_login',
            new UpdateOAuthClientInput(null, null, false, null, ['openid', 'service']),
            false,
        ));
    }

    public function testRejectsRedirectUrisWithForbiddenComponentsAndSchemes(): void
    {
        $service = $this->service($this->createStub(OAuthClientRepositoryInterface::class));
        $cases = [
            ['web', ['https://example.com/callback#fragment']],
            ['web', ['https://user@example.com/callback']],
            ['native', ['javascript:alert(1)']],
            ['native', ['myapp://callback']],
        ];

        foreach ($cases as [$applicationType, $redirectUris]) {
            $this->expectBusinessError('invalid_redirect_uri', fn () => $service->create(
                7,
                new CreateOAuthClientInput('测试应用', null, $applicationType, $redirectUris, ['openid']),
            ));
        }
    }

    private function service(OAuthClientRepositoryInterface $clients): OAuthClientManagementService
    {
        return new OAuthClientManagementService(
            $clients,
            $this->createStub(OAuthClientManagementRepositoryInterface::class),
            $this->createStub(OAuthScopeRepositoryInterface::class),
            $this->createStub(AuditLogRepositoryInterface::class),
            $this->createStub(AccessTokenRepositoryInterface::class),
            $this->createStub(RefreshTokenRepositoryInterface::class),
            $this->createStub(AuthorizationCodeRepositoryInterface::class),
            $this->createStub(OAuthPushedAuthorizationRequestRepositoryInterface::class),
            new SecureToken(),
            new PasswordHasher(),
            $this->createStub(TransactionManagerInterface::class),
        );
    }

    private function expectBusinessError(string $errorCode, callable $operation): void
    {
        try {
            $operation();
            self::fail('预期业务异常未抛出。');
        } catch (BusinessException $exception) {
            self::assertSame($errorCode, $exception->errorCode);
        }
    }
}

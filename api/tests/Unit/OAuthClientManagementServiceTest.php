<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\model\OAuthClient;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\OAuthClientManagementRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\repository\contract\OAuthScopeRepositoryInterface;
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
            new SecureToken(),
            new PasswordHasher(),
            $this->createStub(TransactionManagerInterface::class),
        );

        self::assertSame($client, $service->detail(7, 'moo_other_owner', false));
    }
}

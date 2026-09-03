<?php

declare(strict_types=1);

namespace tests\Unit;

use app\admin\service\ApplicationManagementService;
use app\common\model\Application;
use app\common\model\OAuthClient;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\repository\contract\ApplicationRepositoryInterface;
use app\common\repository\contract\OAuthClientManagementRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\service\OAuthClientManagementService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ApplicationManagementServiceTest extends TestCase
{
    public function testSearchKeepsServerPaginationAndGroupsClients(): void
    {
        $application = new Application();
        $application->setRawAttributes(['id' => 12, 'public_id' => '01TESTAPP00000000000000000', 'owner_user_id' => 3, 'name' => '素材库', 'status' => 'active']);
        $client = new OAuthClient();
        $client->setRawAttributes(['id' => 8, 'application_id' => 12, 'client_id' => 'moo_test']);
        $applications = $this->createMock(ApplicationRepositoryInterface::class);
        $applications->expects(self::once())->method('searchAll')->with('素材', 'active', 2, 20)->willReturn(['items' => [$application], 'total' => 21]);
        $clients = $this->createMock(OAuthClientManagementRepositoryInterface::class);
        $clients->expects(self::once())->method('listByApplicationIds')->with([12])->willReturn([$client]);
        $service = new ApplicationManagementService(
            $applications,
            $clients,
            (new ReflectionClass(OAuthClientManagementService::class))->newInstanceWithoutConstructor(),
            $this->createStub(OAuthClientRepositoryInterface::class),
            $this->createStub(TransactionManagerInterface::class),
        );
        $result = $service->search(' 素材 ', 'active', 2, 20);
        self::assertSame(21, $result['total']);
        self::assertSame($client, $result['items'][0]['clients'][0]);
    }
}


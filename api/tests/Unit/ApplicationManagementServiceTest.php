<?php

declare(strict_types=1);

namespace tests\Unit;

use app\admin\service\ApplicationManagementService;
use app\common\model\Application;
use app\common\model\OAuthAuditLog;
use app\common\model\OAuthClient;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\ApplicationRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\AuthorizationCodeRepositoryInterface;
use app\common\repository\contract\OAuthClientManagementRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\repository\contract\OAuthPushedAuthorizationRequestRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\service\OAuthClientManagementService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Closure;

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
            $this->createStub(AccessTokenRepositoryInterface::class),
            $this->createStub(RefreshTokenRepositoryInterface::class),
            $this->createStub(AuthorizationCodeRepositoryInterface::class),
            $this->createStub(OAuthPushedAuthorizationRequestRepositoryInterface::class),
            $this->createStub(AuditLogRepositoryInterface::class),
            $this->createStub(TransactionManagerInterface::class),
        );
        $result = $service->search(' 素材 ', 'active', 2, 20);
        self::assertSame(21, $result['total']);
        self::assertSame($client, $result['items'][0]['clients'][0]);
    }

    public function testDisablingApplicationRevokesCredentialsWithoutOverwritingClientStatus(): void
    {
        $application = new Application();
        $application->setRawAttributes([
            'id' => 12,
            'public_id' => '01TESTAPP00000000000000000',
            'owner_user_id' => 3,
            'name' => '素材库',
            'status' => 'active',
        ]);
        $client = new OAuthClient();
        $client->setRawAttributes(['id' => 8, 'application_id' => 12, 'client_id' => 'moo_test', 'status' => 'disabled']);

        $applications = $this->createMock(ApplicationRepositoryInterface::class);
        $applications->expects(self::exactly(2))->method('findByPublicId')->willReturn($application);
        $applications->expects(self::once())->method('save')->with(self::callback(
            static fn (Application $saved): bool => $saved->status === 'disabled',
        ));
        $clients = $this->createMock(OAuthClientManagementRepositoryInterface::class);
        $clients->expects(self::exactly(2))->method('listByApplicationIds')->with([12])->willReturn([$client]);
        $clientRepository = $this->createMock(OAuthClientRepositoryInterface::class);
        $clientRepository->expects(self::never())->method('save');
        $accessTokens = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokens->expects(self::once())->method('revokeForClient')->with(8, self::isInstanceOf(\DateTimeImmutable::class));
        $refreshTokens = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokens->expects(self::once())->method('revokeForClient')->with(8, self::isInstanceOf(\DateTimeImmutable::class));
        $authorizationCodes = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authorizationCodes->expects(self::once())->method('revokeUnusedForClient')->with(8, self::isInstanceOf(\DateTimeImmutable::class));
        $pushedRequests = $this->createMock(OAuthPushedAuthorizationRequestRepositoryInterface::class);
        $pushedRequests->expects(self::once())->method('revokeUnusedForClient')->with(8, self::isInstanceOf(\DateTimeImmutable::class));
        $auditLogs = $this->createMock(AuditLogRepositoryInterface::class);
        $auditLogs->expects(self::once())->method('record')->with(self::callback(
            static fn (array $event): bool => $event['event_type'] === 'oauth.application.status_changed'
                && $event['details']['status'] === 'disabled',
        ))->willReturn(new OAuthAuditLog());
        $transactions = $this->createMock(TransactionManagerInterface::class);
        $transactions->method('run')->willReturnCallback(static fn (Closure $callback): mixed => $callback());

        $service = new ApplicationManagementService(
            $applications,
            $clients,
            (new ReflectionClass(OAuthClientManagementService::class))->newInstanceWithoutConstructor(),
            $clientRepository,
            $accessTokens,
            $refreshTokens,
            $authorizationCodes,
            $pushedRequests,
            $auditLogs,
            $transactions,
        );

        $result = $service->updateStatus('01TESTAPP00000000000000000', 'disabled', 99);

        self::assertSame('disabled', $result['application']->status);
        self::assertSame('disabled', $result['clients'][0]->status->value);
    }

    public function testEnablingApplicationDoesNotEnableClientsOrRestoreCredentials(): void
    {
        $application = new Application();
        $application->setRawAttributes([
            'id' => 12,
            'public_id' => '01TESTAPP00000000000000000',
            'owner_user_id' => 3,
            'name' => '素材库',
            'status' => 'disabled',
        ]);
        $client = new OAuthClient();
        $client->setRawAttributes(['id' => 8, 'application_id' => 12, 'client_id' => 'moo_test', 'status' => 'disabled']);

        $applications = $this->createMock(ApplicationRepositoryInterface::class);
        $applications->expects(self::exactly(2))->method('findByPublicId')->willReturn($application);
        $applications->expects(self::once())->method('save');
        $clients = $this->createMock(OAuthClientManagementRepositoryInterface::class);
        $clients->expects(self::exactly(2))->method('listByApplicationIds')->willReturn([$client]);
        $clientRepository = $this->createMock(OAuthClientRepositoryInterface::class);
        $clientRepository->expects(self::never())->method('save');
        $accessTokens = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokens->expects(self::never())->method('revokeForClient');
        $refreshTokens = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokens->expects(self::never())->method('revokeForClient');
        $authorizationCodes = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authorizationCodes->expects(self::never())->method('revokeUnusedForClient');
        $pushedRequests = $this->createMock(OAuthPushedAuthorizationRequestRepositoryInterface::class);
        $pushedRequests->expects(self::never())->method('revokeUnusedForClient');
        $transactions = $this->createMock(TransactionManagerInterface::class);
        $transactions->method('run')->willReturnCallback(static fn (Closure $callback): mixed => $callback());
        $auditLogs = $this->createStub(AuditLogRepositoryInterface::class);
        $auditLogs->method('record')->willReturn(new OAuthAuditLog());

        $service = new ApplicationManagementService(
            $applications,
            $clients,
            (new ReflectionClass(OAuthClientManagementService::class))->newInstanceWithoutConstructor(),
            $clientRepository,
            $accessTokens,
            $refreshTokens,
            $authorizationCodes,
            $pushedRequests,
            $auditLogs,
            $transactions,
        );

        $result = $service->updateStatus('01TESTAPP00000000000000000', 'active', 99);

        self::assertSame('active', $result['application']->status);
        self::assertSame('disabled', $result['clients'][0]->status->value);
    }
}


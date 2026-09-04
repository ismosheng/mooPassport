<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\model\OAuthAuditLog;
use app\common\model\OAuthClient;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\AuthorizationCodeRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\repository\contract\OAuthConsentRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\support\IpAddress;
use app\passport\service\ConsentManagementService;
use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** 验证撤销用户授权会在同一事务内终止该应用持有的全部用户凭据。 */
final class ConsentManagementServiceTest extends TestCase
{
    public function testRevocationAlsoRevokesTokensAndPendingAuthorizationCodes(): void
    {
        $client = new OAuthClient();
        $client->setRawAttributes(['id' => 8, 'client_id' => 'moo_login']);

        $clients = $this->createMock(OAuthClientRepositoryInterface::class);
        $clients->expects(self::once())->method('findByClientId')->with('moo_login')->willReturn($client);
        $consents = $this->createMock(OAuthConsentRepositoryInterface::class);
        $consents->expects(self::once())->method('revoke')
            ->with(42, 8, self::isInstanceOf(DateTimeImmutable::class))
            ->willReturn(true);
        $accessTokens = $this->createMock(AccessTokenRepositoryInterface::class);
        $accessTokens->expects(self::once())->method('revokeForClientAndUser')
            ->with(8, 42, self::isInstanceOf(DateTimeImmutable::class));
        $refreshTokens = $this->createMock(RefreshTokenRepositoryInterface::class);
        $refreshTokens->expects(self::once())->method('revokeForClientAndUser')
            ->with(8, 42, self::isInstanceOf(DateTimeImmutable::class));
        $authorizationCodes = $this->createMock(AuthorizationCodeRepositoryInterface::class);
        $authorizationCodes->expects(self::once())->method('revokeUnusedForClientAndUser')
            ->with(8, 42, self::isInstanceOf(DateTimeImmutable::class));
        $auditLogs = $this->createMock(AuditLogRepositoryInterface::class);
        $auditLogs->expects(self::once())->method('record')->with(self::callback(
            static fn (array $event): bool => $event['event_type'] === 'oauth.consent.revoked'
                && $event['user_id'] === 42
                && $event['client_id'] === 8,
        ))->willReturn(new OAuthAuditLog());
        $transactions = $this->createMock(TransactionManagerInterface::class);
        $transactions->expects(self::once())->method('run')
            ->willReturnCallback(static fn (Closure $callback): mixed => $callback());

        $service = new ConsentManagementService(
            $consents,
            $clients,
            $accessTokens,
            $refreshTokens,
            $authorizationCodes,
            $auditLogs,
            new IpAddress(),
            $transactions,
        );

        $service->revokeForUser(42, ' moo_login ', '127.0.0.1', 'Unit Test');
    }
}

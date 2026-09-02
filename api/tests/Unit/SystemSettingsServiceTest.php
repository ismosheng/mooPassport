<?php

declare(strict_types=1);

namespace tests\Unit;

use app\admin\repository\contract\SystemSettingsRepositoryInterface;
use app\admin\service\SystemSettingsService;
use app\common\exception\BusinessException;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\model\OAuthAuditLog;
use app\common\repository\contract\AuditLogRepositoryInterface;
use Closure;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** 覆盖系统设置白名单、范围限制、并发版本和审计安全边界。 */
final class SystemSettingsServiceTest extends TestCase
{
    public function testUnknownSettingIsRejected(): void
    {
        $settings = $this->createMock(SystemSettingsRepositoryInterface::class);
        $settings->expects(self::never())->method('findForUpdate');

        $this->expectBusinessError(
            'setting_not_allowed',
            fn () => $this->service($settings)->update(7, ['database.password' => 'secret'], []),
        );
    }

    public function testIntegerOutsideAllowedRangeIsRejected(): void
    {
        $settings = $this->createMock(SystemSettingsRepositoryInterface::class);
        $settings->expects(self::never())->method('findForUpdate');

        $this->expectBusinessError(
            'setting_out_of_range',
            fn () => $this->service($settings)->update(7, ['oauth.access_token_ttl' => 30], []),
        );
    }

    public function testConcurrentUpdateReturnsVersionConflict(): void
    {
        $settings = $this->createMock(SystemSettingsRepositoryInterface::class);
        $settings->method('findForUpdate')->willReturnCallback(static function (string $key): array {
            self::assertSame('site.name', $key);
            return ['id' => 3, 'version' => 2];
        });
        $settings->expects(self::never())->method('update');

        $this->expectBusinessError(
            'setting_version_conflict',
            fn () => $this->service($settings)->update(7, ['site.name' => '新名称'], ['site.name' => 1]),
        );
    }

    public function testSuccessfulUpdateIncrementsVersionAndWritesAudit(): void
    {
        $settings = $this->createMock(SystemSettingsRepositoryInterface::class);
        $settings->method('findForUpdate')->willReturnCallback(static function (string $key): array {
            self::assertSame('site.name', $key);
            return ['id' => 3, 'version' => 2];
        });
        $settings->expects(self::once())->method('update')->with(3, '新名称', 3, 7);
        $audit = $this->createMock(AuditLogRepositoryInterface::class);
        $audit->expects(self::once())->method('record')->willReturnCallback(static function (array $event): OAuthAuditLog {
            self::assertSame('admin.settings.updated', $event['event_type']);
            self::assertSame(7, $event['user_id']);
            self::assertSame(['keys' => ['site.name']], $event['details']);
            return new OAuthAuditLog();
        });

        $this->service($settings, $audit)->update(7, ['site.name' => '新名称'], ['site.name' => 2]);
    }

    /** @param SystemSettingsRepositoryInterface&MockObject $settings */
    private function service(
        SystemSettingsRepositoryInterface $settings,
        ?AuditLogRepositoryInterface $audit = null,
    ): SystemSettingsService {
        $transactions = $this->createStub(TransactionManagerInterface::class);
        $transactions->method('run')->willReturnCallback(static fn (Closure $callback): mixed => $callback());

        return new SystemSettingsService(
            $settings,
            $audit ?? $this->createStub(AuditLogRepositoryInterface::class),
            $transactions,
        );
    }

    private function expectBusinessError(string $errorCode, callable $operation): void
    {
        try {
            $operation();
            self::fail('预期的系统设置异常没有抛出。');
        } catch (BusinessException $exception) {
            self::assertSame($errorCode, $exception->errorCode);
        }
    }
}

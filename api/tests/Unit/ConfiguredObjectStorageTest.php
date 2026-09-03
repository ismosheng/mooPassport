<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\exception\BusinessException;
use app\common\infrastructure\storage\ConfiguredObjectStorage;
use app\common\repository\contract\SystemSettingReaderInterface;
use PHPUnit\Framework\TestCase;

/** 验证本地对象存储路径边界以及七牛缺失配置时的失败行为。 */
final class ConfiguredObjectStorageTest extends TestCase
{
    private ?string $createdFile = null;

    protected function tearDown(): void
    {
        if ($this->createdFile !== null && is_file($this->createdFile)) {
            unlink($this->createdFile);
        }
    }

    public function testLocalUploadStoresFileAndReturnsPublicUrl(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'moo-storage-');
        self::assertIsString($source);
        file_put_contents($source, 'avatar-content');
        $key = 'avatars/test/' . bin2hex(random_bytes(8)) . '.png';
        $this->createdFile = public_path('uploads/' . $key);

        try {
            $url = $this->storage(['storage.driver' => 'local'])->put($source, $key);
        } finally {
            unlink($source);
        }

        self::assertSame(rtrim((string) config('app.url'), '/') . '/uploads/' . $key, $url);
        self::assertSame('avatar-content', file_get_contents($this->createdFile));
    }

    public function testUnsafeObjectKeyIsRejected(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'moo-storage-');
        self::assertIsString($source);
        file_put_contents($source, 'avatar-content');

        try {
            $this->expectBusinessError(
                'invalid_object_key',
                fn () => $this->storage(['storage.driver' => 'local'])->put($source, '../avatar.png'),
            );
        } finally {
            unlink($source);
        }
    }

    public function testQiniuUploadRejectsIncompleteConfigurationBeforeNetworkRequest(): void
    {
        if (trim((string) config('storage.qiniu.access_key')) !== '' && trim((string) config('storage.qiniu.secret_key')) !== '') {
            self::markTestSkipped('当前测试环境已经配置七牛密钥。');
        }

        $source = tempnam(sys_get_temp_dir(), 'moo-storage-');
        self::assertIsString($source);
        file_put_contents($source, 'avatar-content');

        try {
            $this->expectBusinessError(
                'qiniu_not_configured',
                fn () => $this->storage([
                    'storage.driver' => 'qiniu',
                    'storage.qiniu.bucket' => 'moo-passport',
                    'storage.qiniu.domain' => 'https://cdn.example.com',
                ])->put($source, 'avatars/test/avatar.png'),
            );
        } finally {
            unlink($source);
        }
    }

    /** @param array<string, string> $values */
    private function storage(array $values): ConfiguredObjectStorage
    {
        $settings = $this->createStub(SystemSettingReaderInterface::class);
        $settings->method('allByKey')->willReturn(array_map(
            static fn (string $value): array => ['value' => $value, 'version' => 1],
            $values,
        ));

        return new ConfiguredObjectStorage($settings);
    }

    private function expectBusinessError(string $errorCode, callable $operation): void
    {
        try {
            $operation();
            self::fail('预期的存储异常没有抛出。');
        } catch (BusinessException $exception) {
            self::assertSame($errorCode, $exception->errorCode);
        }
    }
}

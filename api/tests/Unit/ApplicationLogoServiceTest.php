<?php

declare(strict_types=1);

namespace tests\Unit;

use app\admin\service\ApplicationLogoService;
use app\common\exception\BusinessException;
use app\common\infrastructure\storage\ObjectStorageInterface;
use PHPUnit\Framework\TestCase;
use Webman\Http\UploadFile;

/** 验证应用图标的内容检查以及与对象存储边界的协作。 */
final class ApplicationLogoServiceTest extends TestCase
{
    public function testValidPngIsStoredThroughConfiguredObjectStorage(): void
    {
        $path = $this->temporaryFile(base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true,
        ));
        $storage = $this->createMock(ObjectStorageInterface::class);
        $storage->expects(self::once())
            ->method('put')
            ->with($path, self::matchesRegularExpression('#^application-logos/\d{8}/[a-f0-9]{40}\.png$#'))
            ->willReturn('https://cdn.example.com/application-logo.png');

        try {
            $url = (new ApplicationLogoService($storage))->store(
                new UploadFile($path, 'logo.png', 'image/png', UPLOAD_ERR_OK),
            );
        } finally {
            unlink($path);
        }

        self::assertSame('https://cdn.example.com/application-logo.png', $url);
    }

    public function testFileContentMustBeAnAllowedImageType(): void
    {
        $path = $this->temporaryFile('not an image');
        $storage = $this->createMock(ObjectStorageInterface::class);
        $storage->expects(self::never())->method('put');

        try {
            (new ApplicationLogoService($storage))->store(
                new UploadFile($path, 'logo.png', 'image/png', UPLOAD_ERR_OK),
            );
            self::fail('伪造图片内容没有被拒绝。');
        } catch (BusinessException $exception) {
            self::assertSame('invalid_application_logo_type', $exception->errorCode);
        } finally {
            unlink($path);
        }
    }

    private function temporaryFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'moo-logo-');
        self::assertIsString($path);
        file_put_contents($path, $content);
        return $path;
    }
}

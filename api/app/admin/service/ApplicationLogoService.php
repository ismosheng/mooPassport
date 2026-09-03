<?php

declare(strict_types=1);

namespace app\admin\service;

use app\common\exception\BusinessException;
use app\common\infrastructure\storage\ObjectStorageInterface;
use finfo;
use Webman\Http\UploadFile;

/** 安全保存应用图标；不接受 SVG，避免上传内容在同源环境执行脚本。 */
final class ApplicationLogoService
{
    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly ObjectStorageInterface $storage)
    {
    }

    public function store(UploadFile $file): string
    {
        if (!$file->isValid() || $file->getSize() <= 0 || $file->getSize() > 2 * 1024 * 1024) {
            throw new BusinessException('invalid_application_logo', '图标文件无效或超过 2MB。', 422);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new BusinessException('invalid_application_logo_type', '仅支持 PNG、JPEG 和 WebP 图标。', 422);
        }

        $key = 'application-logos/' . gmdate('Ymd') . '/'
            . bin2hex(random_bytes(20)) . '.' . self::MIME_EXTENSIONS[$mime];
        return $this->storage->put($file->getPathname(), $key);
    }
}

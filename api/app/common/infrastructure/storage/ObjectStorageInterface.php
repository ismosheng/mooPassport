<?php

declare(strict_types=1);

namespace app\common\infrastructure\storage;

/** 定义用户上传文件的持久化边界，业务服务不感知本地或云存储 SDK。 */
interface ObjectStorageInterface
{
    public function put(string $localPath, string $objectKey): string;

    public function deleteByUrl(?string $url): void;
}

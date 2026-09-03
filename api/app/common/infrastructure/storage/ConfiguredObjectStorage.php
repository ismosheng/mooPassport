<?php

declare(strict_types=1);

namespace app\common\infrastructure\storage;

use app\common\exception\BusinessException;
use app\common\repository\contract\SystemSettingReaderInterface;
use Qiniu\Auth;
use Qiniu\Storage\BucketManager;
use Qiniu\Storage\UploadManager;
use RuntimeException;

/**
 * 根据运行时设置选择本地或七牛存储。
 *
 * 七牛 Secret Key 只从环境变量读取，不进入数据库和管理接口。
 */
final class ConfiguredObjectStorage implements ObjectStorageInterface
{
    public function __construct(private readonly SystemSettingReaderInterface $settings)
    {
    }

    public function put(string $localPath, string $objectKey): string
    {
        if (!is_readable($localPath)) {
            throw new BusinessException('upload_file_unreadable', '上传文件不存在或不可读。', 422);
        }

        $configuration = $this->configuration();
        return $configuration['driver'] === 'qiniu'
            ? $this->putToQiniu($localPath, $objectKey, $configuration)
            : $this->putToLocal($localPath, $objectKey);
    }

    public function deleteByUrl(?string $url): void
    {
        if (!is_string($url) || trim($url) === '') {
            return;
        }

        $localPrefix = rtrim((string) config('app.url'), '/') . '/uploads/';
        if (str_starts_with($url, $localPrefix)) {
            $key = $this->safeObjectKey(substr($url, strlen($localPrefix)));
            if ($key !== null) {
                $path = public_path() . '/uploads/' . str_replace('/', DIRECTORY_SEPARATOR, $key);
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            return;
        }

        $configuration = $this->configuration();
        $domain = $configuration['domain'];
        if ($domain === '' || !str_starts_with($url, $domain . '/')) {
            return;
        }

        $key = $this->safeObjectKey(substr($url, strlen($domain) + 1));
        if ($key === null || !$this->qiniuCredentialsConfigured()) {
            return;
        }

        $auth = new Auth((string) config('storage.qiniu.access_key'), (string) config('storage.qiniu.secret_key'));
        (new BucketManager($auth))->delete($configuration['bucket'], $key);
    }

    private function putToLocal(string $localPath, string $objectKey): string
    {
        $key = $this->requiredObjectKey($objectKey);
        $destination = public_path() . '/uploads/' . str_replace('/', DIRECTORY_SEPARATOR, $key);
        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('无法创建本地上传目录。');
        }
        if (!copy($localPath, $destination)) {
            throw new RuntimeException('无法保存上传文件。');
        }

        return rtrim((string) config('app.url'), '/') . '/uploads/' . $key;
    }

    /** @param array{driver:string,bucket:string,domain:string,prefix:string} $configuration */
    private function putToQiniu(string $localPath, string $objectKey, array $configuration): string
    {
        $this->assertQiniuConfigured($configuration);
        $key = $configuration['prefix'] === ''
            ? $this->requiredObjectKey($objectKey)
            : $configuration['prefix'] . '/' . $this->requiredObjectKey($objectKey);
        $auth = new Auth((string) config('storage.qiniu.access_key'), (string) config('storage.qiniu.secret_key'));
        $token = $auth->uploadToken($configuration['bucket'], $key);
        $response = array_values((new UploadManager())->putFile($token, $key, $localPath));
        $result = $response[0] ?? null;
        $error = $response[1] ?? null;
        if ($error !== null || !is_array($result)) {
            throw new BusinessException('qiniu_upload_failed', '七牛云上传失败，请检查存储配置。', 502);
        }

        return $configuration['domain'] . '/' . $key;
    }

    /** @return array{driver:string,bucket:string,domain:string,prefix:string} */
    private function configuration(): array
    {
        $values = [];
        foreach ($this->settings->allByKey() as $key => $row) {
            $values[$key] = $row['value'];
        }
        $driver = $values['storage.driver'] ?? (string) config('storage.default', 'local');
        $domain = trim($values['storage.qiniu.domain'] ?? (string) config('storage.qiniu.domain'));
        if ($domain !== '' && preg_match('#^https?://#i', $domain) !== 1) {
            $domain = 'https://' . $domain;
        }

        return [
            'driver' => $driver === 'qiniu' ? 'qiniu' : 'local',
            'bucket' => trim($values['storage.qiniu.bucket'] ?? (string) config('storage.qiniu.bucket')),
            'domain' => rtrim($domain, '/'),
            'prefix' => trim($values['storage.qiniu.prefix'] ?? (string) config('storage.qiniu.prefix'), '/'),
        ];
    }

    private function qiniuCredentialsConfigured(): bool
    {
        return trim((string) config('storage.qiniu.access_key')) !== ''
            && trim((string) config('storage.qiniu.secret_key')) !== '';
    }

    /** @param array{driver:string,bucket:string,domain:string,prefix:string} $configuration */
    private function assertQiniuConfigured(array $configuration): void
    {
        if (!$this->qiniuCredentialsConfigured() || $configuration['bucket'] === '' || $configuration['domain'] === '') {
            throw new BusinessException(
                'qiniu_not_configured',
                '七牛云配置不完整，请填写 Bucket、访问域名，并在 .env 配置 Access Key 和 Secret Key。',
                422,
            );
        }
    }

    private function requiredObjectKey(string $key): string
    {
        return $this->safeObjectKey($key)
            ?? throw new BusinessException('invalid_object_key', '上传文件路径无效。', 422);
    }

    private function safeObjectKey(string $key): ?string
    {
        $key = str_replace('\\', '/', ltrim($key, '/'));
        if ($key === '' || str_contains($key, '../') || preg_match('#^[A-Za-z0-9/_-]+\.[A-Za-z0-9]+$#', $key) !== 1) {
            return null;
        }
        return $key;
    }
}

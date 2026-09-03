<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\exception\BusinessException;
use app\common\infrastructure\storage\ObjectStorageInterface;
use app\common\model\User;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\support\IpAddress;
use finfo;
use Throwable;
use Webman\Http\UploadFile;

/** 安全保存当前用户头像并更新资料；不接受可能执行同源脚本的 SVG。 */
final class ProfileAvatarService
{
    /** @var array<string, string> */
    private const MIME_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly IpAddress $ipAddress,
        private readonly ObjectStorageInterface $storage,
    ) {
    }

    public function store(User $user, UploadFile $file, ?string $requestIp): User
    {
        if (!$file->isValid() || $file->getSize() <= 0 || $file->getSize() > 2 * 1024 * 1024) {
            throw new BusinessException('invalid_profile_avatar', '头像文件无效或超过 2MB。', 422);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        if (!is_string($mime) || !isset(self::MIME_EXTENSIONS[$mime])) {
            throw new BusinessException('invalid_profile_avatar_type', '仅支持 PNG、JPEG 和 WebP 头像。', 422);
        }

        $key = 'avatars/' . gmdate('Ymd') . '/' . bin2hex(random_bytes(20)) . '.' . self::MIME_EXTENSIONS[$mime];
        $oldAvatarUrl = $user->avatar_url;
        $newAvatarUrl = $this->storage->put($file->getPathname(), $key);

        $user->avatar_url = $newAvatarUrl;
        try {
            $this->users->save($user);
        } catch (Throwable $exception) {
            $user->avatar_url = $oldAvatarUrl;
            try {
                $this->storage->deleteByUrl($newAvatarUrl);
            } catch (Throwable) {
                // 数据库异常是主错误；新文件清理失败交由存储生命周期规则兜底。
            }
            throw $exception;
        }

        $this->auditLogs->record([
            'event_type' => 'user.profile.avatar_updated',
            'user_id' => $user->id,
            'ip_address' => $this->ipAddress->toBinary($requestIp),
            'success' => true,
        ]);
        try {
            $this->storage->deleteByUrl($oldAvatarUrl);
        } catch (Throwable) {
            // 用户头像已经更新，旧对象删除失败不应回滚成功操作。
        }
        return $user;
    }
}

<?php

declare(strict_types=1);

namespace app\admin\repository\contract;

use app\common\repository\contract\SystemSettingReaderInterface;

/** 定义后台系统设置的持久化边界，白名单与值校验由 Service 负责。 */
interface SystemSettingsRepositoryInterface extends SystemSettingReaderInterface
{
    /** @return array{id:int,version:int}|null */
    public function findForUpdate(string $key): ?array;

    public function create(string $key, string $type, string $value, string $description, int $actorUserId): void;

    public function update(int $id, string $value, int $version, int $actorUserId): void;
}

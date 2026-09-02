<?php

declare(strict_types=1);

namespace app\admin\repository\contract;

/** 定义后台系统设置的持久化边界，白名单与值校验由 Service 负责。 */
interface SystemSettingsRepositoryInterface
{
    /** @return array<string, array{value:string,version:int}> */
    public function allByKey(): array;

    /** @return array{id:int,version:int}|null */
    public function findForUpdate(string $key): ?array;

    public function create(string $key, string $type, string $value, string $description, int $actorUserId): void;

    public function update(int $id, string $value, int $version, int $actorUserId): void;
}

<?php

declare(strict_types=1);

namespace app\admin\repository\eloquent;

use app\admin\repository\contract\SystemSettingsRepositoryInterface;
use support\Db;

/** 使用 MySQL 保存白名单系统设置，并提供行锁支持并发版本校验。 */
final class SystemSettingsRepository implements SystemSettingsRepositoryInterface
{
    public function allByKey(): array
    {
        $result = [];
        foreach (Db::table('moo_system_settings')->get(['setting_key', 'setting_value', 'version']) as $row) {
            $result[(string) $row->setting_key] = [
                'value' => (string) $row->setting_value,
                'version' => (int) $row->version,
            ];
        }

        return $result;
    }

    public function findForUpdate(string $key): ?array
    {
        $row = Db::table('moo_system_settings')
            ->where('setting_key', $key)
            ->lockForUpdate()
            ->first(['id', 'version']);

        return $row === null ? null : ['id' => (int) $row->id, 'version' => (int) $row->version];
    }

    public function create(string $key, string $type, string $value, string $description, int $actorUserId): void
    {
        Db::table('moo_system_settings')->insert([
            'setting_key' => $key,
            'value_type' => $type,
            'setting_value' => $value,
            'description' => $description,
            'version' => 1,
            'updated_by_user_id' => $actorUserId,
        ]);
    }

    public function update(int $id, string $value, int $version, int $actorUserId): void
    {
        Db::table('moo_system_settings')->where('id', $id)->update([
            'setting_value' => $value,
            'version' => $version,
            'updated_by_user_id' => $actorUserId,
        ]);
    }
}

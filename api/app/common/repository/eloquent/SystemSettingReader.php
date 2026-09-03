<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\repository\contract\SystemSettingReaderInterface;
use support\Db;

/** 只读加载可公开的运行时系统设置，不负责白名单校验或后台写入。 */
final class SystemSettingReader implements SystemSettingReaderInterface
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
}

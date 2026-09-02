<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use support\App;
use support\Db;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv::createUnsafeImmutable($root)->safeLoad();
App::loadAllConfig();

$schema = Db::connection()->getSchemaBuilder();
if (!$schema->hasColumn('moo_roles', 'is_system')) {
    Db::statement("ALTER TABLE `moo_roles` ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否系统内置角色；内置角色禁止删除' AFTER `description`");
}
if (!$schema->hasColumn('moo_roles', 'status')) {
    Db::statement("ALTER TABLE `moo_roles` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT '角色状态：active 或 disabled' AFTER `is_system`");
    Db::statement('ALTER TABLE `moo_roles` ADD KEY `idx_moo_roles_status` (`status`)');
}
if (!$schema->hasColumn('moo_roles', 'version')) {
    Db::statement("ALTER TABLE `moo_roles` ADD COLUMN `version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '乐观锁版本号，防止并发编辑覆盖' AFTER `status`");
}

Db::table('moo_roles')->where('code', 'super_admin')->update(['is_system' => 1, 'status' => 'active']);
fwrite(STDOUT, "角色扩展字段已就绪。\n");

<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use support\App;
use support\Db;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv::createUnsafeImmutable($root)->safeLoad();
App::loadAllConfig();
$database = (string) config('database.connections.mysql.database');

$hasColumn = Db::table('information_schema.COLUMNS')
    ->where('TABLE_SCHEMA', $database)
    ->where('TABLE_NAME', 'moo_applications')
    ->where('COLUMN_NAME', 'logo_url')
    ->exists();

if (!$hasColumn) {
    Db::connection()->statement("ALTER TABLE `moo_applications` ADD COLUMN `logo_url` VARCHAR(500) NULL COMMENT '应用图标地址' AFTER `description`");
}

// 兼容升级前已直接在 OAuth 客户端保存图标的应用，只回填逻辑应用尚未设置的值。
Db::connection()->statement(
    'UPDATE `moo_applications` a '
    . 'JOIN `moo_oauth_clients` c ON c.`application_id` = a.`id` AND c.`logo_url` IS NOT NULL '
    . 'SET a.`logo_url` = c.`logo_url` WHERE a.`logo_url` IS NULL'
);

fwrite(STDOUT, "应用图标字段已就绪。\n");

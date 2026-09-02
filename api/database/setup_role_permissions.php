<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use support\App;
use support\Db;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv::createUnsafeImmutable($root)->safeLoad();
App::loadAllConfig();
$sql = file_get_contents(__DIR__ . '/migrations/20260902_002_create_role_permissions.sql');
if (!is_string($sql)) {
    fwrite(STDERR, "读取角色权限迁移失败。\n");
    exit(1);
}

foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    Db::connection()->unprepared($statement);
}

fwrite(STDOUT, "角色权限表和默认权限点已就绪。\n");

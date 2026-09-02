<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use support\App;
use support\Db;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv::createUnsafeImmutable($root)->safeLoad();
App::loadAllConfig();
$sql = file_get_contents(__DIR__ . '/migrations/20260901_002_add_service_scope.sql');
if (!is_string($sql)) {
    fwrite(STDERR, "读取服务端 API Scope 迁移失败。\n");
    exit(1);
}

Db::connection()->unprepared($sql);
fwrite(STDOUT, "服务端 API Scope 已就绪。\n");

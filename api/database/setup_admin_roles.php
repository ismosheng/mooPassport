<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use support\App;
use support\Db;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv::createUnsafeImmutable($root)->safeLoad();
App::loadAllConfig();

$migration = file_get_contents(__DIR__ . '/migrations/20260901_001_create_admin_roles.sql');
if (!is_string($migration)) {
    fwrite(STDERR, "读取管理员角色迁移失败。\n");
    exit(1);
}

foreach (array_filter(array_map('trim', explode(';', $migration))) as $statement) {
    Db::connection()->unprepared($statement);
}

$identifier = trim((string) ($argv[1] ?? ''));
if ($identifier === '') {
    fwrite(STDOUT, "管理员角色表已就绪。\n");
    fwrite(STDOUT, "如需授权，请执行：php database/setup_admin_roles.php 用户名或邮箱\n");
    exit(0);
}

$user = Db::table('moo_users')
    ->whereNull('deleted_at')
    ->where(static function ($query) use ($identifier): void {
        $query->where('username', $identifier)->orWhere('email', $identifier);
    })
    ->first();
if ($user === null) {
    fwrite(STDERR, "未找到指定用户。\n");
    exit(1);
}

$roleId = Db::table('moo_roles')->where('code', 'super_admin')->value('id');
Db::table('moo_user_roles')->updateOrInsert(
    ['user_id' => (int) $user->id, 'role_id' => (int) $roleId],
    ['granted_by_user_id' => null, 'created_at' => new DateTimeImmutable('now')],
);

fwrite(STDOUT, "超级管理员权限已授予：{$identifier}\n");
fwrite(STDOUT, "请重新登录或刷新页面。\n");

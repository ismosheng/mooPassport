<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use support\App;
use support\Db;
use Symfony\Component\Uid\Ulid;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv::createUnsafeImmutable($root)->safeLoad();
App::loadAllConfig();
$database = (string) config('database.connections.mysql.database');

$createTable = file_get_contents(__DIR__ . '/migrations/20260901_003_create_applications.sql');
if (!is_string($createTable)) {
    fwrite(STDERR, "读取逻辑应用迁移失败。\n");
    exit(1);
}
$createTable = strstr($createTable, 'ALTER TABLE', true);
Db::connection()->unprepared((string) $createTable);

$hasColumn = Db::table('information_schema.COLUMNS')
    ->where('TABLE_SCHEMA', $database)
    ->where('TABLE_NAME', 'moo_oauth_clients')
    ->where('COLUMN_NAME', 'application_id')
    ->exists();
if (!$hasColumn) {
    Db::connection()->statement("ALTER TABLE `moo_oauth_clients` ADD COLUMN `application_id` BIGINT UNSIGNED NULL COMMENT '所属逻辑应用' AFTER `id`");
}

// 历史客户端一对一回填为逻辑应用，保证升级后列表不丢失已有接入配置。
Db::connection()->transaction(function (): void {
    $legacyClients = Db::table('moo_oauth_clients')->whereNull('application_id')->orderBy('id')->get();
    foreach ($legacyClients as $client) {
        if ($client->owner_user_id === null) {
            continue;
        }
        $applicationId = Db::table('moo_applications')->insertGetId([
            'public_id' => (string) new Ulid(),
            'owner_user_id' => $client->owner_user_id,
            'name' => $client->name,
            'description' => $client->description,
            'status' => $client->status,
            'created_at' => $client->created_at,
            'updated_at' => $client->updated_at,
        ]);
        Db::table('moo_oauth_clients')->where('id', $client->id)->update(['application_id' => $applicationId]);
    }
});

$hasIndex = Db::table('information_schema.STATISTICS')
    ->where('TABLE_SCHEMA', $database)->where('TABLE_NAME', 'moo_oauth_clients')
    ->where('INDEX_NAME', 'idx_moo_oauth_clients_application')->exists();
if (!$hasIndex) {
    Db::connection()->statement('ALTER TABLE `moo_oauth_clients` ADD KEY `idx_moo_oauth_clients_application` (`application_id`)');
}
$hasForeignKey = Db::table('information_schema.TABLE_CONSTRAINTS')
    ->where('CONSTRAINT_SCHEMA', $database)->where('TABLE_NAME', 'moo_oauth_clients')
    ->where('CONSTRAINT_NAME', 'fk_moo_oauth_clients_application')->exists();
if (!$hasForeignKey) {
    Db::connection()->statement('ALTER TABLE `moo_oauth_clients` ADD CONSTRAINT `fk_moo_oauth_clients_application` FOREIGN KEY (`application_id`) REFERENCES `moo_applications` (`id`) ON DELETE CASCADE');
}

fwrite(STDOUT, "逻辑应用结构已就绪，历史客户端已完成回填。\n");

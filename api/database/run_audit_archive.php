<?php

declare(strict_types=1);

use app\common\service\AuditArchiveService;
use Dotenv\Dotenv;
use support\App;
use support\Container;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
Dotenv::createUnsafeImmutable($root)->safeLoad();
App::loadAllConfig();
/** @var AuditArchiveService $service */
$service = Container::get(AuditArchiveService::class);
$result = $service->run(
    (int) config('audit.hot_retention_days'),
    (int) config('audit.archive_batch_size'),
    (int) config('audit.archive_max_batches'),
    (int) config('audit.cold_retention_days'),
    (bool) config('audit.archive_delete_enabled'),
);
fwrite(STDOUT, sprintf("归档完成：%d 批，%d 行，清理 %d 个过期月表。\n", $result['batches'], $result['rows'], $result['dropped_archives']));

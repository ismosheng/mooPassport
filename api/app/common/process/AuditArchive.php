<?php

declare(strict_types=1);

namespace app\common\process;

use app\common\service\AuditArchiveService;
use support\Container;
use support\Log;
use Throwable;
use Workerman\Timer;
use Workerman\Worker;

/** 定时触发安全审计分批归档；失败只记录告警，不终止其他 Webman 进程。 */
final class AuditArchive
{
    public function onWorkerStart(Worker $worker): void
    {
        $run = function (): void {
            try {
                $result = Container::get(AuditArchiveService::class)->run((int) config('audit.hot_retention_days'), (int) config('audit.archive_batch_size'), (int) config('audit.archive_max_batches'), (int) config('audit.cold_retention_days'), (bool) config('audit.archive_delete_enabled'));
                if ($result['rows'] > 0 || $result['dropped_archives'] > 0) Log::info('安全审计归档完成', $result);
            } catch (Throwable $exception) {
                // 不记录 SQL 或数据内容，避免日志再次泄露审计详情。
                Log::error('安全审计归档失败', ['exception' => $exception::class, 'message' => $exception->getMessage()]);
            }
        };
        Timer::add(15, $run, [], false);
        Timer::add((int) config('audit.archive_interval'), $run);
    }
}

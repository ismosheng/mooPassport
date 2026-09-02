<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\repository\contract\AuditArchiveRepositoryInterface;
use DateTimeImmutable;
use RuntimeException;
use support\Db;

/** 以“复制、校验、删除”顺序归档审计数据，失败时保留热表原记录。 */
final class AuditArchiveRepository implements AuditArchiveRepositoryInterface
{
    public function acquireLock(): bool
    {
        return (int) (Db::connection()->selectOne("SELECT GET_LOCK('moo_audit_archive', 0) AS acquired")->acquired ?? 0) === 1;
    }

    public function releaseLock(): void
    {
        Db::connection()->selectOne("SELECT RELEASE_LOCK('moo_audit_archive')");
    }

    public function archiveBatch(DateTimeImmutable $cutoff, int $batchSize): ?array
    {
        $first = Db::table('moo_oauth_audit_logs')->where('created_at', '<', $cutoff)->orderBy('id')->first(['id', 'created_at']);
        if ($first === null) return null;
        $month = substr((string) $first->created_at, 0, 7);
        $monthStart = new DateTimeImmutable($month . '-01 00:00:00');
        $monthEnd = $monthStart->modify('+1 month');
        $ids = Db::table('moo_oauth_audit_logs')->where('created_at', '>=', $monthStart)->where('created_at', '<', $monthEnd)->where('created_at', '<', $cutoff)->orderBy('id')->limit($batchSize)->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        if ($ids === []) return null;
        $suffix = str_replace('-', '', $month);
        if (!preg_match('/^\d{6}$/', $suffix)) throw new RuntimeException('审计归档月份无效。');
        $archiveTable = 'moo_oauth_audit_logs_' . $suffix;
        Db::statement("CREATE TABLE IF NOT EXISTS `{$archiveTable}` LIKE `moo_oauth_audit_logs`");

        return Db::connection()->transaction(function () use ($archiveTable, $ids, $suffix): array {
            $runId = Db::table('moo_audit_archive_runs')->insertGetId(['archive_month' => $suffix, 'status' => 'running', 'row_count' => 0, 'started_at' => new DateTimeImmutable()]);
            $idList = implode(',', array_map('intval', $ids));
            Db::statement("INSERT IGNORE INTO `{$archiveTable}` SELECT * FROM `moo_oauth_audit_logs` WHERE `id` IN ({$idList})");
            $archived = Db::table($archiveTable)->whereIn('id', $ids)->count();
            if ($archived !== count($ids)) throw new RuntimeException('审计归档校验失败，热数据未删除。');
            $deleted = Db::table('moo_oauth_audit_logs')->whereIn('id', $ids)->delete();
            if ($deleted !== count($ids)) throw new RuntimeException('审计热数据清理数量不一致。');
            Db::table('moo_audit_archive_runs')->where('id', $runId)->update(['status' => 'completed', 'row_count' => $deleted, 'finished_at' => new DateTimeImmutable()]);
            return ['month' => $suffix, 'row_count' => $deleted];
        });
    }

    public function purgeBefore(DateTimeImmutable $cutoff): array
    {
        $database = (string) config('database.connections.mysql.database');
        $tables = Db::table('information_schema.tables')->where('table_schema', $database)->where('table_name', 'like', 'moo_oauth_audit_logs_______')->pluck('table_name')->all();
        $dropped = [];
        foreach ($tables as $table) {
            $table = (string) $table;
            if (!preg_match('/^moo_oauth_audit_logs_(\d{6})$/', $table, $matches)) continue;
            $monthEnd = (new DateTimeImmutable(substr($matches[1], 0, 4) . '-' . substr($matches[1], 4, 2) . '-01 00:00:00'))->modify('+1 month');
            if ($monthEnd >= $cutoff) continue;
            Db::statement("DROP TABLE `{$table}`");
            $dropped[] = $table;
        }
        return $dropped;
    }
}

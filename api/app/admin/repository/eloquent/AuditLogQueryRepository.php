<?php

declare(strict_types=1);

namespace app\admin\repository\eloquent;

use app\admin\repository\contract\AuditLogQueryRepositoryInterface;
use DateTimeImmutable;
use support\Db;

/** 使用数据库联表查询安全审计，仅返回展示所需字段。 */
final class AuditLogQueryRepository implements AuditLogQueryRepositoryInterface
{
    public function search(string $keyword, ?string $eventType, ?bool $success, ?DateTimeImmutable $startedAt, ?DateTimeImmutable $endedAt, int $page, int $perPage): array
    {
        $tables = $this->queryTables($startedAt, $endedAt);
        $queries = array_map(fn (string $table) => $this->tableQuery($table, $keyword, $eventType, $success, $startedAt, $endedAt), $tables);
        $union = array_shift($queries);
        foreach ($queries as $query) $union->unionAll($query);
        $combined = Db::connection()->query()->fromSub($union, 'audit_entries');
        $total = (clone $combined)->count();
        $eventTypes = (clone $combined)->distinct()->orderBy('event_type')->pluck('event_type')->map(static fn ($type): string => (string) $type)->all();
        $items = $combined->orderByDesc('created_at')->orderByDesc('source_id')->forPage($page, $perPage)->get()->all();
        return ['items' => array_values($items), 'total' => $total, 'event_types' => array_values($eventTypes)];
    }

    /** @return list<string> */
    private function queryTables(?DateTimeImmutable $startedAt, ?DateTimeImmutable $endedAt): array
    {
        $candidates = [];
        if ($startedAt !== null && $endedAt !== null) {
            $cursor = $startedAt->modify('first day of this month')->setTime(0, 0);
            $last = $endedAt->modify('first day of this month')->setTime(0, 0);
            while ($cursor <= $last) {
                $candidates[] = 'moo_oauth_audit_logs_' . $cursor->format('Ym');
                $cursor = $cursor->modify('+1 month');
            }
        }
        $existing = $candidates === [] ? [] : Db::table('information_schema.tables')
            ->where('table_schema', (string) config('database.connections.mysql.database'))
            ->whereIn('table_name', $candidates)->pluck('table_name')->map(static fn ($table): string => (string) $table)->all();
        return ['moo_oauth_audit_logs', ...array_values($existing)];
    }

    private function tableQuery(string $table, string $keyword, ?string $eventType, ?bool $success, ?DateTimeImmutable $startedAt, ?DateTimeImmutable $endedAt): object
    {
        // 表名只可能来自固定热表或 information_schema 返回且经过候选白名单的月表。
        $query = Db::table($table . ' as audit')
            ->leftJoin('moo_users as users', 'users.id', '=', 'audit.user_id')
            ->leftJoin('moo_oauth_clients as clients', 'clients.id', '=', 'audit.client_id');
        if ($keyword !== '') {
            $query->where(function ($query) use ($keyword): void {
                $query->where('audit.event_type', 'like', '%' . $keyword . '%')
                    ->orWhere('audit.request_id', 'like', '%' . $keyword . '%')
                    ->orWhere('users.username', 'like', '%' . $keyword . '%')
                    ->orWhere('users.email', 'like', '%' . $keyword . '%')
                    ->orWhere('clients.client_id', 'like', '%' . $keyword . '%')
                    ->orWhere('clients.name', 'like', '%' . $keyword . '%');
            });
        }
        if ($eventType !== null) $query->where('audit.event_type', $eventType);
        if ($success !== null) $query->where('audit.success', $success);
        if ($startedAt !== null) $query->where('audit.created_at', '>=', $startedAt);
        if ($endedAt !== null) $query->where('audit.created_at', '<=', $endedAt);
        return $query->select([
            'audit.event_type', 'audit.success', 'audit.request_id', 'audit.user_agent', 'audit.details', 'audit.created_at',
            'users.public_id as user_public_id', 'users.username', 'users.email',
            'clients.client_id as oauth_client_id', 'clients.name as client_name',
        ])->selectRaw('audit.id as source_id, INET6_NTOA(audit.ip_address) as ip_address, ? as storage', [$table === 'moo_oauth_audit_logs' ? 'hot' : 'archive']);
    }
}

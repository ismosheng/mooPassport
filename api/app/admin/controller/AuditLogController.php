<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\middleware\RequirePermission;
use app\admin\service\AuditLogQueryService;
use app\common\support\ApiResponse;
use DateTimeInterface;
use support\annotation\Middleware;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;
use app\passport\middleware\AuthenticateSession;

/** 提供不可变安全审计的只读查询接口，不提供更新和删除能力。 */
#[DisableDefaultRoute]
#[RouteGroup('/admin/v1/audit-logs')]
#[Middleware(AuthenticateSession::class, RequirePermission::class)]
final class AuditLogController
{
    public function __construct(private readonly AuditLogQueryService $query) {}

    #[Get('', 'admin.v1.audit_logs.list')]
    public function list(Request $request): Response
    {
        $successValue = (string) $request->get('success', '');
        $success = $successValue === '' ? null : $successValue === '1';
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(100, max(10, (int) $request->get('per_page', 20)));
        $result = $this->query->search((string) $request->get('keyword', ''), ($event = trim((string) $request->get('event_type', ''))) === '' ? null : $event, $success, ($start = trim((string) $request->get('started_on', ''))) === '' ? null : $start, ($end = trim((string) $request->get('ended_on', ''))) === '' ? null : $end, $page, $perPage);
        return ApiResponse::success($request, [
            'items' => array_map(fn (object $row): array => $this->serialize($row), $result['items']),
            'total' => $result['total'], 'event_types' => $result['event_types'], 'page' => $page, 'per_page' => $perPage,
        ]);
    }

    /** @return array<string,mixed> */
    private function serialize(object $row): array
    {
        $details = is_string($row->details) ? json_decode($row->details, true) : $row->details;
        return [
            'event_type' => (string) $row->event_type, 'success' => (bool) $row->success,
            'request_id' => $row->request_id, 'ip_address' => $row->ip_address, 'user_agent' => $row->user_agent,
            'user' => $row->user_public_id ? ['id' => $row->user_public_id, 'username' => $row->username, 'email' => $row->email] : null,
            'client' => $row->oauth_client_id ? ['client_id' => $row->oauth_client_id, 'name' => $row->client_name] : null,
            'details' => is_array($details) ? $details : [],
            'storage' => (string) $row->storage,
            'created_at' => $row->created_at instanceof DateTimeInterface ? $row->created_at->format(DATE_ATOM) : (string) $row->created_at . '+08:00',
        ];
    }
}

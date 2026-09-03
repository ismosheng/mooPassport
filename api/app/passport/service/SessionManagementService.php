<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\exception\BusinessException;
use app\common\model\UserSession;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\UserSessionRepositoryInterface;
use app\common\support\IpAddress;
use app\passport\dto\AuthenticatedSession;
use DateTimeImmutable;
use DateTimeZone;

/**
 * 管理当前用户的浏览器登录会话列表与撤销。
 *
 * 只允许操作用户自己的会话；撤销当前会话后调用方必须清除认证 Cookie。
 */
final class SessionManagementService
{
    public function __construct(
        private readonly UserSessionRepositoryInterface $sessions,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly IpAddress $ipAddress,
    ) {
    }

    /**
     * @return array{items:list<array{
     *     id: string,
     *     is_current: bool,
     *     ip_address: ?string,
     *     user_agent: ?string,
     *     last_seen_at: ?string,
     *     created_at: ?string,
     *     expires_at: ?string
     * }>,total:int,page:int,per_page:int}
     */
    public function list(AuthenticatedSession $identity, int $page, int $perPage): array
    {
        $now = $this->now();
        $items = [];
        $result = $this->sessions->paginateActiveForUser($identity->user->id, $now, $page, $perPage);
        foreach ($result['items'] as $session) {
            $items[] = $this->serialize($session, $session->id === $identity->session->id);
        }

        return ['items' => $items, 'total' => $result['total'], 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * 撤销指定会话。返回 true 表示撤销的是当前请求会话，调用方需清除 Cookie。
     */
    public function revoke(
        AuthenticatedSession $identity,
        int $sessionId,
        ?string $requestIp,
        ?string $userAgent,
    ): bool {
        $now = $this->now();
        $session = $this->sessions->findActiveByIdForUser($sessionId, $identity->user->id, $now);
        if ($session === null) {
            throw new BusinessException('session_not_found', '登录会话不存在或已失效。', 404);
        }

        $revoked = $this->sessions->revoke($session->id, $now);
        $isCurrent = $session->id === $identity->session->id;
        $this->auditLogs->record([
            'event_type' => $isCurrent ? 'user.session.revoked_current' : 'user.session.revoked',
            'user_id' => $identity->user->id,
            'ip_address' => $this->ipAddress->toBinary($requestIp),
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 500),
            'success' => $revoked,
            'details' => ['session_id' => $session->id, 'is_current' => $isCurrent],
        ]);

        return $isCurrent;
    }

    /** 撤销除当前设备外的全部有效会话。 */
    public function revokeOthers(
        AuthenticatedSession $identity,
        ?string $requestIp,
        ?string $userAgent,
    ): int {
        $count = $this->sessions->revokeOthersForUser(
            $identity->user->id,
            $identity->session->id,
            $this->now(),
        );
        $this->auditLogs->record([
            'event_type' => 'user.session.revoked_others',
            'user_id' => $identity->user->id,
            'ip_address' => $this->ipAddress->toBinary($requestIp),
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 500),
            'success' => true,
            'details' => ['revoked_count' => $count],
        ]);

        return $count;
    }

    /**
     * @return array{
     *     id: string,
     *     is_current: bool,
     *     ip_address: ?string,
     *     user_agent: ?string,
     *     last_seen_at: ?string,
     *     created_at: ?string,
     *     expires_at: ?string
     * }
     */
    private function serialize(UserSession $session, bool $isCurrent): array
    {
        return [
            'id' => (string) $session->id,
            'is_current' => $isCurrent,
            'ip_address' => $this->ipAddress->toString($session->ip_address),
            'user_agent' => $session->user_agent,
            'last_seen_at' => $session->last_seen_at?->format(DATE_ATOM),
            'created_at' => $session->created_at?->format(DATE_ATOM),
            'expires_at' => $session->expires_at?->format(DATE_ATOM),
        ];
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
    }
}

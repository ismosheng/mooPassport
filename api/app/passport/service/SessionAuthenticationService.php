<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\enum\UserStatus;
use app\common\exception\BusinessException;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\repository\contract\UserSessionRepositoryInterface;
use app\common\support\IpAddress;
use app\common\support\SecureToken;
use app\passport\dto\AuthenticatedSession;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/** 使用 Cookie 令牌哈希恢复或撤销服务端浏览器会话。 */
final class SessionAuthenticationService
{
    private const TOUCH_INTERVAL_SECONDS = 300;

    public function __construct(
        private readonly UserSessionRepositoryInterface $sessions,
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly SecureToken $secureToken,
        private readonly IpAddress $ipAddress,
    ) {
    }

    public function authenticate(?string $rawSessionToken): AuthenticatedSession
    {
        if ($rawSessionToken === null || $rawSessionToken === '') {
            throw $this->unauthorized();
        }

        $now = $this->now();
        $session = $this->sessions->findActiveByHash($this->secureToken->hash($rawSessionToken), $now);
        if ($session === null) {
            throw $this->unauthorized();
        }

        $user = $this->users->findById($session->user_id);
        if ($user === null || $user->status !== UserStatus::Active) {
            throw $this->unauthorized();
        }

        $touchBoundary = $now->sub(new DateInterval('PT' . self::TOUCH_INTERVAL_SECONDS . 'S'));
        if ($session->last_seen_at === null || $session->last_seen_at < $touchBoundary) {
            $this->sessions->touch($session->id, $now);
            $session->last_seen_at = $now;
        }

        return new AuthenticatedSession($user, $session);
    }

    public function logout(
        AuthenticatedSession $identity,
        ?string $ipAddress,
        ?string $userAgent,
    ): void {
        $revoked = $this->sessions->revoke($identity->session->id, $this->now());
        $this->auditLogs->record([
            'event_type' => 'user.logout',
            'user_id' => $identity->user->id,
            'ip_address' => $this->ipAddress->toBinary($ipAddress),
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 500),
            'success' => $revoked,
        ]);
    }

    private function unauthorized(): BusinessException
    {
        return new BusinessException('unauthenticated', '请先登录。', 401);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

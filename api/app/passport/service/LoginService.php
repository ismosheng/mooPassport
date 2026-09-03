<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\enum\UserStatus;
use app\common\exception\BusinessException;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\model\User;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\LoginAttemptRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\repository\contract\UserSessionRepositoryInterface;
use app\common\support\IpAddress;
use app\common\support\PasswordHasher;
use app\common\support\SecureToken;
use app\passport\dto\LoginInput;
use app\passport\dto\LoginResult;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

/**
 * 验证本地账号，并创建仅持久化令牌哈希的浏览器会话。
 *
 * 所有凭据失败统一返回相同的公开错误，防止调用方枚举账号及账号状态。
 */
final class LoginService
{
    private const FAILURE_WINDOW_SECONDS = 900;
    private const MAX_IDENTIFIER_FAILURES = 5;
    private const MAX_IP_FAILURES = 20;
    private const SESSION_LIFETIME_SECONDS = 604800;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserSessionRepositoryInterface $sessions,
        private readonly LoginAttemptRepositoryInterface $loginAttempts,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly PasswordHasher $passwordHasher,
        private readonly SecureToken $secureToken,
        private readonly IpAddress $ipAddress,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function login(LoginInput $input): LoginResult
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
        $identifier = mb_strtolower(trim($input->identifier));
        $identifierHash = hash('sha256', $identifier, true);
        $binaryIp = $this->ipAddress->toBinary($input->ipAddress);
        $since = $now->sub(new DateInterval('PT' . self::FAILURE_WINDOW_SECONDS . 'S'));

        if (
            $this->loginAttempts->countRecentFailuresByIdentifier($identifierHash, $since)
            >= self::MAX_IDENTIFIER_FAILURES
            || ($binaryIp !== null
                && $this->loginAttempts->countRecentFailuresByIp($binaryIp, $since) >= self::MAX_IP_FAILURES)
        ) {
            $this->recordFailure(null, $identifierHash, $binaryIp, $input, 'rate_limited', $now);
            throw new BusinessException('login_rate_limited', '登录尝试过多，请稍后重试。', 429);
        }

        $user = $this->users->findByUsernameOrEmail($identifier);
        $passwordValid = $user !== null
            && $this->passwordHasher->verify($input->password, (string) $user->password_hash);

        if (!$passwordValid || $user->status !== UserStatus::Active) {
            $reason = $user === null ? 'invalid_credentials' : 'account_not_active';
            $this->recordFailure($user, $identifierHash, $binaryIp, $input, $reason, $now);
            throw new BusinessException('invalid_credentials', '账号或密码错误。', 401);
        }

        $sessionToken = $this->secureToken->generate();
        $expiresAt = $now->add(new DateInterval('PT' . self::SESSION_LIFETIME_SECONDS . 'S'));

        /** @var LoginResult $result */
        $result = $this->transactions->run(function () use (
            $user,
            $input,
            $identifierHash,
            $binaryIp,
            $now,
            $sessionToken,
            $expiresAt,
        ): LoginResult {
            if ($this->passwordHasher->needsRehash((string) $user->password_hash)) {
                $user->password_hash = $this->passwordHasher->hash($input->password);
            }
            $user->last_login_at = $now;
            $this->users->save($user);

            $this->sessions->create([
                'session_hash' => $this->secureToken->hash($sessionToken),
                'user_id' => $user->id,
                'ip_address' => $binaryIp,
                'user_agent' => $this->truncateUserAgent($input->userAgent),
                'last_seen_at' => $now,
                'expires_at' => $expiresAt,
            ]);

            $this->loginAttempts->record([
                'user_id' => $user->id,
                'login_identifier_hash' => $identifierHash,
                'ip_address' => $binaryIp,
                'user_agent' => $this->truncateUserAgent($input->userAgent),
                'succeeded' => true,
                'created_at' => $now,
            ]);

            $this->auditLogs->record([
                'event_type' => 'user.login.succeeded',
                'user_id' => $user->id,
                'ip_address' => $binaryIp,
                'user_agent' => $this->truncateUserAgent($input->userAgent),
                'request_id' => $input->requestId,
                'success' => true,
                'created_at' => $now,
            ]);

            return new LoginResult($user, $sessionToken, $expiresAt);
        });

        return $result;
    }

    private function recordFailure(
        ?User $user,
        string $identifierHash,
        ?string $binaryIp,
        LoginInput $input,
        string $reason,
        DateTimeImmutable $now,
    ): void {
        $this->loginAttempts->record([
            'user_id' => $user?->id,
            'login_identifier_hash' => $identifierHash,
            'ip_address' => $binaryIp,
            'user_agent' => $this->truncateUserAgent($input->userAgent),
            'succeeded' => false,
            'failure_reason' => $reason,
            'created_at' => $now,
        ]);

        $this->auditLogs->record([
            'event_type' => 'user.login.failed',
            'user_id' => $user?->id,
            'ip_address' => $binaryIp,
            'user_agent' => $this->truncateUserAgent($input->userAgent),
            'request_id' => $input->requestId,
            'success' => false,
            'details' => ['reason' => $reason],
            'created_at' => $now,
        ]);
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        return $userAgent === null ? null : mb_substr($userAgent, 0, 500);
    }
}

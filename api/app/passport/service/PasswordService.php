<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\enum\UserStatus;
use app\common\exception\BusinessException;
use app\common\infrastructure\mail\MailSenderInterface;
use app\common\model\User;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\PasswordResetTokenRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\repository\contract\UserSessionRepositoryInterface;
use app\common\support\IpAddress;
use app\common\support\PasswordHasher;
use app\common\support\SecureToken;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use support\Db;

/** 处理密码重置与修改，并撤销密码变更前签发的全部会话和令牌。 */
final class PasswordService
{
    private const RESET_LIFETIME_MINUTES = 30;
    private const MINIMUM_REQUEST_INTERVAL_SECONDS = 60;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PasswordResetTokenRepositoryInterface $resetTokens,
        private readonly UserSessionRepositoryInterface $sessions,
        private readonly AccessTokenRepositoryInterface $accessTokens,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly MailSenderInterface $mailSender,
        private readonly SecureToken $secureToken,
        private readonly PasswordHasher $passwordHasher,
        private readonly IpAddress $ipAddress,
        private readonly string $resetBaseUrl,
    ) {
    }

    public function requestReset(string $email, ?string $requestIp): void
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $user = $this->users->findByUsernameOrEmail($normalizedEmail);
        if (
            $user === null
            || $user->email !== $normalizedEmail
            || $user->status !== UserStatus::Active
        ) {
            return;
        }

        $now = $this->now();
        if ($this->resetTokens->hasIssuedSince(
            $user->id,
            $now->sub(new DateInterval('PT' . self::MINIMUM_REQUEST_INTERVAL_SECONDS . 'S')),
        )) {
            return;
        }

        $rawToken = $this->secureToken->generate();
        Db::connection()->transaction(function () use ($user, $requestIp, $rawToken, $now): void {
            $this->resetTokens->invalidateOutstandingForUser($user->id, $now);
            $this->resetTokens->create([
                'user_id' => $user->id,
                'token_hash' => $this->secureToken->hash($rawToken),
                'ip_address' => $this->ipAddress->toBinary($requestIp),
                'expires_at' => $now->add(new DateInterval('PT' . self::RESET_LIFETIME_MINUTES . 'M')),
                'created_at' => $now,
            ]);
        });

        $url = $this->resetBaseUrl . '/reset-password#token=' . rawurlencode($rawToken);
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        try {
            $this->mailSender->send(
                (string) $user->email,
                '重置你的哞哞通行证密码',
                "请在 30 分钟内打开以下链接重置密码：\n{$url}\n\n如果不是你本人操作，请忽略此邮件。",
                "<p>请在 30 分钟内完成密码重置。</p><p><a href=\"{$safeUrl}\">重置密码</a></p><p>如果不是你本人操作，请忽略此邮件。</p>",
            );
        } catch (\Throwable) {
            $this->auditLogs->record([
                'event_type' => 'user.password_reset.delivery_failed',
                'user_id' => $user->id,
                'success' => false,
            ]);
        }
    }

    public function reset(string $rawToken, string $newPassword, ?string $requestIp): void
    {
        $now = $this->now();
        $tokenHash = $this->secureToken->hash($rawToken);
        $resetToken = $this->resetTokens->findValidByHash($tokenHash, $now);
        if ($resetToken === null) {
            throw $this->invalidResetToken();
        }

        Db::connection()->transaction(function () use ($resetToken, $tokenHash, $newPassword, $requestIp, $now): void {
            if (!$this->resetTokens->consume($tokenHash, $now)) {
                throw $this->invalidResetToken();
            }
            $user = $this->users->findById($resetToken->user_id);
            if ($user === null || $user->status !== UserStatus::Active) {
                throw $this->invalidResetToken();
            }

            $this->replacePasswordAndRevokeCredentials($user, $newPassword, $now);
            $this->resetTokens->invalidateOutstandingForUser($user->id, $now);
            $this->auditLogs->record([
                'event_type' => 'user.password_reset.completed',
                'user_id' => $user->id,
                'ip_address' => $this->ipAddress->toBinary($requestIp),
                'success' => true,
            ]);
        });
    }

    public function change(User $user, string $currentPassword, string $newPassword, ?string $requestIp): void
    {
        if (!$this->passwordHasher->verify($currentPassword, $user->password_hash)) {
            throw new BusinessException('invalid_current_password', '当前密码不正确。', 400);
        }
        if ($this->passwordHasher->verify($newPassword, $user->password_hash)) {
            throw new BusinessException('password_reused', '新密码不能与当前密码相同。', 422);
        }

        $now = $this->now();
        Db::connection()->transaction(function () use ($user, $newPassword, $requestIp, $now): void {
            $this->replacePasswordAndRevokeCredentials($user, $newPassword, $now);
            $this->resetTokens->invalidateOutstandingForUser($user->id, $now);
            $this->auditLogs->record([
                'event_type' => 'user.password_changed',
                'user_id' => $user->id,
                'ip_address' => $this->ipAddress->toBinary($requestIp),
                'success' => true,
            ]);
        });
    }

    private function replacePasswordAndRevokeCredentials(
        User $user,
        string $newPassword,
        DateTimeImmutable $now,
    ): void {
        $user->password_hash = $this->passwordHasher->hash($newPassword);
        $user->password_changed_at = $now;
        $this->users->save($user);
        $this->sessions->revokeAllForUser($user->id, $now);
        $this->accessTokens->revokeForUser($user->id, $now);
        $this->refreshTokens->revokeForUser($user->id, $now);
    }

    private function invalidResetToken(): BusinessException
    {
        return new BusinessException('invalid_password_reset_token', '密码重置链接无效或已过期。', 400);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\enum\UserStatus;
use app\common\exception\BusinessException;
use app\common\infrastructure\mail\MailSenderInterface;
use app\common\model\User;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\EmailVerificationTokenRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\support\SecureToken;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use support\Db;

/** 签发并消费一次性邮箱验证令牌。 */
final class EmailVerificationService
{
    private const TOKEN_LIFETIME_MINUTES = 30;
    private const MINIMUM_RESEND_INTERVAL_SECONDS = 60;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EmailVerificationTokenRepositoryInterface $tokens,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly MailSenderInterface $mailSender,
        private readonly SecureToken $secureToken,
        private readonly string $verificationBaseUrl,
    ) {
    }

    public function sendForUser(User $user): void
    {
        if ($user->email === null || $user->email === '') {
            return;
        }

        $now = $this->now();
        $resendBoundary = $now->sub(new DateInterval('PT' . self::MINIMUM_RESEND_INTERVAL_SECONDS . 'S'));
        if ($this->tokens->hasIssuedSince($user->id, $resendBoundary)) {
            return;
        }

        $rawToken = $this->secureToken->generate();
        $expiresAt = $now->add(new DateInterval('PT' . self::TOKEN_LIFETIME_MINUTES . 'M'));

        Db::connection()->transaction(function () use ($user, $rawToken, $now, $expiresAt): void {
            $this->tokens->invalidateOutstandingForUser($user->id, $now);
            $this->tokens->create([
                'user_id' => $user->id,
                'email' => $user->email,
                'token_hash' => $this->secureToken->hash($rawToken),
                'purpose' => 'verify_email',
                'expires_at' => $expiresAt,
                'created_at' => $now,
            ]);
        });

        // URL fragment 不会随 HTTP 请求发送到服务器，可降低令牌进入访问日志或 Referer 的风险。
        $verificationUrl = $this->verificationBaseUrl . '/verify-email#token=' . rawurlencode($rawToken);
        $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeName = htmlspecialchars($user->display_name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $this->mailSender->send(
            $user->email,
            '验证你的哞哞通行证邮箱',
            "你好，{$user->display_name}：\n\n请在 30 分钟内打开以下链接完成邮箱验证：\n{$verificationUrl}\n\n如果不是你本人操作，请忽略此邮件。",
            "<p>你好，{$safeName}：</p><p>请在 30 分钟内完成邮箱验证。</p><p><a href=\"{$safeUrl}\">验证邮箱</a></p><p>如果不是你本人操作，请忽略此邮件。</p>",
        );
    }

    public function verify(string $rawToken): User
    {
        $now = $this->now();
        $tokenHash = $this->secureToken->hash($rawToken);
        $challenge = $this->tokens->findValidByHash($tokenHash, $now);

        if ($challenge === null) {
            throw new BusinessException('invalid_verification_token', '验证链接无效或已过期。', 400);
        }

        /** @var User $user */
        $user = Db::connection()->transaction(function () use ($challenge, $tokenHash, $now): User {
            if (!$this->tokens->consume($tokenHash, $now)) {
                throw new BusinessException('invalid_verification_token', '验证链接无效或已过期。', 400);
            }

            $user = $this->users->findById($challenge->user_id);
            if ($user === null || $user->email !== $challenge->email) {
                throw new BusinessException('invalid_verification_token', '验证链接无效或已过期。', 400);
            }

            $user->email_verified_at = $now;
            $user->status = UserStatus::Active;
            $this->users->save($user);
            $this->auditLogs->record([
                'event_type' => 'user.email.verified',
                'user_id' => $user->id,
                'success' => true,
            ]);

            return $user;
        });

        return $user;
    }

    /** 始终静默返回，防止调用方枚举已注册邮箱。 */
    public function resend(string $email): void
    {
        $normalizedEmail = mb_strtolower(trim($email));
        $user = $this->users->findByUsernameOrEmail($normalizedEmail);

        if ($user === null || $user->email !== $normalizedEmail || $user->status !== UserStatus::Pending) {
            return;
        }

        $this->sendForUser($user);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

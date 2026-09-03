<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\enum\UserStatus;
use app\common\exception\BusinessException;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\support\IpAddress;
use app\common\support\PasswordHasher;
use app\passport\dto\RegisterInput;
use app\passport\dto\RegisterResult;
use Symfony\Component\Uid\Ulid;

/**
 * 注册本地账号，并在同一事务中记录安全审计事件。
 *
 * 新注册账号保持待验证状态，完成邮箱验证后才可登录。
 */
final class RegisterService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly PasswordHasher $passwordHasher,
        private readonly IpAddress $ipAddress,
        private readonly EmailVerificationService $emailVerification,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    public function register(RegisterInput $input): RegisterResult
    {
        $username = mb_strtolower(trim($input->username));
        $email = mb_strtolower(trim($input->email));

        if ($this->users->usernameExists($username)) {
            throw new BusinessException('username_taken', '该用户名已被使用。', 409);
        }
        if ($this->users->emailExists($email)) {
            throw new BusinessException('email_taken', '该邮箱已被使用。', 409);
        }

        $passwordHash = $this->passwordHasher->hash($input->password);

        /** @var RegisterResult $result */
        $result = $this->transactions->run(function () use ($input, $username, $email, $passwordHash): RegisterResult {
            $user = $this->users->create([
                'public_id' => (string) new Ulid(),
                'username' => $username,
                'email' => $email,
                'password_hash' => $passwordHash,
                'display_name' => trim($input->displayName),
                'status' => UserStatus::Pending,
            ]);

            $this->auditLogs->record([
                'event_type' => 'user.registered',
                'user_id' => $user->id,
                'ip_address' => $this->ipAddress->toBinary($input->ipAddress),
                'user_agent' => $this->truncateUserAgent($input->userAgent),
                'success' => true,
                'details' => ['email_verification_required' => true],
            ]);

            return new RegisterResult($user);
        });

        // 邮件投递必须放在账号事务之外，临时 SMTP 故障不能回滚已经成功的注册。
        try {
            $this->emailVerification->sendForUser($result->user);
        } catch (\Throwable) {
            $this->auditLogs->record([
                'event_type' => 'user.email.delivery_failed',
                'user_id' => $result->user->id,
                'success' => false,
            ]);
        }

        return $result;
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        return $userAgent === null ? null : mb_substr($userAgent, 0, 500);
    }
}

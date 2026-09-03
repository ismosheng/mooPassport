<?php

declare(strict_types=1);

use app\common\enum\UserStatus;
use app\common\exception\BusinessException;
use app\common\infrastructure\mail\MailSenderInterface;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\model\EmailVerificationToken;
use app\common\model\LoginAttempt;
use app\common\model\PasswordResetToken;
use app\common\model\User;
use app\common\model\UserSession;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\EmailVerificationTokenRepositoryInterface;
use app\common\repository\contract\PasswordResetTokenRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\repository\contract\UserRepositoryInterface;
use app\common\repository\contract\UserSessionRepositoryInterface;
use app\common\support\IpAddress;
use app\common\support\PasswordHasher;
use app\common\support\SecureToken;
use app\passport\dto\LoginInput;
use app\passport\dto\RegisterInput;
use app\passport\service\EmailVerificationService;
use app\passport\service\MailTemplateService;
use app\common\repository\contract\SystemSettingReaderInterface;
use app\passport\service\LoginService;
use app\passport\service\PasswordService;
use app\passport\service\RegisterService;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;
use support\App;
use support\Db;
use Symfony\Component\Uid\Ulid;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, 2);
Dotenv::createUnsafeImmutable($root)->load();
App::loadAllConfig();
/** @var ContainerInterface $container */
$container = require $root . '/config/container.php';

$assert = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$expectBusinessError = static function (callable $operation, string $expectedCode, string $message): void {
    try {
        $operation();
    } catch (BusinessException $exception) {
        if ($exception->errorCode === $expectedCode) {
            return;
        }
        throw new RuntimeException($message . '，实际错误：' . $exception->errorCode);
    }

    throw new RuntimeException($message . '，操作未被拒绝。');
};
$extractFragmentToken = static function (string $text): string {
    if (preg_match('/#token=([^\s]+)/', $text, $matches) !== 1) {
        throw new RuntimeException('测试邮件中没有找到一次性令牌。');
    }

    return rawurldecode($matches[1]);
};

$mail = new class implements MailSenderInterface {
    /** @var list<array{recipient: string, subject: string, text: string, html: string}> */
    public array $messages = [];

    public function send(string $recipient, string $subject, string $text, string $html): void
    {
        $this->messages[] = compact('recipient', 'subject', 'text', 'html');
    }
};

$secureToken = new SecureToken();
$passwordHasher = new PasswordHasher();
$ipAddress = new IpAddress();
$users = $container->get(UserRepositoryInterface::class);
$sessions = $container->get(UserSessionRepositoryInterface::class);
$auditLogs = $container->get(AuditLogRepositoryInterface::class);
$emailTokens = $container->get(EmailVerificationTokenRepositoryInterface::class);
$resetTokens = $container->get(PasswordResetTokenRepositoryInterface::class);
$transactions = $container->get(TransactionManagerInterface::class);
$mailTemplates = new MailTemplateService(
    new class implements SystemSettingReaderInterface {
        public function allByKey(): array
        {
            return [];
        }
    },
    'http://passport.test',
    'Moo Passport',
);
$emailVerification = new EmailVerificationService(
    $users,
    $emailTokens,
    $auditLogs,
    $mail,
    $secureToken,
    $mailTemplates,
    $transactions,
);
$register = new RegisterService(
    $users,
    $auditLogs,
    $passwordHasher,
    $ipAddress,
    $emailVerification,
    $transactions,
);
/** @var LoginService $login */
$login = $container->get(LoginService::class);
$passwords = new PasswordService(
    $users,
    $resetTokens,
    $sessions,
    $container->get(AccessTokenRepositoryInterface::class),
    $container->get(RefreshTokenRepositoryInterface::class),
    $auditLogs,
    $mail,
    $secureToken,
    $passwordHasher,
    $ipAddress,
    $mailTemplates,
    $transactions,
);

$models = [User::class, UserSession::class, LoginAttempt::class, EmailVerificationToken::class, PasswordResetToken::class];
$countsBefore = [];
foreach ($models as $model) {
    $countsBefore[$model] = $model::query()->count();
}

$connection = Db::connection();
$connection->beginTransaction();

try {
    $suffix = bin2hex(random_bytes(5));
    $registeredPassword = 'Register-Aa!9';
    $registered = $register->register(new RegisterInput(
        username: 'register_' . $suffix,
        email: 'register_' . $suffix . '@example.invalid',
        password: $registeredPassword,
        displayName: '账号流程测试用户',
        ipAddress: '192.0.2.10',
        userAgent: 'MooPassportSecurityTest/1.0',
    ));
    $assert($registered->user->status === UserStatus::Pending, '新注册账号没有保持 pending 状态。');
    $assert(count($mail->messages) === 1, '注册没有生成激活邮件。');

    $expectBusinessError(
        fn () => $login->login(new LoginInput(
            $registered->user->username,
            $registeredPassword,
            '192.0.2.10',
            'MooPassportSecurityTest/1.0',
        )),
        'invalid_credentials',
        '未激活账号可以登录',
    );

    $verificationToken = $extractFragmentToken($mail->messages[0]['text']);
    $storedVerification = EmailVerificationToken::query()->where('user_id', $registered->user->id)->firstOrFail();
    $assert(!hash_equals($verificationToken, (string) $storedVerification->token_hash), '邮箱验证令牌被明文存储。');
    $verified = $emailVerification->verify($verificationToken);
    $assert($verified->status === UserStatus::Active, '邮箱验证后账号没有激活。');
    $expectBusinessError(
        fn () => $emailVerification->verify($verificationToken),
        'invalid_verification_token',
        '邮箱验证令牌可以重复使用',
    );

    $loginResult = $login->login(new LoginInput(
        $registered->user->username,
        $registeredPassword,
        '192.0.2.10',
        'MooPassportSecurityTest/1.0',
    ));
    $storedSession = UserSession::query()->where('user_id', $registered->user->id)->latest('id')->firstOrFail();
    $assert(!hash_equals($loginResult->sessionToken, (string) $storedSession->session_hash), '浏览器会话令牌被明文存储。');
    fwrite(STDOUT, "PASS registration_activation_login\n");
    fwrite(STDOUT, "PASS activation_token_single_use_and_hashing\n");

    $limitedPassword = 'Limited-Aa!9';
    $limitedUser = User::query()->create([
        'public_id' => (string) new Ulid(),
        'username' => 'limited_' . $suffix,
        'email' => 'limited_' . $suffix . '@example.invalid',
        'password_hash' => $passwordHasher->hash($limitedPassword),
        'display_name' => '登录限流测试用户',
        'status' => UserStatus::Active,
        'email_verified_at' => new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai')),
    ]);
    for ($attempt = 0; $attempt < 5; ++$attempt) {
        $expectBusinessError(
            fn () => $login->login(new LoginInput(
                $limitedUser->username,
                'Wrong-Aa!9',
                '198.51.100.20',
                'MooPassportSecurityTest/1.0',
            )),
            'invalid_credentials',
            '错误密码没有按统一凭据错误处理',
        );
    }
    $expectBusinessError(
        fn () => $login->login(new LoginInput(
            $limitedUser->username,
            $limitedPassword,
            '198.51.100.20',
            'MooPassportSecurityTest/1.0',
        )),
        'login_rate_limited',
        '连续失败后仍可立即尝试登录',
    );
    fwrite(STDOUT, "PASS login_rate_limit\n");

    $resetPassword = 'Reset-Old!9';
    $resetUser = User::query()->create([
        'public_id' => (string) new Ulid(),
        'username' => 'reset_' . $suffix,
        'email' => 'reset_' . $suffix . '@example.invalid',
        'password_hash' => $passwordHasher->hash($resetPassword),
        'display_name' => '密码重置测试用户',
        'status' => UserStatus::Active,
        'email_verified_at' => new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai')),
    ]);
    $resetLogin = $login->login(new LoginInput(
        $resetUser->username,
        $resetPassword,
        '203.0.113.30',
        'MooPassportSecurityTest/1.0',
    ));
    $messageCount = count($mail->messages);
    $passwords->requestReset((string) $resetUser->email, '203.0.113.30');
    $assert(count($mail->messages) === $messageCount + 1, '有效账号没有生成密码重置邮件。');
    $passwords->requestReset('missing_' . $suffix . '@example.invalid', '203.0.113.30');
    $assert(count($mail->messages) === $messageCount + 1, '不存在账号的重置请求产生了可观察邮件。');

    $resetToken = $extractFragmentToken($mail->messages[array_key_last($mail->messages)]['text']);
    $storedReset = PasswordResetToken::query()->where('user_id', $resetUser->id)->firstOrFail();
    $assert(!hash_equals($resetToken, (string) $storedReset->token_hash), '密码重置令牌被明文存储。');
    $newPassword = 'Reset-New!9';
    $passwords->reset($resetToken, $newPassword, '203.0.113.30');
    $expectBusinessError(
        fn () => $passwords->reset($resetToken, 'Another-New!9', '203.0.113.30'),
        'invalid_password_reset_token',
        '密码重置令牌可以重复使用',
    );
    $assert(
        UserSession::query()->where('user_id', $resetUser->id)->whereNotNull('revoked_at')->exists(),
        '密码重置后旧浏览器会话没有撤销。',
    );
    $expectBusinessError(
        fn () => $login->login(new LoginInput(
            $resetUser->username,
            $resetPassword,
            '203.0.113.30',
            'MooPassportSecurityTest/1.0',
        )),
        'invalid_credentials',
        '密码重置后旧密码仍可登录',
    );
    $newLogin = $login->login(new LoginInput(
        $resetUser->username,
        $newPassword,
        '203.0.113.30',
        'MooPassportSecurityTest/1.0',
    ));
    $assert($newLogin->sessionToken !== $resetLogin->sessionToken, '密码重置后复用了旧会话令牌。');
    fwrite(STDOUT, "PASS password_reset_single_use_and_enumeration_resistance\n");
    fwrite(STDOUT, "PASS password_reset_revokes_sessions\n");
} finally {
    $connection->rollBack();
}

foreach ($countsBefore as $model => $count) {
    $assert($model::query()->count() === $count, $model . ' 的测试数据没有完全回滚。');
}
fwrite(STDOUT, "PASS transaction_rollback\n");

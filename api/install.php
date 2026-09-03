<?php

declare(strict_types=1);

use app\common\support\PasswordHasher;
use app\oauth\service\SigningKeyService;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;
use Symfony\Component\Uid\Ulid;
use support\App;

require __DIR__ . '/vendor/autoload.php';

const EXPECTED_TABLES = [
    'moo_applications',
    'moo_audit_archive_runs',
    'moo_email_verification_tokens',
    'moo_login_attempts',
    'moo_mfa_methods',
    'moo_oauth_access_tokens',
    'moo_oauth_audit_logs',
    'moo_oauth_authorization_codes',
    'moo_oauth_client_redirect_uris',
    'moo_oauth_client_scopes',
    'moo_oauth_client_secrets',
    'moo_oauth_clients',
    'moo_oauth_consents',
    'moo_oauth_pushed_authorization_requests',
    'moo_oauth_refresh_tokens',
    'moo_oauth_scopes',
    'moo_oauth_signing_keys',
    'moo_password_reset_tokens',
    'moo_permissions',
    'moo_role_permissions',
    'moo_roles',
    'moo_system_settings',
    'moo_user_identities',
    'moo_user_roles',
    'moo_user_sessions',
    'moo_users',
];

/** @return never */
function fail(string $message): never
{
    fwrite(STDERR, "安装失败：{$message}" . PHP_EOL);
    exit(1);
}

function prompt(string $label, ?string $default = null): string
{
    $suffix = $default === null ? ': ' : " [{$default}]: ";
    $value = trim((string) readline($label . $suffix));
    return $value === '' ? (string) $default : $value;
}

function promptSecret(string $label): string
{
    if (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
        $script = '$p=Read-Host -Prompt ' . escapeshellarg($label) . ' -AsSecureString;'
            . '$b=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($p);'
            . 'try{[Runtime.InteropServices.Marshal]::PtrToStringBSTR($b)}'
            . 'finally{[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b)}';
        $value = shell_exec('powershell.exe -NoProfile -Command ' . escapeshellarg($script));
        if (is_string($value)) {
            return rtrim($value, "\r\n");
        }
    }

    if (PHP_OS_FAMILY !== 'Windows' && function_exists('shell_exec')) {
        fwrite(STDOUT, $label . ': ');
        shell_exec('stty -echo');
        $value = rtrim((string) fgets(STDIN), "\r\n");
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
        return $value;
    }

    fail('当前终端无法安全读取管理员密码，请通过 MOO_INSTALL_ADMIN_PASSWORD 环境变量提供。');
}

function ensureEncryptionKey(string $environmentFile, string $variable, string $description): string
{
    $key = trim((string) (getenv($variable) ?: ''));
    if ($key !== '') {
        return $key;
    }

    $contents = file_get_contents($environmentFile);
    if (!is_string($contents)) {
        fail('无法读取 .env。');
    }

    $key = base64_encode(random_bytes(32));
    $line = $variable . '=' . $key;
    $pattern = '/^' . preg_quote($variable, '/') . '=.*$/m';
    $updated = preg_match($pattern, $contents) === 1
        ? preg_replace($pattern, $line, $contents)
        : rtrim($contents) . PHP_EOL . $line . PHP_EOL;
    if (!is_string($updated) || file_put_contents($environmentFile, $updated, LOCK_EX) === false) {
        fail('无法把' . $description . '写入 .env。');
    }

    putenv($variable . '=' . $key);
    $_ENV[$variable] = $key;
    $_SERVER[$variable] = $key;
    return $key;
}

/** @return list<string> */
function splitSql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $lineComment = false;
    $blockComment = false;
    $length = strlen($sql);

    for ($index = 0; $index < $length; ++$index) {
        $character = $sql[$index];
        $next = $index + 1 < $length ? $sql[$index + 1] : '';

        if ($lineComment) {
            if ($character === "\n") {
                $lineComment = false;
                $buffer .= $character;
            }
            continue;
        }
        if ($blockComment) {
            if ($character === '*' && $next === '/') {
                $blockComment = false;
                ++$index;
            }
            continue;
        }
        if ($quote === null && (($character === '-' && $next === '-') || $character === '#')) {
            $lineComment = true;
            if ($character === '-') {
                ++$index;
            }
            continue;
        }
        if ($quote === null && $character === '/' && $next === '*') {
            $blockComment = true;
            ++$index;
            continue;
        }
        if ($quote === null && in_array($character, ["'", '"', '`'], true)) {
            $quote = $character;
        } elseif ($quote === $character) {
            $escaped = $index > 0 && $sql[$index - 1] === '\\';
            $doubled = $next === $character;
            if ($doubled) {
                $buffer .= $character . $next;
                ++$index;
                continue;
            }
            if (!$escaped) {
                $quote = null;
            }
        }

        if ($character === ';' && $quote === null) {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $character;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }
    return $statements;
}

/** @return list<string> */
function installedTables(PDO $pdo, string $database): array
{
    $query = $pdo->prepare(
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME LIKE 'moo\\_%' ESCAPE '\\\\' ORDER BY TABLE_NAME",
    );
    $query->execute([$database]);
    return array_map('strval', $query->fetchAll(PDO::FETCH_COLUMN));
}

function validateInstallation(PDO $pdo, string $database): void
{
    $tables = installedTables($pdo, $database);
    $missing = array_values(array_diff(EXPECTED_TABLES, $tables));
    if ($missing !== []) {
        fail('数据库结构不完整，缺少表：' . implode(', ', $missing));
    }

    $requiredColumns = [
        'moo_users' => [
            'gender', 'birth_date', 'bio', 'real_name_encrypted', 'identity_document_type',
            'identity_document_number_encrypted', 'identity_document_number_hash',
            'realname_status', 'realname_verified_at',
        ],
        'moo_applications' => ['logo_url'],
        'moo_oauth_clients' => ['application_id'],
        'moo_roles' => ['is_system', 'status', 'version'],
        'moo_system_settings' => ['version', 'updated_by_user_id'],
    ];
    $query = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?',
    );
    foreach ($requiredColumns as $table => $columns) {
        foreach ($columns as $column) {
            $query->execute([$database, $table, $column]);
            if ((int) $query->fetchColumn() !== 1) {
                fail("数据库结构不完整，缺少字段 {$table}.{$column}。");
            }
        }
    }

    $requiredScopes = ['service', 'realname', 'realname_full'];
    $scopePlaceholders = implode(', ', array_fill(0, count($requiredScopes), '?'));
    $scopeQuery = $pdo->prepare("SELECT COUNT(*) FROM moo_oauth_scopes WHERE name IN ({$scopePlaceholders})");
    $scopeQuery->execute($requiredScopes);
    if ((int) $scopeQuery->fetchColumn() !== count($requiredScopes)) {
        fail('缺少必要的 OAuth Scope：' . implode(', ', $requiredScopes) . '。');
    }
    $permissionCount = (int) $pdo->query('SELECT COUNT(*) FROM moo_permissions')->fetchColumn();
    $superPermissionCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM moo_role_permissions rp JOIN moo_roles r ON r.id = rp.role_id WHERE r.code = 'super_admin'",
    )->fetchColumn();
    if ($permissionCount < 19 || $superPermissionCount !== $permissionCount) {
        fail('超级管理员权限种子不完整。');
    }
}

/** @param array<string, mixed> $options */
function createAdministrator(PDO $pdo, array $options): void
{
    $username = trim((string) ($options['admin-username'] ?? ''));
    $email = trim((string) ($options['admin-email'] ?? ''));
    $displayName = trim((string) ($options['admin-display-name'] ?? ''));
    $interactive = function_exists('stream_isatty') && stream_isatty(STDIN);

    if ($username === '' && $interactive) {
        $username = prompt('管理员用户名', 'admin');
    }
    if ($email === '' && $interactive) {
        $email = prompt('管理员邮箱');
    }
    if ($displayName === '' && $interactive) {
        $displayName = prompt('管理员显示名称', '系统管理员');
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        fail('管理员用户名必须为 3-32 位字母、数字或下划线。');
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 191) {
        fail('管理员邮箱格式无效。');
    }

    $password = (string) (getenv('MOO_INSTALL_ADMIN_PASSWORD') ?: '');
    if ($password === '' && $interactive) {
        $password = promptSecret('管理员密码');
        $confirmation = promptSecret('再次输入管理员密码');
        if (!hash_equals($password, $confirmation)) {
            fail('两次输入的管理员密码不一致。');
        }
    }
    if (strlen($password) < 9 || strlen($password) > 128
        || preg_match('/[A-Z]/', $password) !== 1
        || preg_match('/[a-z]/', $password) !== 1
        || preg_match('/[\x21-\x2F\x3A-\x40\x5B-\x60\x7B-\x7E]/', $password) !== 1) {
        fail('管理员密码必须为 9-128 位，并包含大写字母、小写字母和特殊符号。');
    }

    $duplicate = $pdo->prepare('SELECT COUNT(*) FROM moo_users WHERE username = ? OR email = ?');
    $duplicate->execute([$username, $email]);
    if ((int) $duplicate->fetchColumn() !== 0) {
        fail('管理员用户名或邮箱已存在。');
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare(
            'INSERT INTO moo_users (public_id, username, email, password_hash, display_name, status, email_verified_at, password_changed_at) '
            . "VALUES (?, ?, ?, ?, ?, 'active', ?, ?)",
        );
        $insert->execute([
            (string) new Ulid(),
            $username,
            $email,
            (new PasswordHasher())->hash($password),
            $displayName,
            $now->format('Y-m-d H:i:s.u'),
            $now->format('Y-m-d H:i:s.u'),
        ]);
        $userId = (int) $pdo->lastInsertId();
        $roleId = (int) $pdo->query("SELECT id FROM moo_roles WHERE code = 'super_admin'")->fetchColumn();
        $grant = $pdo->prepare('INSERT INTO moo_user_roles (user_id, role_id, granted_by_user_id) VALUES (?, ?, NULL)');
        $grant->execute([$userId, $roleId]);
        $audit = $pdo->prepare(
            "INSERT INTO moo_oauth_audit_logs (event_type, user_id, success, details) VALUES ('system.install.admin_created', ?, 1, ?)",
        );
        $audit->execute([$userId, json_encode(['username' => $username], JSON_THROW_ON_ERROR)]);
        $pdo->commit();
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    } finally {
        // 明文密码只在当前调用栈中短暂存在，不写入文件或日志。
        $password = '';
    }

    fwrite(STDOUT, "超级管理员已创建：{$username}" . PHP_EOL);
}

if (!extension_loaded('pdo_mysql')) {
    fail('缺少 pdo_mysql 扩展。');
}

$options = getopt('', ['check', 'no-admin', 'admin-username:', 'admin-email:', 'admin-display-name::']);
if (!is_array($options)) {
    $options = [];
}
$environmentFile = __DIR__ . '/.env';
if (!is_file($environmentFile)) {
    if (!copy(__DIR__ . '/.env.example', $environmentFile)) {
        fail('无法从 .env.example 创建 .env。');
    }
    fail('已创建 .env，请先填写数据库、Redis、邮件和站点配置，然后重新执行安装。');
}

Dotenv::createUnsafeMutable(__DIR__)->safeLoad();
$host = (string) (getenv('DB_HOST') ?: '127.0.0.1');
$port = (int) (getenv('DB_PORT') ?: 3306);
$database = (string) (getenv('DB_DATABASE') ?: 'moopassport');
$username = (string) (getenv('DB_USERNAME') ?: '');
$password = (string) (getenv('DB_PASSWORD') ?: '');
if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fail('DB_DATABASE 只能包含字母、数字和下划线。');
}
if ($username === '' || $password === '' || $password === 'change-me') {
    fail('请先在 .env 中配置有效的 DB_USERNAME 和 DB_PASSWORD。');
}

$attributes = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
    PDO::ATTR_EMULATE_PREPARES => false,
];
try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
        $username,
        $password,
        $attributes,
    );
} catch (Throwable $exception) {
    if (array_key_exists('check', $options)) {
        fail('无法连接目标数据库，请检查 .env。检查模式不会创建数据库。');
    }
    try {
        $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, $attributes);
        $server->exec(
            'CREATE DATABASE `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        );
        $pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
            $username,
            $password,
            $attributes,
        );
    } catch (Throwable $creationException) {
        fail('无法连接或创建数据库，请检查 .env，并确认数据库已预先创建或账号具有建库权限。');
    }
}

if (array_key_exists('check', $options)) {
    validateInstallation($pdo, $database);
    fwrite(STDOUT, '数据库结构检查通过，共 ' . count(EXPECTED_TABLES) . ' 张业务表。' . PHP_EOL);
    exit(0);
}

$existingTables = installedTables($pdo, $database);
if ($existingTables !== []) {
    fail('目标数据库已包含 Moo Passport 表。安装器不会覆盖已有数据；请使用 php install.php --check 检查结构。');
}

$schema = file_get_contents(__DIR__ . '/database/schema.sql');
if (!is_string($schema)) {
    fail('无法读取 database/schema.sql。');
}
try {
    foreach (splitSql($schema) as $statement) {
        $pdo->exec($statement);
    }
    if (!array_key_exists('no-admin', $options)) {
        createAdministrator($pdo, $options);
    }

    ensureEncryptionKey($environmentFile, 'OIDC_PRIVATE_KEY_ENCRYPTION_KEY', 'OIDC 私钥加密主密钥');
    ensureEncryptionKey($environmentFile, 'USER_DATA_ENCRYPTION_KEY', '用户实名资料加密主密钥');
    App::loadAllConfig();
    /** @var ContainerInterface $container */
    $container = require __DIR__ . '/config/container.php';
    $signingKey = $container->get(SigningKeyService::class)->ensureActiveKey();
    validateInstallation($pdo, $database);
} catch (Throwable $exception) {
    fail('执行数据库初始化失败：' . $exception->getMessage());
}

fwrite(STDOUT, "OIDC 签名密钥已创建，kid={$signingKey->kid}" . PHP_EOL);
fwrite(STDOUT, '安装完成。启动服务前请再次检查 APP_URL、OAUTH_ISSUER、CORS、Cookie、Redis 和 SMTP 配置。' . PHP_EOL);

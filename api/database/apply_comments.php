<?php

declare(strict_types=1);

/**
 * 在不改变字段定义的前提下补充中文表注释和字段注释。
 *
 * 脚本先通过 SHOW CREATE TABLE 读取原始定义，再添加注释，从而保留索引、
 * 默认值、精度及生成列等属性。
 */

require dirname(__DIR__) . '/vendor/autoload.php';

if (class_exists(Dotenv\Dotenv::class) && is_file(dirname(__DIR__) . '/.env')) {
    Dotenv\Dotenv::createUnsafeMutable(dirname(__DIR__))->safeLoad();
}

$tables = [
    'moo_users' => ['用户账户表', [
        'id' => '内部自增主键', 'public_id' => '对外公开的用户ULID', 'username' => '用户名',
        'email' => '邮箱地址', 'phone_country_code' => '手机国家或地区代码', 'phone_number' => '手机号码',
        'password_hash' => '密码哈希，禁止保存明文密码', 'display_name' => '用户显示名称',
        'avatar_url' => '头像地址', 'status' => '账户状态', 'email_verified_at' => '邮箱验证时间',
        'phone_verified_at' => '手机号验证时间', 'password_changed_at' => '最近修改密码时间',
        'last_login_at' => '最近登录时间', 'created_at' => '创建时间', 'updated_at' => '更新时间',
        'deleted_at' => '软删除时间',
    ]],
    'moo_user_identities' => ['用户外部身份关联表', [
        'id' => '内部自增主键', 'user_id' => '关联用户ID', 'provider' => '身份提供方标识',
        'provider_subject' => '用户在身份提供方的唯一标识', 'profile' => '身份提供方返回的公开资料快照',
        'created_at' => '创建时间', 'updated_at' => '更新时间',
    ]],
    'moo_user_sessions' => ['用户登录会话表', [
        'id' => '内部自增主键', 'session_hash' => '会话令牌的SHA-256哈希', 'user_id' => '关联用户ID',
        'ip_address' => '客户端IP地址的二进制形式', 'user_agent' => '客户端User-Agent',
        'last_seen_at' => '最近活动时间', 'expires_at' => '会话过期时间', 'revoked_at' => '会话撤销时间',
        'created_at' => '创建时间',
    ]],
    'moo_login_attempts' => ['用户登录尝试记录表', [
        'id' => '内部自增主键', 'user_id' => '匹配到的用户ID',
        'login_identifier_hash' => '登录标识的SHA-256哈希', 'ip_address' => '客户端IP地址的二进制形式',
        'user_agent' => '客户端User-Agent', 'succeeded' => '是否登录成功',
        'failure_reason' => '失败原因代码', 'created_at' => '尝试时间',
    ]],
    'moo_email_verification_tokens' => ['邮箱验证令牌表', [
        'id' => '内部自增主键', 'user_id' => '关联用户ID', 'email' => '待验证邮箱地址',
        'token_hash' => '一次性令牌的SHA-256哈希', 'purpose' => '令牌用途', 'expires_at' => '令牌过期时间',
        'consumed_at' => '令牌使用时间', 'created_at' => '创建时间',
    ]],
    'moo_password_reset_tokens' => ['密码重置令牌表', [
        'id' => '内部自增主键', 'user_id' => '关联用户ID', 'token_hash' => '一次性令牌的SHA-256哈希',
        'ip_address' => '申请重置的IP地址', 'expires_at' => '令牌过期时间', 'consumed_at' => '令牌使用时间',
        'created_at' => '创建时间',
    ]],
    'moo_mfa_methods' => ['用户多因素认证方式表', [
        'id' => '内部自增主键', 'user_id' => '关联用户ID', 'type' => '认证方式类型',
        'name' => '用户定义的认证器名称', 'encrypted_secret' => '加密后的TOTP密钥',
        'credential_data' => 'WebAuthn公开凭据元数据', 'enabled_at' => '启用时间',
        'last_used_at' => '最近使用时间', 'created_at' => '创建时间', 'updated_at' => '更新时间',
    ]],
    'moo_oauth_clients' => ['OAuth客户端应用表', [
        'id' => '内部自增主键', 'client_id' => '公开的应用标识AppID', 'name' => '应用名称',
        'description' => '应用说明', 'logo_url' => '应用图标地址', 'client_type' => '客户端安全类型',
        'application_type' => '应用运行类型', 'token_endpoint_auth_method' => '令牌端点认证方式',
        'require_pkce' => '是否强制使用PKCE', 'require_consent' => '是否需要用户授权确认',
        'allowed_grant_types' => '允许的授权类型JSON数组', 'allowed_response_types' => '允许的响应类型JSON数组',
        'access_token_ttl' => '访问令牌有效秒数', 'refresh_token_ttl' => '刷新令牌有效秒数',
        'status' => '客户端状态', 'owner_user_id' => '应用所有者用户ID',
        'created_at' => '创建时间', 'updated_at' => '更新时间',
    ]],
    'moo_oauth_client_secrets' => ['OAuth客户端密钥表', [
        'id' => '内部自增主键', 'client_id' => '关联客户端内部ID', 'secret_id' => '密钥公开定位标识',
        'secret_hash' => 'AppSecret密码哈希', 'description' => '密钥用途说明',
        'last_used_at' => '最近使用时间', 'expires_at' => '密钥过期时间', 'revoked_at' => '密钥撤销时间',
        'created_at' => '创建时间',
    ]],
    'moo_oauth_client_redirect_uris' => ['OAuth客户端回调地址表', [
        'id' => '内部自增主键', 'client_id' => '关联客户端内部ID',
        'redirect_uri' => '精确匹配的OAuth回调地址', 'created_at' => '创建时间',
    ]],
    'moo_oauth_scopes' => ['OAuth权限范围定义表', [
        'id' => '内部自增主键', 'name' => 'Scope协议名称', 'display_name' => 'Scope显示名称',
        'description' => 'Scope权限说明', 'is_default' => '是否为默认Scope', 'status' => 'Scope状态',
        'created_at' => '创建时间', 'updated_at' => '更新时间',
    ]],
    'moo_oauth_client_scopes' => ['OAuth客户端可用权限关联表', [
        'client_id' => '关联客户端内部ID', 'scope_id' => '关联Scope内部ID', 'created_at' => '创建时间',
    ]],
    'moo_oauth_consents' => ['OAuth用户授权记录表', [
        'id' => '内部自增主键', 'user_id' => '授权用户ID', 'client_id' => '被授权客户端内部ID',
        'scopes' => '用户同意的Scope JSON数组', 'granted_at' => '授权时间',
        'expires_at' => '授权过期时间', 'revoked_at' => '授权撤销时间',
    ]],
    'moo_oauth_authorization_codes' => ['OAuth授权码表', [
        'id' => '内部自增主键', 'code_hash' => '授权码的SHA-256哈希', 'client_id' => '关联客户端内部ID',
        'user_id' => '授权用户ID', 'redirect_uri' => '本次授权绑定的回调地址',
        'scopes' => '本次授权的Scope JSON数组', 'code_challenge' => 'PKCE代码挑战值',
        'code_challenge_method' => 'PKCE代码挑战算法', 'nonce' => 'OIDC防重放随机值',
        'auth_time' => '用户完成认证的时间', 'expires_at' => '授权码过期时间',
        'used_at' => '授权码兑换时间', 'created_at' => '创建时间',
    ]],
    'moo_oauth_access_tokens' => ['OAuth访问令牌表', [
        'id' => '内部自增主键', 'token_hash' => '不透明访问令牌的SHA-256哈希',
        'client_id' => '关联客户端内部ID', 'user_id' => '令牌代表的用户ID，机器令牌为空',
        'grant_type' => '签发令牌使用的授权类型', 'scopes' => '令牌Scope JSON数组',
        'audience' => '令牌目标资源服务器', 'expires_at' => '令牌过期时间',
        'revoked_at' => '令牌撤销时间', 'created_at' => '创建时间',
    ]],
    'moo_oauth_refresh_tokens' => ['OAuth刷新令牌表', [
        'id' => '内部自增主键', 'token_hash' => '刷新令牌的SHA-256哈希',
        'family_id' => '令牌轮换家族ULID', 'parent_id' => '上一个刷新令牌ID',
        'access_token_id' => '关联访问令牌ID', 'client_id' => '关联客户端内部ID',
        'user_id' => '授权用户ID', 'scopes' => '令牌Scope JSON数组', 'expires_at' => '令牌过期时间',
        'used_at' => '令牌兑换时间', 'revoked_at' => '令牌撤销时间', 'created_at' => '创建时间',
    ]],
    'moo_oauth_signing_keys' => ['OIDC签名密钥表', [
        'id' => '内部自增主键', 'kid' => '公开密钥标识', 'algorithm' => '签名算法',
        'public_jwk' => '公开JWK数据', 'encrypted_private_key' => '加密后的签名私钥',
        'status' => '密钥生命周期状态', 'not_before' => '密钥开始生效时间',
        'expires_at' => '密钥过期时间', 'created_at' => '创建时间',
    ]],
    'moo_oauth_audit_logs' => ['OAuth安全审计日志表', [
        'id' => '内部自增主键', 'event_type' => '审计事件类型', 'user_id' => '关联用户ID',
        'client_id' => '关联客户端内部ID', 'ip_address' => '客户端IP地址的二进制形式',
        'user_agent' => '客户端User-Agent', 'request_id' => '请求追踪标识',
        'success' => '操作是否成功', 'details' => '不含敏感凭据的事件详情', 'created_at' => '事件时间',
    ]],
];

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('DB_PORT') ?: 3306);
$database = getenv('DB_DATABASE') ?: 'moopassport';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';

$pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
    $username,
    $password,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
);

$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

foreach ($tables as $table => [$tableComment, $columns]) {
    $quotedTable = '`' . str_replace('`', '``', $table) . '`';
    $statement = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch();
    if ($statement === false) {
        throw new RuntimeException("Table {$table} does not exist.");
    }

    $createSql = (string) array_values($statement)[1];
    foreach ($columns as $column => $comment) {
        $quotedColumnPattern = preg_quote('`' . $column . '`', '/');
        if (!preg_match('/^\s+' . $quotedColumnPattern . '\s+(.+?)(?:,)?$/m', $createSql, $match)) {
            throw new RuntimeException("Column {$table}.{$column} does not exist.");
        }

        $definition = preg_replace("/\s+COMMENT\s+'(?:[^'\\\\]|\\\\.)*'/i", '', $match[1]);
        if ($definition === null) {
            throw new RuntimeException("Unable to parse {$table}.{$column}.");
        }

        $quotedColumn = '`' . str_replace('`', '``', $column) . '`';
        $pdo->exec(
            "ALTER TABLE {$quotedTable} MODIFY COLUMN {$quotedColumn} {$definition} COMMENT "
            . $pdo->quote($comment),
        );
    }

    $pdo->exec("ALTER TABLE {$quotedTable} COMMENT = " . $pdo->quote($tableComment));
    echo "Commented {$table}\n";
}

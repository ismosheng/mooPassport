-- Moo Passport initial schema
-- Target: MySQL 8.0+
-- Convention: all application tables use the `moo_` prefix.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `moo_users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(26) NOT NULL COMMENT 'Externally visible stable user id (ULID)',
  `username` VARCHAR(64) NULL,
  `email` VARCHAR(191) NULL,
  `phone_country_code` VARCHAR(8) NULL,
  `phone_number` VARCHAR(32) NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL DEFAULT '',
  `avatar_url` VARCHAR(500) NULL,
  `gender` ENUM('male','female','other','undisclosed') NULL,
  `birth_date` DATE NULL,
  `bio` VARCHAR(500) NULL,
  `real_name_encrypted` VARBINARY(1024) NULL COMMENT 'Authenticated ciphertext; never store plaintext real names',
  `identity_document_type` ENUM('id_card','passport','other') NULL,
  `identity_document_number_encrypted` VARBINARY(1024) NULL COMMENT 'Authenticated ciphertext; never store plaintext document numbers',
  `identity_document_number_hash` BINARY(32) NULL COMMENT 'Keyed HMAC used only for duplicate detection',
  `realname_status` ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified',
  `realname_verified_at` DATETIME(6) NULL,
  `status` ENUM('pending','active','locked','disabled') NOT NULL DEFAULT 'pending',
  `email_verified_at` DATETIME(6) NULL,
  `phone_verified_at` DATETIME(6) NULL,
  `password_changed_at` DATETIME(6) NULL,
  `last_login_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  `deleted_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_users_public_id` (`public_id`),
  UNIQUE KEY `uk_moo_users_username` (`username`),
  UNIQUE KEY `uk_moo_users_email` (`email`),
  UNIQUE KEY `uk_moo_users_phone` (`phone_country_code`, `phone_number`),
  UNIQUE KEY `uk_moo_users_identity_document_hash` (`identity_document_number_hash`),
  KEY `idx_moo_users_realname_status` (`realname_status`),
  KEY `idx_moo_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL COMMENT '角色稳定标识，例如 super_admin',
  `name` VARCHAR(100) NOT NULL COMMENT '角色显示名称',
  `description` VARCHAR(500) NULL COMMENT '角色用途及权限边界说明',
  `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否系统内置角色；内置角色禁止删除',
  `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT '角色状态：active 或 disabled',
  `version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '乐观锁版本号，防止并发编辑覆盖',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_roles_code` (`code`),
  KEY `idx_moo_roles_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='通行证角色定义';

CREATE TABLE IF NOT EXISTS `moo_user_roles` (
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户内部主键',
  `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色内部主键',
  `granted_by_user_id` BIGINT UNSIGNED NULL COMMENT '执行授权的管理员；初始化时可为空',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`user_id`, `role_id`),
  KEY `idx_moo_user_roles_role` (`role_id`),
  KEY `idx_moo_user_roles_granted_by` (`granted_by_user_id`),
  CONSTRAINT `fk_moo_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_moo_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `moo_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_moo_user_roles_granted_by` FOREIGN KEY (`granted_by_user_id`) REFERENCES `moo_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户与角色关系';

INSERT INTO `moo_roles` (`code`, `name`, `description`, `is_system`, `status`)
VALUES ('super_admin', '超级管理员', '管理 OAuth 应用、用户、安全审计及系统配置', 1, 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`), `is_system` = 1, `status` = 'active';

CREATE TABLE IF NOT EXISTS `moo_permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(100) NOT NULL COMMENT '权限稳定标识，例如 admin.users.read',
  `name` VARCHAR(100) NOT NULL COMMENT '权限中文名称',
  `module` VARCHAR(50) NOT NULL COMMENT '权限所属后台模块',
  `description` VARCHAR(500) NULL COMMENT '权限用途和边界说明',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_permissions_code` (`code`),
  KEY `idx_moo_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台细粒度权限定义';

CREATE TABLE IF NOT EXISTS `moo_role_permissions` (
  `role_id` BIGINT UNSIGNED NOT NULL COMMENT '角色内部主键',
  `permission_id` BIGINT UNSIGNED NOT NULL COMMENT '权限内部主键',
  `granted_by_user_id` BIGINT UNSIGNED NULL COMMENT '最后执行授权的管理员；初始化时可为空',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`role_id`, `permission_id`),
  KEY `idx_moo_role_permissions_permission` (`permission_id`),
  KEY `idx_moo_role_permissions_granted_by` (`granted_by_user_id`),
  CONSTRAINT `fk_moo_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `moo_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_moo_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `moo_permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_moo_role_permissions_granted_by` FOREIGN KEY (`granted_by_user_id`) REFERENCES `moo_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色与后台权限关系';

INSERT INTO `moo_permissions` (`code`, `name`, `module`, `description`) VALUES
  ('admin.dashboard.read', '查看工作台', 'dashboard', '查看后台统计数据和运行概况'),
  ('admin.applications.read', '查看应用', 'applications', '查看 OAuth 应用及客户端配置'),
  ('admin.applications.create', '创建应用', 'applications', '创建 OAuth 应用及其初始客户端'),
  ('admin.applications.update', '编辑应用', 'applications', '编辑应用资料、Logo、回调地址和 Scope'),
  ('admin.applications.delete', '删除应用', 'applications', '删除未被依赖的 OAuth 应用'),
  ('admin.applications.status.update', '启用或禁用应用', 'applications', '变更 OAuth 客户端启用状态并影响其 Token 使用'),
  ('admin.applications.secret.rotate', '轮换 AppSecret', 'applications', '轮换 confidential client 的 AppSecret'),
  ('admin.users.read', '查看用户', 'users', '检索用户并查看账号详情'),
  ('admin.users.status.update', '修改用户状态', 'users', '锁定、禁用或恢复用户账号'),
  ('admin.users.sessions.revoke', '强制用户下线', 'users', '撤销用户的有效登录会话'),
  ('admin.audit.read', '查看安全审计', 'audit', '查询在线及归档安全审计记录'),
  ('admin.roles.read', '查看角色权限', 'roles', '查看角色、权限点和成员'),
  ('admin.roles.create', '创建角色', 'roles', '创建新的自定义角色'),
  ('admin.roles.update', '编辑角色资料', 'roles', '编辑角色名称、说明和启用状态'),
  ('admin.roles.delete', '删除角色', 'roles', '删除没有成员的自定义角色'),
  ('admin.roles.permissions.update', '配置角色权限', 'roles', '替换自定义角色拥有的权限点'),
  ('admin.roles.members.manage', '管理角色成员', 'roles', '向用户授予角色或撤销用户角色'),
  ('admin.settings.read', '查看系统设置', 'settings', '查看允许后台管理的系统配置'),
  ('admin.settings.write', '管理系统设置', 'settings', '修改经过白名单约束的系统配置')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `module` = VALUES(`module`), `description` = VALUES(`description`);

INSERT IGNORE INTO `moo_role_permissions` (`role_id`, `permission_id`, `granted_by_user_id`)
SELECT `roles`.`id`, `permissions`.`id`, NULL
FROM `moo_roles` AS `roles`
CROSS JOIN `moo_permissions` AS `permissions`
WHERE `roles`.`code` = 'super_admin';

CREATE TABLE IF NOT EXISTS `moo_user_identities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(32) NOT NULL COMMENT 'local, wechat, github, etc.',
  `provider_subject` VARCHAR(191) NOT NULL,
  `profile` JSON NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_identity_provider_subject` (`provider`, `provider_subject`),
  KEY `idx_moo_identity_user` (`user_id`),
  CONSTRAINT `fk_moo_identity_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_email_verification_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `token_hash` BINARY(32) NOT NULL COMMENT 'SHA-256 of the one-time token',
  `purpose` ENUM('verify_email','change_email') NOT NULL DEFAULT 'verify_email',
  `expires_at` DATETIME(6) NOT NULL,
  `consumed_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_email_tokens_hash` (`token_hash`),
  KEY `idx_moo_email_tokens_user` (`user_id`),
  KEY `idx_moo_email_tokens_expires` (`expires_at`),
  CONSTRAINT `fk_moo_email_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_password_reset_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token_hash` BINARY(32) NOT NULL COMMENT 'SHA-256 of the one-time token',
  `ip_address` VARBINARY(16) NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `consumed_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_password_reset_hash` (`token_hash`),
  KEY `idx_moo_password_reset_user` (`user_id`),
  KEY `idx_moo_password_reset_expires` (`expires_at`),
  CONSTRAINT `fk_moo_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_mfa_methods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('totp','webauthn') NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `encrypted_secret` VARBINARY(1024) NULL COMMENT 'TOTP secret encrypted with a key outside the database',
  `credential_data` JSON NULL COMMENT 'Public WebAuthn credential metadata',
  `enabled_at` DATETIME(6) NULL,
  `last_used_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_moo_mfa_user` (`user_id`),
  CONSTRAINT `fk_moo_mfa_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_user_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_hash` BINARY(32) NOT NULL COMMENT 'SHA-256 of the session token',
  `user_id` BIGINT UNSIGNED NOT NULL,
  `ip_address` VARBINARY(16) NULL,
  `user_agent` VARCHAR(500) NULL,
  `last_seen_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `revoked_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_sessions_hash` (`session_hash`),
  KEY `idx_moo_sessions_user` (`user_id`),
  KEY `idx_moo_sessions_expires` (`expires_at`),
  CONSTRAINT `fk_moo_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NULL,
  `login_identifier_hash` BINARY(32) NULL,
  `ip_address` VARBINARY(16) NULL,
  `user_agent` VARCHAR(500) NULL,
  `succeeded` TINYINT(1) NOT NULL DEFAULT 0,
  `failure_reason` VARCHAR(64) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_moo_login_user_created` (`user_id`, `created_at`),
  KEY `idx_moo_login_identifier_created` (`login_identifier_hash`, `created_at`),
  KEY `idx_moo_login_ip_created` (`ip_address`, `created_at`),
  CONSTRAINT `fk_moo_login_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_applications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(26) NOT NULL COMMENT '应用公开稳定标识 ULID',
  `owner_user_id` BIGINT UNSIGNED NOT NULL COMMENT '创建并管理应用的用户',
  `name` VARCHAR(100) NOT NULL COMMENT '应用显示名称',
  `description` VARCHAR(500) NULL COMMENT '应用用途说明',
  `logo_url` VARCHAR(500) NULL COMMENT '应用图标地址',
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_applications_public_id` (`public_id`),
  KEY `idx_moo_applications_owner` (`owner_user_id`),
  KEY `idx_moo_applications_status` (`status`),
  CONSTRAINT `fk_moo_applications_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `moo_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='逻辑应用，一个应用可包含多个 OAuth 客户端';

CREATE TABLE IF NOT EXISTS `moo_oauth_clients` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `application_id` BIGINT UNSIGNED NULL COMMENT '所属逻辑应用',
  `client_id` VARCHAR(100) NOT NULL COMMENT 'Public AppID',
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(500) NULL,
  `logo_url` VARCHAR(500) NULL,
  `client_type` ENUM('public','confidential') NOT NULL,
  `application_type` ENUM('web','spa','native','service') NOT NULL,
  `token_endpoint_auth_method` ENUM('none','client_secret_basic','client_secret_post','private_key_jwt') NOT NULL DEFAULT 'none',
  `require_pkce` TINYINT(1) NOT NULL DEFAULT 1,
  `require_consent` TINYINT(1) NOT NULL DEFAULT 1,
  `allowed_grant_types` JSON NOT NULL,
  `allowed_response_types` JSON NOT NULL,
  `access_token_ttl` INT UNSIGNED NOT NULL DEFAULT 900,
  `refresh_token_ttl` INT UNSIGNED NOT NULL DEFAULT 2592000,
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `owner_user_id` BIGINT UNSIGNED NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_oauth_clients_client_id` (`client_id`),
  KEY `idx_moo_oauth_clients_owner` (`owner_user_id`),
  KEY `idx_moo_oauth_clients_application` (`application_id`),
  KEY `idx_moo_oauth_clients_status` (`status`),
  CONSTRAINT `fk_moo_oauth_clients_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `moo_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_moo_oauth_clients_application` FOREIGN KEY (`application_id`) REFERENCES `moo_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_oauth_client_secrets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT UNSIGNED NOT NULL,
  `secret_id` VARCHAR(32) NOT NULL COMMENT 'Public key id used during secret rotation',
  `secret_hash` VARCHAR(255) NOT NULL COMMENT 'Password hash; plaintext is shown only once',
  `description` VARCHAR(100) NULL,
  `last_used_at` DATETIME(6) NULL,
  `expires_at` DATETIME(6) NULL,
  `revoked_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_client_secrets_secret_id` (`secret_id`),
  KEY `idx_moo_client_secrets_client` (`client_id`),
  CONSTRAINT `fk_moo_client_secrets_client` FOREIGN KEY (`client_id`) REFERENCES `moo_oauth_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_oauth_client_redirect_uris` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `client_id` BIGINT UNSIGNED NOT NULL,
  `redirect_uri` VARCHAR(1000) NOT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_redirect_client_uri` (`client_id`, `redirect_uri`(500)),
  CONSTRAINT `fk_moo_redirect_client` FOREIGN KEY (`client_id`) REFERENCES `moo_oauth_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;

CREATE TABLE IF NOT EXISTS `moo_oauth_scopes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(500) NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_oauth_scopes_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_oauth_client_scopes` (
  `client_id` BIGINT UNSIGNED NOT NULL,
  `scope_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`client_id`, `scope_id`),
  CONSTRAINT `fk_moo_client_scope_client` FOREIGN KEY (`client_id`) REFERENCES `moo_oauth_clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_moo_client_scope_scope` FOREIGN KEY (`scope_id`) REFERENCES `moo_oauth_scopes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_oauth_consents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `client_id` BIGINT UNSIGNED NOT NULL,
  `scopes` JSON NOT NULL,
  `granted_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `expires_at` DATETIME(6) NULL,
  `revoked_at` DATETIME(6) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_consent_user_client` (`user_id`, `client_id`),
  CONSTRAINT `fk_moo_consent_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_moo_consent_client` FOREIGN KEY (`client_id`) REFERENCES `moo_oauth_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_oauth_pushed_authorization_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_uri_hash` BINARY(32) NOT NULL COMMENT 'SHA-256 of the one-time request_uri',
  `client_id` BIGINT UNSIGNED NOT NULL,
  `parameters` JSON NOT NULL COMMENT 'Validated authorization parameters without client credentials',
  `expires_at` DATETIME(6) NOT NULL,
  `used_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_pushed_auth_requests_hash` (`request_uri_hash`),
  KEY `idx_moo_pushed_auth_requests_expires` (`expires_at`),
  CONSTRAINT `fk_moo_pushed_auth_requests_client` FOREIGN KEY (`client_id`) REFERENCES `moo_oauth_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='短时一次性推送授权请求';

CREATE TABLE IF NOT EXISTS `moo_oauth_authorization_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code_hash` BINARY(32) NOT NULL COMMENT 'SHA-256 of the authorization code',
  `client_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `redirect_uri` VARCHAR(1000) NOT NULL,
  `scopes` JSON NOT NULL,
  `code_challenge` VARCHAR(128) NOT NULL,
  `code_challenge_method` ENUM('S256') NOT NULL DEFAULT 'S256',
  `nonce` VARCHAR(255) NULL COMMENT 'OIDC nonce',
  `auth_time` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `used_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_auth_codes_hash` (`code_hash`),
  KEY `idx_moo_auth_codes_expires` (`expires_at`),
  CONSTRAINT `fk_moo_auth_codes_client` FOREIGN KEY (`client_id`) REFERENCES `moo_oauth_clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_moo_auth_codes_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_oauth_access_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_hash` BINARY(32) NOT NULL COMMENT 'SHA-256 of an opaque access token',
  `client_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NULL COMMENT 'Null for client_credentials tokens',
  `grant_type` ENUM('authorization_code','refresh_token','client_credentials') NOT NULL,
  `scopes` JSON NOT NULL,
  `audience` VARCHAR(255) NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `revoked_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_access_tokens_hash` (`token_hash`),
  KEY `idx_moo_access_tokens_client_user` (`client_id`, `user_id`),
  KEY `idx_moo_access_tokens_expires` (`expires_at`),
  CONSTRAINT `fk_moo_access_tokens_client` FOREIGN KEY (`client_id`) REFERENCES `moo_oauth_clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_moo_access_tokens_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_oauth_refresh_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_hash` BINARY(32) NOT NULL COMMENT 'SHA-256 of the refresh token',
  `family_id` CHAR(26) NOT NULL COMMENT 'ULID shared by one rotation family',
  `parent_id` BIGINT UNSIGNED NULL,
  `access_token_id` BIGINT UNSIGNED NULL,
  `client_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `scopes` JSON NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `used_at` DATETIME(6) NULL,
  `revoked_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_refresh_tokens_hash` (`token_hash`),
  KEY `idx_moo_refresh_family` (`family_id`),
  KEY `idx_moo_refresh_client_user` (`client_id`, `user_id`),
  KEY `idx_moo_refresh_expires` (`expires_at`),
  CONSTRAINT `fk_moo_refresh_parent` FOREIGN KEY (`parent_id`) REFERENCES `moo_oauth_refresh_tokens` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_moo_refresh_access` FOREIGN KEY (`access_token_id`) REFERENCES `moo_oauth_access_tokens` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_moo_refresh_client` FOREIGN KEY (`client_id`) REFERENCES `moo_oauth_clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_moo_refresh_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_oauth_signing_keys` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kid` VARCHAR(100) NOT NULL,
  `algorithm` VARCHAR(20) NOT NULL DEFAULT 'RS256',
  `public_jwk` JSON NOT NULL,
  `encrypted_private_key` MEDIUMBLOB NOT NULL COMMENT 'Encrypt with a key kept outside the database',
  `status` ENUM('active','retiring','retired') NOT NULL DEFAULT 'active',
  `not_before` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_signing_keys_kid` (`kid`),
  KEY `idx_moo_signing_keys_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_oauth_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(100) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `client_id` BIGINT UNSIGNED NULL,
  `ip_address` VARBINARY(16) NULL,
  `user_agent` VARCHAR(500) NULL,
  `request_id` VARCHAR(100) NULL,
  `success` TINYINT(1) NOT NULL DEFAULT 1,
  `details` JSON NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  KEY `idx_moo_audit_event_created` (`event_type`, `created_at`),
  KEY `idx_moo_audit_user_created` (`user_id`, `created_at`),
  KEY `idx_moo_audit_client_created` (`client_id`, `created_at`),
  CONSTRAINT `fk_moo_audit_user` FOREIGN KEY (`user_id`) REFERENCES `moo_users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_moo_audit_client` FOREIGN KEY (`client_id`) REFERENCES `moo_oauth_clients` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `moo_audit_archive_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '归档执行记录主键',
  `archive_month` CHAR(6) NOT NULL COMMENT '归档月份，格式 YYYYMM',
  `status` ENUM('running','completed','failed') NOT NULL COMMENT '本批归档状态',
  `row_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '本批成功归档行数',
  `error_message` VARCHAR(1000) NULL COMMENT '脱敏后的失败原因',
  `started_at` DATETIME(6) NOT NULL COMMENT '开始时间（北京时间）',
  `finished_at` DATETIME(6) NULL COMMENT '结束时间（北京时间）',
  PRIMARY KEY (`id`),
  KEY `idx_moo_audit_archive_month_started` (`archive_month`, `started_at`),
  KEY `idx_moo_audit_archive_status_started` (`status`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='安全审计按月归档执行记录';

CREATE TABLE IF NOT EXISTS `moo_system_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(120) NOT NULL COMMENT '白名单设置键',
  `value_type` VARCHAR(20) NOT NULL COMMENT '值类型 string、integer、boolean',
  `setting_value` TEXT NOT NULL COMMENT '设置值；敏感凭据不得写入此表',
  `description` VARCHAR(500) NOT NULL COMMENT '设置用途说明',
  `version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '乐观锁版本号',
  `updated_by_user_id` BIGINT UNSIGNED NULL COMMENT '最后修改管理员',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_system_settings_key` (`setting_key`),
  CONSTRAINT `fk_moo_system_settings_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `moo_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台白名单系统设置';

INSERT INTO `moo_oauth_scopes` (`name`, `display_name`, `description`, `is_default`)
VALUES
  ('openid', '账号身份', '使用哞哞账号完成 OpenID Connect 身份认证', 1),
  ('profile', '基础资料', '读取用户公开 ID、显示名称和头像', 1),
  ('email', '邮箱地址', '读取用户邮箱地址和验证状态', 0),
  ('realname', '脱敏实名信息', '读取脱敏后的真实姓名、证件类型、证件号码和认证状态', 0),
  ('realname_full', '完整实名信息', '读取完整真实姓名和证件号码，属于高敏感权限', 0),
  ('offline_access', '离线访问', '允许应用获得刷新令牌并持续访问已授权信息', 0),
  ('service', '服务端 API', '允许应用使用 Client Credentials 进行机器间调用', 0)
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `description` = VALUES(`description`);

SET FOREIGN_KEY_CHECKS = 1;

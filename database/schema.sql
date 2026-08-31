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
  KEY `idx_moo_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS `moo_oauth_clients` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
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
  KEY `idx_moo_oauth_clients_status` (`status`),
  CONSTRAINT `fk_moo_oauth_clients_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `moo_users` (`id`) ON DELETE SET NULL
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

INSERT INTO `moo_oauth_scopes` (`name`, `display_name`, `description`, `is_default`)
VALUES
  ('openid', 'OpenID', 'Request an OpenID Connect identity token', 1),
  ('profile', 'Basic profile', 'Read the user public id, display name and avatar', 1),
  ('email', 'Email address', 'Read the user email address and verification state', 0),
  ('offline_access', 'Offline access', 'Allow issuance of a refresh token', 0)
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `description` = VALUES(`description`);

SET FOREIGN_KEY_CHECKS = 1;

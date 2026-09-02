CREATE TABLE IF NOT EXISTS `moo_applications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `public_id` CHAR(26) NOT NULL COMMENT '应用公开稳定标识 ULID',
  `owner_user_id` BIGINT UNSIGNED NOT NULL COMMENT '创建并管理应用的用户',
  `name` VARCHAR(100) NOT NULL COMMENT '应用显示名称',
  `description` VARCHAR(500) NULL COMMENT '应用用途说明',
  `status` ENUM('active','disabled') NOT NULL DEFAULT 'active',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_applications_public_id` (`public_id`),
  KEY `idx_moo_applications_owner` (`owner_user_id`),
  KEY `idx_moo_applications_status` (`status`),
  CONSTRAINT `fk_moo_applications_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `moo_users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='逻辑应用，一个应用可包含多个 OAuth 客户端';

ALTER TABLE `moo_oauth_clients`
  ADD COLUMN `application_id` BIGINT UNSIGNED NULL COMMENT '所属逻辑应用' AFTER `id`,
  ADD KEY `idx_moo_oauth_clients_application` (`application_id`),
  ADD CONSTRAINT `fk_moo_oauth_clients_application` FOREIGN KEY (`application_id`) REFERENCES `moo_applications` (`id`) ON DELETE CASCADE;

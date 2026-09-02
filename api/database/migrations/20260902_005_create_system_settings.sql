SET NAMES utf8mb4;

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
  PRIMARY KEY (`id`), UNIQUE KEY `uk_moo_system_settings_key` (`setting_key`),
  CONSTRAINT `fk_moo_system_settings_user` FOREIGN KEY (`updated_by_user_id`) REFERENCES `moo_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台白名单系统设置';

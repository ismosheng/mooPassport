SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `moo_roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL COMMENT '角色稳定标识，例如 super_admin',
  `name` VARCHAR(100) NOT NULL COMMENT '角色显示名称',
  `description` VARCHAR(500) NULL COMMENT '角色用途及权限边界说明',
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_roles_code` (`code`)
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

INSERT INTO `moo_roles` (`code`, `name`, `description`)
VALUES ('super_admin', '超级管理员', '管理 OAuth 应用、用户、安全审计及系统配置')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `description` = VALUES(`description`);

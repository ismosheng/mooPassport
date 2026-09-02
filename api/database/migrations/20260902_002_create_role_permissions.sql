SET NAMES utf8mb4;

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
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`), `module` = VALUES(`module`), `description` = VALUES(`description`);

INSERT IGNORE INTO `moo_role_permissions` (`role_id`, `permission_id`, `granted_by_user_id`)
SELECT `roles`.`id`, `permissions`.`id`, NULL
FROM `moo_roles` AS `roles`
CROSS JOIN `moo_permissions` AS `permissions`
WHERE `roles`.`code` = 'super_admin';

SET NAMES utf8mb4;

-- 先建立细粒度权限，再把旧角色的宽泛写权限等价展开，避免升级时已有管理员失权。
INSERT INTO `moo_permissions` (`code`, `name`, `module`, `description`) VALUES
  ('admin.applications.create', '创建应用', 'applications', '创建 OAuth 应用及其初始客户端'),
  ('admin.applications.update', '编辑应用', 'applications', '编辑应用资料、Logo、回调地址和 Scope'),
  ('admin.applications.delete', '删除应用', 'applications', '删除未被依赖的 OAuth 应用'),
  ('admin.applications.status.update', '启用或禁用应用', 'applications', '变更 OAuth 客户端启用状态并影响其 Token 使用'),
  ('admin.applications.secret.rotate', '轮换 AppSecret', 'applications', '轮换 confidential client 的 AppSecret'),
  ('admin.users.status.update', '修改用户状态', 'users', '锁定、禁用或恢复用户账号'),
  ('admin.users.sessions.revoke', '强制用户下线', 'users', '撤销用户的有效登录会话'),
  ('admin.roles.create', '创建角色', 'roles', '创建新的自定义角色'),
  ('admin.roles.update', '编辑角色资料', 'roles', '编辑角色名称、说明和启用状态'),
  ('admin.roles.delete', '删除角色', 'roles', '删除没有成员的自定义角色'),
  ('admin.roles.permissions.update', '配置角色权限', 'roles', '替换自定义角色拥有的权限点'),
  ('admin.roles.members.manage', '管理角色成员', 'roles', '向用户授予角色或撤销用户角色')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`), `module` = VALUES(`module`), `description` = VALUES(`description`);

INSERT IGNORE INTO `moo_role_permissions` (`role_id`, `permission_id`, `granted_by_user_id`)
SELECT `existing`.`role_id`, `replacement`.`id`, `existing`.`granted_by_user_id`
FROM `moo_role_permissions` AS `existing`
JOIN `moo_permissions` AS `legacy` ON `legacy`.`id` = `existing`.`permission_id`
JOIN `moo_permissions` AS `replacement` ON (
  (`legacy`.`code` = 'admin.applications.write' AND `replacement`.`code` IN (
    'admin.applications.create', 'admin.applications.update', 'admin.applications.delete',
    'admin.applications.status.update', 'admin.applications.secret.rotate'
  )) OR
  (`legacy`.`code` = 'admin.users.write' AND `replacement`.`code` IN (
    'admin.users.status.update', 'admin.users.sessions.revoke'
  )) OR
  (`legacy`.`code` = 'admin.roles.write' AND `replacement`.`code` IN (
    'admin.roles.create', 'admin.roles.update', 'admin.roles.delete',
    'admin.roles.permissions.update', 'admin.roles.members.manage'
  ))
);

-- 旧权限已完成一对多迁移；删除定义可防止角色编辑器继续展示失效选项。
DELETE FROM `moo_permissions`
WHERE `code` IN ('admin.applications.write', 'admin.users.write', 'admin.roles.write');

SET NAMES utf8mb4;

ALTER TABLE `moo_roles`
  ADD COLUMN `is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否系统内置角色；内置角色禁止删除' AFTER `description`,
  ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT '角色状态：active 或 disabled' AFTER `is_system`,
  ADD COLUMN `version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '乐观锁版本号，防止并发编辑覆盖' AFTER `status`,
  ADD KEY `idx_moo_roles_status` (`status`);

UPDATE `moo_roles`
SET `is_system` = 1, `status` = 'active'
WHERE `code` = 'super_admin';

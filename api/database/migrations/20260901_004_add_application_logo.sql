ALTER TABLE `moo_applications`
  ADD COLUMN `logo_url` VARCHAR(500) NULL COMMENT '应用图标地址' AFTER `description`;

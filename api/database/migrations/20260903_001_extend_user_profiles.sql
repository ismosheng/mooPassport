ALTER TABLE `moo_users`
  ADD COLUMN `gender` ENUM('male','female','other','undisclosed') NULL AFTER `avatar_url`,
  ADD COLUMN `birth_date` DATE NULL AFTER `gender`,
  ADD COLUMN `bio` VARCHAR(500) NULL AFTER `birth_date`,
  ADD COLUMN `real_name_encrypted` VARBINARY(1024) NULL AFTER `bio`,
  ADD COLUMN `identity_document_type` ENUM('id_card','passport','other') NULL AFTER `real_name_encrypted`,
  ADD COLUMN `identity_document_number_encrypted` VARBINARY(1024) NULL AFTER `identity_document_type`,
  ADD COLUMN `identity_document_number_hash` BINARY(32) NULL AFTER `identity_document_number_encrypted`,
  ADD COLUMN `realname_status` ENUM('unverified','pending','verified','rejected') NOT NULL DEFAULT 'unverified' AFTER `identity_document_number_hash`,
  ADD COLUMN `realname_verified_at` DATETIME(6) NULL AFTER `realname_status`,
  ADD UNIQUE KEY `uk_moo_users_identity_document_hash` (`identity_document_number_hash`),
  ADD KEY `idx_moo_users_realname_status` (`realname_status`);

INSERT INTO `moo_oauth_scopes` (`name`, `display_name`, `description`, `is_default`, `status`) VALUES
  ('realname', '脱敏实名信息', '读取脱敏后的真实姓名、证件类型、证件号码和认证状态', 0, 'active'),
  ('realname_full', '完整实名信息', '读取完整真实姓名和证件号码，属于高敏感权限', 0, 'active')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `description` = VALUES(`description`),
  `is_default` = 0,
  `status` = 'active';

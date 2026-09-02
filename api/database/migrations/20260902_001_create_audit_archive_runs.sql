CREATE TABLE IF NOT EXISTS `moo_audit_archive_runs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '归档执行记录主键',
  `archive_month` CHAR(6) NOT NULL COMMENT '归档月份，格式 YYYYMM',
  `status` ENUM('running','completed','failed') NOT NULL COMMENT '本批归档状态',
  `row_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '本批成功归档行数',
  `error_message` VARCHAR(1000) NULL COMMENT '脱敏后的失败原因',
  `started_at` DATETIME(6) NOT NULL COMMENT '开始时间（UTC）',
  `finished_at` DATETIME(6) NULL COMMENT '结束时间（UTC）',
  PRIMARY KEY (`id`),
  KEY `idx_moo_audit_archive_month_started` (`archive_month`, `started_at`),
  KEY `idx_moo_audit_archive_status_started` (`status`, `started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='安全审计按月归档执行记录';

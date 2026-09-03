CREATE TABLE IF NOT EXISTS `moo_oauth_pushed_authorization_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `request_uri_hash` BINARY(32) NOT NULL COMMENT 'SHA-256 of the one-time request_uri',
  `client_id` BIGINT UNSIGNED NOT NULL,
  `parameters` JSON NOT NULL COMMENT 'Validated authorization parameters without client credentials',
  `expires_at` DATETIME(6) NOT NULL,
  `used_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_moo_pushed_auth_requests_hash` (`request_uri_hash`),
  KEY `idx_moo_pushed_auth_requests_expires` (`expires_at`),
  CONSTRAINT `fk_moo_pushed_auth_requests_client` FOREIGN KEY (`client_id`) REFERENCES `moo_oauth_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='短时一次性推送授权请求';

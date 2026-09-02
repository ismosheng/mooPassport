INSERT INTO `moo_oauth_scopes` (`name`, `display_name`, `description`, `is_default`, `status`)
VALUES ('service', '服务端 API', '允许应用使用 Client Credentials 进行机器间调用', 0, 'active')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `description` = VALUES(`description`),
  `status` = 'active';

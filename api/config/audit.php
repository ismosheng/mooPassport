<?php

declare(strict_types=1);

return [
    'hot_retention_days' => max(31, (int) (getenv('AUDIT_HOT_RETENTION_DAYS') ?: 90)),
    'archive_batch_size' => max(100, min(10000, (int) (getenv('AUDIT_ARCHIVE_BATCH_SIZE') ?: 5000))),
    'archive_max_batches' => max(1, min(100, (int) (getenv('AUDIT_ARCHIVE_MAX_BATCHES') ?: 20))),
    'archive_interval' => max(3600, (int) (getenv('AUDIT_ARCHIVE_INTERVAL') ?: 86400)),
    'cold_retention_days' => max(365, (int) (getenv('AUDIT_COLD_RETENTION_DAYS') ?: 1095)),
    // 冷数据删除是不可恢复操作，必须由运维显式启用。
    'archive_delete_enabled' => filter_var(getenv('AUDIT_ARCHIVE_DELETE_ENABLED') ?: 'false', FILTER_VALIDATE_BOOL),
];

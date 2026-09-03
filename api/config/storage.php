<?php

declare(strict_types=1);

return [
    'default' => getenv('STORAGE_DRIVER') ?: 'local',
    'qiniu' => [
        'access_key' => getenv('QINIU_ACCESS_KEY') ?: '',
        'secret_key' => getenv('QINIU_SECRET_KEY') ?: '',
        'bucket' => getenv('QINIU_BUCKET') ?: '',
        'domain' => getenv('QINIU_DOMAIN') ?: '',
        'prefix' => getenv('QINIU_PREFIX') ?: 'moo-passport',
    ],
];

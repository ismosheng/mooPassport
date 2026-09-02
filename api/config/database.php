<?php

declare(strict_types=1);

return [
    'default' => getenv('DB_CONNECTION') ?: 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => getenv('DB_HOST') ?: '127.0.0.1',
            'port'        => (int) (getenv('DB_PORT') ?: 3306),
            'database'    => getenv('DB_DATABASE') ?: 'moopassport',
            'username'    => getenv('DB_USERNAME') ?: 'moo_passport',
            'password'    => getenv('DB_PASSWORD') ?: '',
            'charset'     => getenv('DB_CHARSET') ?: 'utf8mb4',
            'collation'   => getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false, // Must be false for Swoole and Swow drivers.
            ],
            'pool' => [
                'max_connections' => 5,
                'min_connections' => 1,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
    ],
];

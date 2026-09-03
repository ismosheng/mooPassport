<?php

declare(strict_types=1);

return [
    // 环回地址覆盖同机 Nginx；其他反向代理仍必须由部署环境显式配置。
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', getenv('TRUSTED_PROXIES') ?: '127.0.0.1,::1'),
    ))),
    'cors' => [
        'allowed_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', getenv('CORS_ALLOWED_ORIGINS') ?: ''),
        ))),
        'allowed_methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'allowed_headers' => 'Accept, Authorization, Content-Type, X-Request-ID',
        'max_age' => 600,
    ],
];

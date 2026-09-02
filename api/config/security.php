<?php

declare(strict_types=1);

return [
    // 只有这些反向代理的转发头可信；留空时始终使用直连 IP。
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', getenv('TRUSTED_PROXIES') ?: ''),
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

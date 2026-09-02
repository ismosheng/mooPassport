<?php

declare(strict_types=1);

return [
    'cookie' => [
        'name' => getenv('AUTH_COOKIE') ?: 'moo_auth_session',
        'max_age' => (int) (getenv('SESSION_LIFETIME') ?: 604800),
        'domain' => getenv('AUTH_COOKIE_DOMAIN') ?: '',
        'secure' => filter_var(getenv('SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOL),
        'same_site' => getenv('SESSION_SAME_SITE') ?: 'Lax',
    ],
];

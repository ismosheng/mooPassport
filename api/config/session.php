<?php

declare(strict_types=1);

/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Webman\Session\FileSessionHandler;
use Webman\Session\RedisSessionHandler;
use Webman\Session\RedisClusterSessionHandler;

$sessionDriver = getenv('SESSION_DRIVER') ?: 'file';
$sessionHandler = match ($sessionDriver) {
    'redis' => RedisSessionHandler::class,
    'redis_cluster' => RedisClusterSessionHandler::class,
    default => FileSessionHandler::class,
};

return [

    'type' => $sessionDriver,

    'handler' => $sessionHandler,

    'config' => [
        'file' => [
            'save_path' => runtime_path() . '/sessions',
        ],
        'redis' => [
            'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('REDIS_PORT') ?: 6379),
            'auth' => getenv('REDIS_PASSWORD') ?: '',
            'timeout' => 2,
            'database' => (int) (getenv('REDIS_DATABASE') ?: 0),
            'prefix' => 'redis_session_',
        ],
        'redis_cluster' => [
            'host' => ['127.0.0.1:7000', '127.0.0.1:7001', '127.0.0.1:7001'],
            'timeout' => 2,
            'auth' => '',
            'prefix' => 'redis_session_',
        ]
    ],

    'session_name' => getenv('SESSION_COOKIE') ?: 'moo_passport_session',
    
    'auto_update_timestamp' => false,

    'lifetime' => (int) (getenv('SESSION_LIFETIME') ?: 604800),

    'cookie_lifetime' => 365*24*60*60,

    'cookie_path' => '/',

    'domain' => '',
    
    'http_only' => true,

    'secure' => filter_var(getenv('SESSION_SECURE') ?: 'false', FILTER_VALIDATE_BOOL),
    
    'same_site' => getenv('SESSION_SAME_SITE') ?: 'Lax',

    'gc_probability' => [1, 1000],

];

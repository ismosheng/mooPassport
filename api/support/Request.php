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

namespace support;

use app\common\support\TrustedProxy;

/**
 * Class Request
 * @package support
 */
/** 根据显式可信代理配置提供不可伪造的客户端 IP。 */
class Request extends \Webman\Http\Request
{
    public function getRealIp(bool $safeMode = true): string
    {
        /** @var list<string> $trustedProxies */
        $trustedProxies = (array) config('security.trusted_proxies', []);
        $forwardedFor = $this->header('X-Forwarded-For');
        $realIp = $this->header('X-Real-IP');

        return (new TrustedProxy($trustedProxies))->resolve(
            $this->getRemoteIp(),
            is_string($forwardedFor) ? $forwardedFor : null,
            is_string($realIp) ? $realIp : null,
        );
    }
}

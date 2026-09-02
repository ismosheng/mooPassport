<?php

declare(strict_types=1);

namespace app\common\middleware;

use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 为全部动态响应设置浏览器安全策略，并处理受控的跨域预检请求。
 *
 * OAuth 协议内容仍由控制器生成；本中间件只附加传输层响应头。
 */
final class HttpSecurity implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $origin = $request->header('Origin');
        $allowedOrigin = is_string($origin) && $this->originAllowed($origin) ? $origin : null;

        if (strtoupper($request->method()) === 'OPTIONS' && $allowedOrigin !== null) {
            $response = response('', 204);
        } else {
            $response = $handler($request);
        }

        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'DENY');
        $response->header('Referrer-Policy', 'no-referrer');
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->header('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'");

        if ($allowedOrigin !== null) {
            $response->header('Access-Control-Allow-Origin', $allowedOrigin);
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Allow-Methods', (string) config('security.cors.allowed_methods'));
            $response->header('Access-Control-Allow-Headers', (string) config('security.cors.allowed_headers'));
            $response->header('Access-Control-Max-Age', (string) config('security.cors.max_age'));
            $response->header('Vary', 'Origin');
        }

        return $response;
    }

    private function originAllowed(string $origin): bool
    {
        /** @var list<string> $origins */
        $origins = (array) config('security.cors.allowed_origins', []);

        // 携带 Cookie 的认证请求不能安全使用通配符，必须精确匹配 Origin。
        return in_array($origin, $origins, true);
    }
}

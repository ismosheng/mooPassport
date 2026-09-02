<?php

declare(strict_types=1);

namespace app\passport\middleware;

use app\common\exception\BusinessException;
use app\passport\service\SessionAuthenticationService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/** 尝试恢复浏览器登录态，但允许未登录请求继续进入协议参数校验。 */
final class ResolveSession implements MiddlewareInterface
{
    public function __construct(private readonly SessionAuthenticationService $authentication)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        $cookie = $request->cookie((string) config('auth.cookie.name'));
        if (is_string($cookie) && $cookie !== '') {
            try {
                $request->context[AuthenticateSession::CONTEXT_KEY] = $this->authentication->authenticate($cookie);
            } catch (BusinessException) {
                // 无效或过期 Cookie 按未登录处理，不影响 OAuth 请求参数的优先校验。
            }
        }

        return $handler($request);
    }
}

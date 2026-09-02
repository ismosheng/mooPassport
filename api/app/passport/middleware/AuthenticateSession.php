<?php

declare(strict_types=1);

namespace app\passport\middleware;

use app\passport\service\SessionAuthenticationService;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/** 要求被标记的接口必须具有有效的通行证浏览器会话。 */
final class AuthenticateSession implements MiddlewareInterface
{
    public const CONTEXT_KEY = 'passport_identity';

    public function __construct(private readonly SessionAuthenticationService $authentication)
    {
    }

    public function process(Request $request, callable $handler): Response
    {
        $cookie = $request->cookie((string) config('auth.cookie.name'));
        $request->context[self::CONTEXT_KEY] = $this->authentication->authenticate(
            is_string($cookie) ? $cookie : null,
        );

        return $handler($request);
    }
}

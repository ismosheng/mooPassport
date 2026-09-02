<?php

declare(strict_types=1);

namespace app\passport\controller;

use app\common\enum\UserStatus;
use app\common\support\ApiResponse;
use app\passport\dto\LoginInput;
use app\passport\dto\RegisterInput;
use app\passport\service\LoginService;
use app\passport\service\RegisterService;
use app\passport\validator\LoginValidator;
use app\passport\validator\RegisterValidator;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/**
 * 提供浏览器账号注册与登录接口。
 *
 * 凭据校验和会话创建由应用服务负责，控制器只转换 HTTP 输入与输出。
 */
#[DisableDefaultRoute]
#[RouteGroup('/passport/v1')]
final class AuthController
{
    public function __construct(
        private readonly RegisterService $registerService,
        private readonly LoginService $loginService,
    ) {
    }

    #[Post('/register', 'passport.v1.auth.register')]
    public function register(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = RegisterValidator::make((array) $request->post())->validate();
        $userAgent = $request->header('User-Agent');

        $result = $this->registerService->register(new RegisterInput(
            username: (string) $data['username'],
            email: (string) $data['email'],
            password: (string) $data['password'],
            displayName: (string) ($data['display_name'] ?? $data['username']),
            ipAddress: $request->getRealIp(),
            userAgent: is_string($userAgent) ? $userAgent : null,
        ));

        return ApiResponse::success($request, [
            'user' => [
                'id' => $result->user->public_id,
                'username' => $result->user->username,
                'email' => $result->user->email,
                'display_name' => $result->user->display_name,
                'status' => $this->statusValue($result->user->status),
            ],
            'email_verification_required' => true,
        ], 201);
    }

    #[Post('/login', 'passport.v1.auth.login')]
    public function login(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = LoginValidator::make((array) $request->post())->validate();
        $userAgent = $request->header('User-Agent');

        $result = $this->loginService->login(new LoginInput(
            identifier: (string) $data['identifier'],
            password: (string) $data['password'],
            ipAddress: $request->getRealIp(),
            userAgent: is_string($userAgent) ? $userAgent : null,
        ));

        $response = ApiResponse::success($request, [
            'user' => [
                'id' => $result->user->public_id,
                'username' => $result->user->username,
                'email' => $result->user->email,
                'display_name' => $result->user->display_name,
                'status' => $this->statusValue($result->user->status),
            ],
        ]);

        // 原始会话令牌只能通过受保护的 HttpOnly Cookie 返回。
        return $response->cookie(
            (string) config('auth.cookie.name'),
            $result->sessionToken,
            (int) config('auth.cookie.max_age'),
            '/',
            (string) config('auth.cookie.domain'),
            (bool) config('auth.cookie.secure'),
            true,
            (string) config('auth.cookie.same_site'),
        );
    }

    private function statusValue(UserStatus|string $status): string
    {
        return $status instanceof UserStatus ? $status->value : $status;
    }
}

<?php

declare(strict_types=1);

namespace app\passport\controller;

use app\common\exception\BusinessException;
use app\common\support\ApiResponse;
use app\passport\dto\AuthenticatedSession;
use app\passport\middleware\AuthenticateSession;
use app\passport\service\PasswordService;
use app\passport\validator\ChangePasswordValidator;
use app\passport\validator\ForgotPasswordValidator;
use app\passport\validator\ResetPasswordValidator;
use support\annotation\Middleware;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供密码重置申请、一次性重置和登录后修改密码接口。 */
#[DisableDefaultRoute]
#[RouteGroup('/passport/v1/password')]
final class PasswordController
{
    public function __construct(private readonly PasswordService $passwords)
    {
    }

    #[Post('/forgot', 'passport.v1.password.forgot')]
    public function forgot(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = ForgotPasswordValidator::make((array) $request->post())->validate();
        $this->passwords->requestReset((string) $data['email'], $request->getRealIp());

        return ApiResponse::success($request, [
            'message' => '如果该邮箱可以重置密码，我们已发送重置邮件。',
        ]);
    }

    #[Post('/reset', 'passport.v1.password.reset')]
    public function reset(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = ResetPasswordValidator::make((array) $request->post())->validate();
        $this->passwords->reset(
            (string) $data['token'],
            (string) $data['password'],
            $request->getRealIp(),
        );

        return ApiResponse::success($request, ['message' => '密码已重置，请重新登录。']);
    }

    #[Post('/change', 'passport.v1.password.change')]
    #[Middleware(AuthenticateSession::class)]
    public function change(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = ChangePasswordValidator::make((array) $request->post())->validate();
        $this->passwords->change(
            $this->identity($request)->user,
            (string) $data['current_password'],
            (string) $data['password'],
            $request->getRealIp(),
        );

        return ApiResponse::success($request, ['message' => '密码已修改，请重新登录。'])->cookie(
            (string) config('auth.cookie.name'), '', -1, '/',
            (string) config('auth.cookie.domain'),
            (bool) config('auth.cookie.secure'),
            true,
            (string) config('auth.cookie.same_site'),
        );
    }

    private function identity(Request $request): AuthenticatedSession
    {
        $identity = $request->context[AuthenticateSession::CONTEXT_KEY] ?? null;
        if (!$identity instanceof AuthenticatedSession) {
            throw new BusinessException('unauthenticated', '请先登录。', 401);
        }

        return $identity;
    }
}

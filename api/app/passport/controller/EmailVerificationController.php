<?php

declare(strict_types=1);

namespace app\passport\controller;

use app\common\enum\UserStatus;
use app\common\support\ApiResponse;
use app\passport\service\EmailVerificationService;
use app\passport\validator\ResendVerificationValidator;
use app\passport\validator\VerifyEmailValidator;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Post;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/** 将邮箱验证 HTTP 请求转换为应用服务调用。 */
#[DisableDefaultRoute]
#[RouteGroup('/passport/v1/email')]
final class EmailVerificationController
{
    public function __construct(private readonly EmailVerificationService $service)
    {
    }

    #[Post('/verify', 'passport.v1.email.verify')]
    public function verify(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = VerifyEmailValidator::make((array) $request->post())->validate();
        $user = $this->service->verify((string) $data['token']);

        return ApiResponse::success($request, [
            'user' => [
                'id' => $user->public_id,
                'email' => $user->email,
                'status' => $user->status instanceof UserStatus ? $user->status->value : $user->status,
                'email_verified_at' => $user->email_verified_at?->format(DATE_ATOM),
            ],
        ]);
    }

    #[Post('/resend', 'passport.v1.email.resend')]
    public function resend(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = ResendVerificationValidator::make((array) $request->post())->validate();
        $this->service->resend((string) $data['email']);

        return ApiResponse::success($request, [
            'message' => '如果该邮箱可验证，我们已发送新的验证邮件。',
        ]);
    }
}

<?php

declare(strict_types=1);

namespace app\passport\controller;

use app\common\enum\UserStatus;
use app\common\exception\BusinessException;
use app\common\support\ApiResponse;
use app\common\service\RoleService;
use app\passport\dto\AuthenticatedSession;
use app\passport\middleware\AuthenticateSession;
use app\passport\service\ProfileService;
use app\passport\service\SessionAuthenticationService;
use app\passport\validator\UpdateProfileValidator;
use support\annotation\Middleware;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供当前已登录通行证账号的相关接口。 */
#[DisableDefaultRoute]
#[RouteGroup('/passport/v1')]
#[Middleware(AuthenticateSession::class)]
final class AccountController
{
    public function __construct(
        private readonly SessionAuthenticationService $authentication,
        private readonly ProfileService $profile,
        private readonly RoleService $roles,
    ) {
    }

    #[Get('/me', 'passport.v1.account.me')]
    public function me(Request $request): Response
    {
        return ApiResponse::success($request, [
            'user' => $this->serializeUser($this->identity($request)->user),
        ]);
    }

    #[Put('/profile', 'passport.v1.account.profile.update')]
    public function updateProfile(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = UpdateProfileValidator::make((array) $request->post())->validate();
        $user = $this->profile->updateDisplayName(
            $this->identity($request)->user,
            (string) $data['display_name'],
            $request->getRealIp(),
        );

        return ApiResponse::success($request, [
            'user' => $this->serializeUser($user),
            'message' => '个人资料已更新。',
        ]);
    }

    #[Post('/logout', 'passport.v1.account.logout')]
    public function logout(Request $request): Response
    {
        $userAgent = $request->header('User-Agent');
        $this->authentication->logout(
            $this->identity($request),
            $request->getRealIp(),
            is_string($userAgent) ? $userAgent : null,
        );

        return ApiResponse::success($request)->cookie(
            (string) config('auth.cookie.name'),
            '',
            -1,
            '/',
            (string) config('auth.cookie.domain'),
            (bool) config('auth.cookie.secure'),
            true,
            (string) config('auth.cookie.same_site'),
        );
    }

    /** @return array<string, mixed> */
    private function serializeUser(\app\common\model\User $user): array
    {
        return [
            'id' => $user->public_id,
            'username' => $user->username,
            'email' => $user->email,
            'display_name' => $user->display_name,
            'status' => $user->status instanceof UserStatus ? $user->status->value : $user->status,
            'email_verified_at' => $user->email_verified_at?->format(DATE_ATOM),
            'created_at' => $user->created_at?->format(DATE_ATOM),
            'roles' => $this->roles->codesForUser($user->id),
        ];
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

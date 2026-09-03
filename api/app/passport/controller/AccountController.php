<?php

declare(strict_types=1);

namespace app\passport\controller;

use app\common\enum\UserStatus;
use app\common\exception\BusinessException;
use app\common\support\ApiResponse;
use app\common\service\RoleService;
use app\common\service\UserSensitiveDataService;
use app\passport\dto\AuthenticatedSession;
use app\passport\dto\UpdateProfileInput;
use app\passport\middleware\AuthenticateSession;
use app\passport\service\ProfileAvatarService;
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
use Webman\Http\UploadFile;

/** 提供当前已登录通行证账号的相关接口。 */
#[DisableDefaultRoute]
#[RouteGroup('/passport/v1')]
#[Middleware(AuthenticateSession::class)]
final class AccountController
{
    public function __construct(
        private readonly SessionAuthenticationService $authentication,
        private readonly ProfileService $profile,
        private readonly ProfileAvatarService $avatars,
        private readonly RoleService $roles,
        private readonly UserSensitiveDataService $sensitiveData,
    ) {
    }

    #[Get('/me', 'passport.v1.account.me')]
    public function me(Request $request): Response
    {
        return ApiResponse::success($request, [
            'user' => $this->serializeUser($this->identity($request)->user),
        ]);
    }

    #[Get('/avatar/default', 'passport.v1.account.avatar.default')]
    public function defaultAvatar(Request $request): Response
    {
        $user = $this->identity($request)->user;
        $displayName = trim((string) $user->display_name);
        $label = mb_substr($displayName !== '' ? $displayName : (string) $user->username, 0, 1);
        $label = htmlspecialchars($label, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160"><rect width="160" height="160" rx="80" fill="#2f80ed"/><text x="80" y="88" fill="#fff" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="72" font-weight="600">%s</text></svg>',
            $label,
        );

        return response($svg, 200)->withHeader('Content-Type', 'image/svg+xml; charset=UTF-8')->withHeader('Cache-Control', 'private, max-age=3600');
    }
    #[Put('/profile', 'passport.v1.account.profile.update')]
    public function updateProfile(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = UpdateProfileValidator::make((array) $request->post())->validate();
        $user = $this->profile->updateProfile(
            $this->identity($request)->user,
            new UpdateProfileInput(
                (string) $data['display_name'],
                isset($data['phone_country_code']) ? (string) $data['phone_country_code'] : null,
                isset($data['phone_number']) ? (string) $data['phone_number'] : null,
                isset($data['gender']) ? (string) $data['gender'] : null,
                isset($data['birth_date']) ? (string) $data['birth_date'] : null,
                isset($data['bio']) ? (string) $data['bio'] : null,
                isset($data['real_name']) ? (string) $data['real_name'] : null,
                isset($data['identity_document_type']) ? (string) $data['identity_document_type'] : null,
                isset($data['identity_document_number']) ? (string) $data['identity_document_number'] : null,
            ),
            $request->getRealIp(),
        );

        return ApiResponse::success($request, [
            'user' => $this->serializeUser($user),
            'message' => '个人资料已更新。',
        ]);
    }

    #[Post('/profile/avatar', 'passport.v1.account.profile.avatar')]
    public function uploadAvatar(Request $request): Response
    {
        $file = $request->file('avatar');
        if (!$file instanceof UploadFile) {
            throw new BusinessException('profile_avatar_required', '请选择头像文件。', 422);
        }

        $user = $this->avatars->store($this->identity($request)->user, $file, $request->getRealIp());
        return ApiResponse::success($request, [
            'user' => $this->serializeUser($user),
            'message' => '头像已更新。',
        ], 201);
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
            'avatar_url' => $user->avatar_url ?: '/passport/v1/avatar/default',
            'phone_country_code' => $user->phone_country_code,
            'phone_number' => $user->phone_number,
            'gender' => $user->gender,
            'birth_date' => $user->birth_date?->format('Y-m-d'),
            'bio' => $user->bio,
            'status' => $user->status instanceof UserStatus ? $user->status->value : $user->status,
            'email_verified_at' => $user->email_verified_at?->format(DATE_ATOM),
            'phone_verified_at' => $user->phone_verified_at?->format(DATE_ATOM),
            'created_at' => $user->created_at?->format(DATE_ATOM),
            'roles' => $this->roles->codesForUser($user->id),
            ...$this->sensitiveData->ownerView($user),
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

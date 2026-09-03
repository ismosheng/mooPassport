<?php

declare(strict_types=1);

namespace app\oauth\controller;

use app\common\service\UserSensitiveDataService;
use app\oauth\dto\AccessTokenIdentity;
use app\oauth\middleware\AuthenticateAccessToken;
use support\annotation\Middleware;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use Webman\Http\Request;
use Webman\Http\Response;

/** 根据 Access Token 的 Scope 返回最小化的 OIDC 用户信息。 */
#[DisableDefaultRoute]
#[Middleware(AuthenticateAccessToken::class)]
final class UserInfoController
{
    public function __construct(private readonly UserSensitiveDataService $sensitiveData)
    {
    }

    #[Get('/oauth/userinfo', 'oauth.userinfo.get')]
    public function get(Request $request): Response
    {
        return $this->respond($request);
    }

    #[Post('/oauth/userinfo', 'oauth.userinfo.post')]
    public function post(Request $request): Response
    {
        return $this->respond($request);
    }

    private function respond(Request $request): Response
    {
        $identity = $request->context[AuthenticateAccessToken::CONTEXT_KEY] ?? null;
        if (!$identity instanceof AccessTokenIdentity) {
            return $this->invalidToken();
        }
        if (!$identity->hasScope('openid')) {
            return json([
                'error' => 'insufficient_scope',
                'error_description' => '访问 UserInfo 需要 openid Scope。',
            ])->withStatus(403)
                ->withHeader(
                    'WWW-Authenticate',
                    'Bearer realm="oauth-resource", error="insufficient_scope", scope="openid"',
                )
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Pragma', 'no-cache');
        }

        $user = $identity->user;
        $claims = ['sub' => $user->public_id];
        if ($identity->hasScope('profile')) {
            $claims['name'] = $user->display_name;
            $claims['preferred_username'] = $user->username;
            if ($user->avatar_url !== null) {
                $claims['picture'] = $user->avatar_url;
            }
        }
        if ($identity->hasScope('email')) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }
        if ($identity->hasScope('realname_full')) {
            $claims = [...$claims, ...$this->sensitiveData->oauthClaims($user, true)];
        } elseif ($identity->hasScope('realname')) {
            $claims = [...$claims, ...$this->sensitiveData->oauthClaims($user, false)];
        }

        return json($claims)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }

    private function invalidToken(): Response
    {
        return json([
            'error' => 'invalid_token',
            'error_description' => 'Access Token 无效或已过期。',
        ])->withStatus(401)
            ->withHeader('WWW-Authenticate', 'Bearer realm="oauth-resource", error="invalid_token"')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Pragma', 'no-cache');
    }
}

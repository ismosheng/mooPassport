<?php

declare(strict_types=1);

namespace app\oauth\controller;

use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供无需通行证会话即可读取的 OIDC 默认头像。 */
#[DisableDefaultRoute]
final class DefaultAvatarController
{
    #[Get('/oauth/avatar/default', 'oauth.avatar.default')]
    public function get(Request $request): Response
    {
        $value = trim((string) $request->get('label', 'U'));
        $label = mb_substr($value !== '' ? $value : 'U', 0, 1);
        $label = htmlspecialchars($label, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160"><rect width="160" height="160" rx="80" fill="#2f80ed"/><text x="80" y="88" fill="#fff" text-anchor="middle" dominant-baseline="middle" font-family="Arial, sans-serif" font-size="72" font-weight="600">%s</text></svg>',
            $label,
        );

        return response($svg, 200)
            ->withHeader('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->withHeader('Cache-Control', 'public, max-age=3600')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}

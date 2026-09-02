<?php

declare(strict_types=1);

namespace app\passport\dto;

use app\common\model\User;
use app\common\model\UserSession;

/** 绑定到单次已认证 HTTP 请求的不可变身份信息。 */
final readonly class AuthenticatedSession
{
    public function __construct(
        public User $user,
        public UserSession $session,
    ) {
    }
}

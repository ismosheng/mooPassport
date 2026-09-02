<?php

declare(strict_types=1);

namespace app\admin\repository\contract;

use app\common\model\User;

/** 定义后台用户检索和状态持久化边界，不处理账号安全工作流。 */
interface UserManagementRepositoryInterface
{
    /** @return array{items:list<User>,total:int,roles:array<int,list<string>>} */
    public function search(string $keyword, ?string $status, ?bool $emailVerified, int $page, int $perPage): array;

    public function findByPublicId(string $publicId): ?User;

    public function save(User $user): void;

    /** @return array{roles:list<string>,active_sessions:int,active_consents:int,owned_applications:int,failed_logins_30d:int} */
    public function statistics(int $userId): array;
}

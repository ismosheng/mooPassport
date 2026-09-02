<?php

declare(strict_types=1);

namespace app\admin\repository\eloquent;

use app\admin\repository\contract\UserManagementRepositoryInterface;
use app\common\model\User;
use support\Db;

/** 使用 Eloquent 提供后台专用用户分页与角色聚合查询。 */
final class UserManagementRepository implements UserManagementRepositoryInterface
{
    public function search(string $keyword, ?string $status, ?bool $emailVerified, int $page, int $perPage): array
    {
        $query = User::query();
        if ($keyword !== '') {
            $query->where(function ($query) use ($keyword): void {
                $query->where('username', 'like', '%' . $keyword . '%')
                    ->orWhere('email', 'like', '%' . $keyword . '%')
                    ->orWhere('display_name', 'like', '%' . $keyword . '%')
                    ->orWhere('public_id', $keyword);
            });
        }
        if ($status !== null) $query->where('status', $status);
        if ($emailVerified !== null) {
            $emailVerified ? $query->whereNotNull('email_verified_at') : $query->whereNull('email_verified_at');
        }
        $total = (clone $query)->count();
        /** @var list<User> $items */
        $items = $query->orderByDesc('id')->forPage($page, $perPage)->get()->all();
        $ids = array_map(static fn (User $user): int => $user->id, $items);
        $roles = [];
        if ($ids !== []) {
            foreach (Db::table('moo_user_roles')->join('moo_roles', 'moo_roles.id', '=', 'moo_user_roles.role_id')->whereIn('moo_user_roles.user_id', $ids)->get(['moo_user_roles.user_id', 'moo_roles.code']) as $row) {
                $roles[(int) $row->user_id][] = (string) $row->code;
            }
        }
        return ['items' => $items, 'total' => $total, 'roles' => $roles];
    }

    public function findByPublicId(string $publicId): ?User
    {
        $user = User::query()->where('public_id', $publicId)->first();
        return $user instanceof User ? $user : null;
    }

    public function save(User $user): void
    {
        $user->saveOrFail();
    }

    public function statistics(int $userId): array
    {
        $now = new \DateTimeImmutable();
        $roles = Db::table('moo_user_roles')->join('moo_roles', 'moo_roles.id', '=', 'moo_user_roles.role_id')
            ->where('moo_user_roles.user_id', $userId)->pluck('moo_roles.code')->map(static fn ($code): string => (string) $code)->all();
        return [
            'roles' => array_values($roles),
            'active_sessions' => Db::table('moo_user_sessions')->where('user_id', $userId)->whereNull('revoked_at')->where('expires_at', '>', $now)->count(),
            'active_consents' => Db::table('moo_oauth_consents')->where('user_id', $userId)->whereNull('revoked_at')->count(),
            'owned_applications' => Db::table('moo_applications')->where('owner_user_id', $userId)->count(),
            'failed_logins_30d' => Db::table('moo_login_attempts')->where('user_id', $userId)->where('succeeded', false)->where('created_at', '>=', $now->modify('-30 days'))->count(),
        ];
    }
}

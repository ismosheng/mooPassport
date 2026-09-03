<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\User;
use app\common\repository\contract\UserRepositoryInterface;

/**
 * 通过 Eloquent 存储和查询本地账号。
 */
final class UserRepository implements UserRepositoryInterface
{
    public function findById(int $id): ?User
    {
        $user = User::query()->find($id);

        return $user instanceof User ? $user : null;
    }

    public function findByPublicId(string $publicId): ?User
    {
        $user = User::query()->where('public_id', $publicId)->first();

        return $user instanceof User ? $user : null;
    }

    public function findByUsernameOrEmail(string $identifier): ?User
    {
        $user = User::query()
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        return $user instanceof User ? $user : null;
    }

    public function usernameExists(string $username): bool
    {
        return User::query()->where('username', $username)->exists();
    }

    public function emailExists(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    public function phoneExists(string $countryCode, string $phoneNumber, ?int $excludeUserId = null): bool
    {
        $query = User::query()
            ->where('phone_country_code', $countryCode)
            ->where('phone_number', $phoneNumber);

        if ($excludeUserId !== null) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->exists();
    }

    public function create(array $attributes): User
    {
        return User::query()->create($attributes);
    }

    public function save(User $user): void
    {
        $user->saveOrFail();
    }
}

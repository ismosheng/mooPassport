<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\User;

/**
 * 定义通行证本地账号的持久化操作。
 */
interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function findByPublicId(string $publicId): ?User;

    public function findByUsernameOrEmail(string $identifier): ?User;

    public function usernameExists(string $username): bool;

    public function emailExists(string $email): bool;

    public function phoneExists(string $countryCode, string $phoneNumber, ?int $excludeUserId = null): bool;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): User;

    public function save(User $user): void;
}

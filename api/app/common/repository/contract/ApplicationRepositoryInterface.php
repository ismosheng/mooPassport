<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\Application;

/** 定义逻辑应用的持久化边界。 */
interface ApplicationRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Application;

    /** @return list<Application> */
    public function listOwnedByUser(int $ownerUserId): array;

    /** @return array{items:list<Application>,total:int} */
    public function searchOwnedByUser(int $ownerUserId, string $keyword, ?string $status, int $page, int $perPage): array;

    /** @return array{items:list<Application>,total:int} */
    public function searchAll(string $keyword, ?string $status, int $page, int $perPage): array;

    public function findOwnedByPublicId(int $ownerUserId, string $publicId): ?Application;

    public function findByPublicId(string $publicId): ?Application;

    public function delete(Application $application): void;

    public function save(Application $application): void;
}

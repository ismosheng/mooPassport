<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\OAuthScope;

/**
 * 解析有效的 OAuth Scope，避免向 Service 暴露 ORM 集合。
 */
interface OAuthScopeRepositoryInterface
{
    public function findActiveByName(string $name): ?OAuthScope;

    /** @return list<OAuthScope> */
    public function findAllActive(): array;

    /**
     * @param list<string> $names
     * @return list<OAuthScope>
     */
    public function findActiveByNames(array $names): array;

    /** @return list<OAuthScope> */
    public function findAllowedForClient(int $clientId): array;
}

<?php

declare(strict_types=1);

namespace app\common\repository\contract;

use app\common\model\OAuthClient;

/**
 * 定义已注册 OAuth 客户端应用的持久化操作。
 */
interface OAuthClientRepositoryInterface
{
    /** @return list<OAuthClient> */
    public function listAll(): array;

    public function findById(int $id): ?OAuthClient;

    public function findByClientId(string $clientId): ?OAuthClient;

    public function findActiveByClientId(string $clientId): ?OAuthClient;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): OAuthClient;

    public function save(OAuthClient $client): void;
}

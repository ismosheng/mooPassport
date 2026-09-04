<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthClient;
use app\common\repository\contract\OAuthClientRepositoryInterface;

/**
 * 持久化已注册 OAuth 客户端，不在此处处理客户端密钥校验。
 */
final class OAuthClientRepository implements OAuthClientRepositoryInterface
{
    public function listAll(): array
    {
        /** @var list<OAuthClient> $clients */
        $clients = OAuthClient::query()->orderByDesc('id')->get()->all();
        return $clients;
    }

    public function findById(int $id): ?OAuthClient
    {
        $client = OAuthClient::query()->find($id);

        return $client instanceof OAuthClient ? $client : null;
    }

    public function findByClientId(string $clientId): ?OAuthClient
    {
        $client = OAuthClient::query()->where('client_id', $clientId)->first();

        return $client instanceof OAuthClient ? $client : null;
    }

    public function findActiveByClientId(string $clientId): ?OAuthClient
    {
        $client = OAuthClient::query()
            ->leftJoin('moo_applications as applications', 'applications.id', '=', 'moo_oauth_clients.application_id')
            ->where('moo_oauth_clients.client_id', $clientId)
            ->where('moo_oauth_clients.status', 'active')
            ->where(function ($query): void {
                $query->whereNull('moo_oauth_clients.application_id')
                    ->orWhere('applications.status', 'active');
            })
            ->select('moo_oauth_clients.*')
            ->first();

        return $client instanceof OAuthClient ? $client : null;
    }

    public function create(array $attributes): OAuthClient
    {
        return OAuthClient::query()->create($attributes);
    }

    public function save(OAuthClient $client): void
    {
        $client->saveOrFail();
    }
}

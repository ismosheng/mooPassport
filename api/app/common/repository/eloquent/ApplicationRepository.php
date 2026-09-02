<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\Application;
use app\common\repository\contract\ApplicationRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

/** 使用 Eloquent 保存和读取逻辑应用，不组织 OAuth 客户端创建流程。 */
final class ApplicationRepository implements ApplicationRepositoryInterface
{
    public function create(array $attributes): Application
    {
        return Application::query()->create($attributes);
    }

    public function listOwnedByUser(int $ownerUserId): array
    {
        /** @var list<Application> $applications */
        $applications = Application::query()->where('owner_user_id', $ownerUserId)->orderByDesc('id')->get()->all();
        return $applications;
    }

    public function searchOwnedByUser(int $ownerUserId, string $keyword, ?string $status, int $page, int $perPage): array
    {
        $query = Application::query()->where('owner_user_id', $ownerUserId);
        return $this->search($query, $keyword, $status, $page, $perPage);
    }

    public function searchAll(string $keyword, ?string $status, int $page, int $perPage): array
    {
        return $this->search(Application::query(), $keyword, $status, $page, $perPage);
    }

    /**
     * @param Builder<Application> $query
     * @return array{items:list<Application>,total:int}
     */
    private function search(Builder $query, string $keyword, ?string $status, int $page, int $perPage): array
    {
        if ($keyword !== '') {
            $query->where(function ($query) use ($keyword): void {
                $query->where('name', 'like', '%' . $keyword . '%')
                    ->orWhereExists(function ($clientQuery) use ($keyword): void {
                        $clientQuery->selectRaw('1')->from('moo_oauth_clients')
                            ->whereColumn('moo_oauth_clients.application_id', 'moo_applications.id')
                            ->where('moo_oauth_clients.client_id', 'like', '%' . $keyword . '%');
                    });
            });
        }
        if ($status !== null) {
            $query->where('status', $status);
        }
        $total = (clone $query)->count();
        /** @var list<Application> $items */
        $items = $query->orderByDesc('id')->forPage($page, $perPage)->get()->all();
        return ['items' => $items, 'total' => $total];
    }

    public function findOwnedByPublicId(int $ownerUserId, string $publicId): ?Application
    {
        return Application::query()
            ->where('owner_user_id', $ownerUserId)
            ->where('public_id', $publicId)
            ->first();
    }

    public function findByPublicId(string $publicId): ?Application
    {
        return Application::query()->where('public_id', $publicId)->first();
    }

    public function delete(Application $application): void
    {
        $application->delete();
    }

    public function save(Application $application): void
    {
        $application->save();
    }
}

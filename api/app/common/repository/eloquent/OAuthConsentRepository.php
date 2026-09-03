<?php

declare(strict_types=1);

namespace app\common\repository\eloquent;

use app\common\model\OAuthConsent;
use app\common\repository\contract\OAuthConsentRepositoryInterface;
use DateTimeImmutable;

/**
 * 持久化用户授权确认，并且只返回当前有效的授权。
 */
final class OAuthConsentRepository implements OAuthConsentRepositoryInterface
{
    public function findActive(int $userId, int $clientId, DateTimeImmutable $now): ?OAuthConsent
    {
        $consent = (new OAuthConsent())->newQuery()
            ->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->where(static function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->first();

        return $consent instanceof OAuthConsent ? $consent : null;
    }

    public function listActiveForUser(int $userId, DateTimeImmutable $now): array
    {
        /** @var list<OAuthConsent> $consents */
        $consents = (new OAuthConsent())->newQuery()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where(static function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderByDesc('granted_at')
            ->get()
            ->all();

        return $consents;
    }

    public function paginateActiveForUser(int $userId, DateTimeImmutable $now, int $page, int $perPage): array
    {
        $query = (new OAuthConsent())->newQuery()
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->where(static function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            });

        $total = (clone $query)->count();
        /** @var list<OAuthConsent> $consents */
        $consents = $query
            ->orderByDesc('granted_at')
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get()
            ->all();

        return ['items' => $consents, 'total' => $total];
    }

    public function grant(int $userId, int $clientId, array $scopes, ?DateTimeImmutable $expiresAt): OAuthConsent
    {
        return OAuthConsent::query()->updateOrCreate(
            ['user_id' => $userId, 'client_id' => $clientId],
            [
                'scopes' => array_values(array_unique($scopes)),
                'granted_at' => new DateTimeImmutable(),
                'expires_at' => $expiresAt,
                'revoked_at' => null,
            ],
        );
    }

    public function revoke(int $userId, int $clientId, DateTimeImmutable $revokedAt): bool
    {
        return OAuthConsent::query()
            ->where('user_id', $userId)
            ->where('client_id', $clientId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $revokedAt]) === 1;
    }
}

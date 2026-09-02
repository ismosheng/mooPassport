<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\exception\BusinessException;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\repository\contract\OAuthConsentRepositoryInterface;
use app\common\support\IpAddress;
use DateTimeImmutable;
use DateTimeZone;

/**
 * 管理当前用户已授权给第三方 OAuth 应用的记录。
 *
 * 撤销授权不会自动吊销已签发的 Access Token，但会阻止后续授权确认自动通过。
 */
final class ConsentManagementService
{
    public function __construct(
        private readonly OAuthConsentRepositoryInterface $consents,
        private readonly OAuthClientRepositoryInterface $clients,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly IpAddress $ipAddress,
    ) {
    }

    /**
     * @return list<array{
     *     client_id: string,
     *     name: string,
     *     description: ?string,
     *     logo_url: ?string,
     *     scopes: list<string>,
     *     granted_at: ?string
     * }>
     */
    public function listForUser(int $userId): array
    {
        $now = $this->now();
        $items = [];
        foreach ($this->consents->listActiveForUser($userId, $now) as $consent) {
            $client = $this->clients->findById($consent->client_id);
            if ($client === null) {
                continue;
            }

            $items[] = [
                'client_id' => $client->client_id,
                'name' => $client->name,
                'description' => $client->description,
                'logo_url' => $client->logo_url,
                'scopes' => array_values((array) $consent->scopes),
                'granted_at' => $consent->granted_at?->format(DATE_ATOM),
            ];
        }

        return $items;
    }

    public function revokeForUser(
        int $userId,
        string $publicClientId,
        ?string $requestIp,
        ?string $userAgent,
    ): void {
        $client = $this->clients->findByClientId(trim($publicClientId));
        if ($client === null) {
            throw new BusinessException('consent_not_found', '授权记录不存在或已失效。', 404);
        }

        $now = $this->now();
        if (!$this->consents->revoke($userId, $client->id, $now)) {
            throw new BusinessException('consent_not_found', '授权记录不存在或已失效。', 404);
        }

        $this->auditLogs->record([
            'event_type' => 'oauth.consent.revoked',
            'user_id' => $userId,
            'client_id' => $client->id,
            'ip_address' => $this->ipAddress->toBinary($requestIp),
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 500),
            'success' => true,
        ]);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}

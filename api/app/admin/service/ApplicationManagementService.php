<?php

declare(strict_types=1);

namespace app\admin\service;

use app\admin\dto\CreateApplicationInput;
use app\common\enum\OAuthApplicationType;
use app\common\exception\BusinessException;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\model\Application;
use app\common\repository\contract\AccessTokenRepositoryInterface;
use app\common\repository\contract\ApplicationRepositoryInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\repository\contract\AuthorizationCodeRepositoryInterface;
use app\common\repository\contract\OAuthClientManagementRepositoryInterface;
use app\common\repository\contract\OAuthClientRepositoryInterface;
use app\common\repository\contract\OAuthPushedAuthorizationRequestRepositoryInterface;
use app\common\repository\contract\RefreshTokenRepositoryInterface;
use app\common\dto\CreateOAuthClientInput;
use app\common\dto\CreatedOAuthClient;
use app\common\service\OAuthClientManagementService;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Ulid;

/** 管理逻辑应用，并以独立 OAuth 客户端隔离用户登录与机器调用凭据。 */
final class ApplicationManagementService
{
    public function __construct(
        private readonly ApplicationRepositoryInterface $applications,
        private readonly OAuthClientManagementRepositoryInterface $clients,
        private readonly OAuthClientManagementService $clientManagement,
        private readonly OAuthClientRepositoryInterface $clientRepository,
        private readonly AccessTokenRepositoryInterface $accessTokens,
        private readonly RefreshTokenRepositoryInterface $refreshTokens,
        private readonly AuthorizationCodeRepositoryInterface $authorizationCodes,
        private readonly OAuthPushedAuthorizationRequestRepositoryInterface $pushedAuthorizationRequests,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    /** @return array{application: Application, clients: list<array{purpose: string, created: CreatedOAuthClient}>} */
    public function create(int $ownerUserId, CreateApplicationInput $input): array
    {
        $capabilities = array_values(array_unique($input->capabilities));
        if ($capabilities === [] || array_diff($capabilities, ['login', 'service']) !== []) {
            throw new BusinessException('invalid_capability', '请至少选择一种有效的接入能力。', 422);
        }
        if (in_array('login', $capabilities, true) && $input->redirectUris === []) {
            throw new BusinessException('redirect_uri_required', '用户登录接入至少需要一个回调地址。', 422);
        }
        $this->validateLogoUrl($input->logoUrl);

        return $this->transactions->run(function () use ($ownerUserId, $input, $capabilities): array {
            $application = $this->applications->create([
                'public_id' => (string) new Ulid(),
                'owner_user_id' => $ownerUserId,
                'name' => trim($input->name),
                'description' => $input->description === null ? null : trim($input->description),
                'logo_url' => $input->logoUrl,
                'status' => 'active',
            ]);
            $createdClients = [];

            if (in_array('login', $capabilities, true)) {
                $createdClients[] = [
                    'purpose' => 'login',
                    'created' => $this->clientManagement->create($ownerUserId, new CreateOAuthClientInput(
                        name: $application->name . ' - 用户登录',
                        description: $application->description,
                        logoUrl: $application->logo_url,
                        applicationType: $input->loginApplicationType,
                        redirectUris: $input->redirectUris,
                        scopes: $input->loginScopes,
                    ), $application->id),
                ];
            }
            if (in_array('service', $capabilities, true)) {
                $createdClients[] = [
                    'purpose' => 'service',
                    'created' => $this->clientManagement->create($ownerUserId, new CreateOAuthClientInput(
                        name: $application->name . ' - 服务端 API',
                        description: $application->description,
                        logoUrl: $application->logo_url,
                        applicationType: 'service',
                        redirectUris: [],
                        scopes: ['service'],
                    ), $application->id),
                ];
            }

            return ['application' => $application, 'clients' => $createdClients];
        });
    }

    /** @return list<array{application: Application, clients: list<\app\common\model\OAuthClient>}> */
    public function list(int $ownerUserId): array
    {
        $applications = $this->applications->listOwnedByUser($ownerUserId);
        $clients = $this->clients->listByApplicationIds(array_map(static fn (Application $app): int => $app->id, $applications));
        $grouped = [];
        foreach ($clients as $client) {
            $grouped[(int) $client->application_id][] = $client;
        }

        return array_map(static fn (Application $application): array => [
            'application' => $application,
            'clients' => $grouped[$application->id] ?? [],
        ], $applications);
    }

    /** @return array{items:list<array{application:Application,clients:list<\app\common\model\OAuthClient>}>,total:int,page:int,per_page:int} */
    public function search(string $keyword, ?string $status, int $page, int $perPage): array
    {
        // 后台 read 权限代表查看全局应用；所有者隔离只适用于用户自助管理入口。
        $result = $this->applications->searchAll(trim($keyword), $status, $page, $perPage);
        $applications = $result['items'];
        $clients = $this->clients->listByApplicationIds(array_map(static fn (Application $app): int => $app->id, $applications));
        $grouped = [];
        foreach ($clients as $client) {
            $grouped[(int) $client->application_id][] = $client;
        }
        return [
            'items' => array_map(static fn (Application $application): array => [
                'application' => $application,
                'clients' => $grouped[$application->id] ?? [],
            ], $applications),
            'total' => $result['total'], 'page' => $page, 'per_page' => $perPage,
        ];
    }

    /** @return array{application: Application, clients: list<\app\common\model\OAuthClient>} */
    public function detail(string $publicId): array
    {
        $application = $this->applications->findByPublicId($publicId);
        if ($application === null) {
            throw new BusinessException('application_not_found', '应用不存在。', 404);
        }
        $clients = $this->clients->listByApplicationIds([$application->id]);
        return ['application' => $application, 'clients' => $clients];
    }

    public function delete(string $publicId): void
    {
        $result = $this->detail($publicId);
        // 外键级联删除客户端、授权码和令牌，确保被删除应用无法继续访问资源。
        $this->transactions->run(fn () => $this->applications->delete($result['application']));
    }

    /** @return array{application: Application, clients: list<\app\common\model\OAuthClient>} */
    public function update(string $publicId, string $name, ?string $description, ?string $logoUrl): array
    {
        $this->validateLogoUrl($logoUrl);
        $result = $this->detail($publicId);
        $application = $result['application'];
        $application->name = trim($name);
        $application->description = $description === null ? null : trim($description);
        $application->logo_url = $logoUrl;

        $this->transactions->run(function () use ($application, $result): void {
            $this->applications->save($application);
            foreach ($result['clients'] as $client) {
                $purpose = $client->application_type === OAuthApplicationType::Service ? '服务端 API' : '用户登录';
                $client->name = $application->name . ' - ' . $purpose;
                $client->description = $application->description;
                $client->logo_url = $application->logo_url;
                $this->clientRepository->save($client);
            }
        });
        return $this->detail($publicId);
    }

    /** @return array{application: Application, clients: list<\app\common\model\OAuthClient>} */
    public function updateStatus(string $publicId, string $status, int $actorUserId): array
    {
        if (!in_array($status, ['active', 'disabled'], true)) {
            throw new BusinessException('invalid_application_status', '应用状态无效。', 422);
        }

        $result = $this->detail($publicId);
        $application = $result['application'];
        $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
        $this->transactions->run(function () use ($application, $result, $status, $actorUserId, $now): void {
            $application->status = $status;
            $this->applications->save($application);
            if ($status === 'disabled') {
                foreach ($result['clients'] as $client) {
                    // 应用状态是父级总开关；保留客户端自身状态，避免重新启用应用时复活此前单独禁用的客户端。
                    $this->accessTokens->revokeForClient($client->id, $now);
                    $this->refreshTokens->revokeForClient($client->id, $now);
                    $this->authorizationCodes->revokeUnusedForClient($client->id, $now);
                    $this->pushedAuthorizationRequests->revokeUnusedForClient($client->id, $now);
                }
            }
            $this->auditLogs->record([
                'event_type' => 'oauth.application.status_changed',
                'user_id' => $actorUserId,
                'success' => true,
                'details' => [
                    'application_id' => $application->public_id,
                    'status' => $status,
                    'client_count' => count($result['clients']),
                ],
            ]);
        });

        return $this->detail($publicId);
    }

    /**
     * @param list<int> $clientIds
     * @return array<int, array{redirect_uris: list<string>, scopes: list<string>}>
     */
    public function clientConfigurations(array $clientIds): array
    {
        return $this->clients->configurationsByClientIds($clientIds);
    }

    private function validateLogoUrl(?string $logoUrl): void
    {
        if ($logoUrl === null || $logoUrl === '') return;
        $parts = parse_url($logoUrl);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $loopback = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        if (!is_array($parts) || ($scheme !== 'https' && !($scheme === 'http' && $loopback))) {
            throw new BusinessException('invalid_logo_url', '图标地址必须使用 HTTPS，本地开发可使用环回地址。', 422);
        }
    }
}


<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\admin\dto\CreateApplicationInput;
use app\admin\middleware\RequirePermission;
use app\admin\service\ApplicationManagementService;
use app\admin\validator\CreateApplicationValidator;
use app\admin\validator\UpdateApplicationValidator;
use app\common\enum\OAuthApplicationType;
use app\common\enum\OAuthClientStatus;
use app\common\enum\OAuthClientType;
use app\common\enum\TokenEndpointAuthMethod;
use app\common\exception\BusinessException;
use app\common\model\Application;
use app\common\model\OAuthClient;
use app\common\support\ApiResponse;
use app\passport\dto\AuthenticatedSession;
use app\passport\middleware\AuthenticateSession;
use support\annotation\Middleware;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Delete;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供逻辑应用创建与查询接口，客户端密钥只在创建响应中出现一次。 */
#[DisableDefaultRoute]
#[RouteGroup('/admin/v1/applications')]
#[Middleware(AuthenticateSession::class, RequirePermission::class)]
final class ApplicationController
{
    public function __construct(private readonly ApplicationManagementService $management)
    {
    }

    #[Post('', 'admin.v1.applications.create')]
    public function create(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = CreateApplicationValidator::make((array) $request->post())->validate();
        $result = $this->management->create($this->identity($request)->user->id, new CreateApplicationInput(
            name: (string) $data['name'],
            description: isset($data['description']) ? (string) $data['description'] : null,
            logoUrl: isset($data['logo_url']) ? (string) $data['logo_url'] : null,
            capabilities: array_values(array_map('strval', (array) $data['capabilities'])),
            loginApplicationType: (string) ($data['login_application_type'] ?? 'web'),
            redirectUris: array_values(array_map('strval', (array) ($data['redirect_uris'] ?? []))),
            loginScopes: array_values(array_map('strval', (array) ($data['login_scopes'] ?? ['openid', 'profile']))),
        ));
        $clients = array_map(
            static fn (array $item): OAuthClient => $item['created']->client,
            $result['clients'],
        );
        $configurations = $this->configurations($clients);

        return ApiResponse::success($request, [
            ...$this->serializeApplication($result['application'], $clients, $configurations),
            'clients' => array_map(fn (array $item): array => [
                ...$this->serializeClient($item['created']->client, $configurations),
                'purpose' => $item['purpose'],
                'client_secret' => $item['created']->plainSecret,
            ], $result['clients']),
            'client_secret_notice' => '机密客户端的 AppSecret 仅展示本次，请立即安全保存。',
        ], 201);
    }

    #[Get('', 'admin.v1.applications.list')]
    public function list(Request $request): Response
    {
        $keyword = trim((string) $request->get('keyword', ''));
        $status = trim((string) $request->get('status', ''));
        $status = in_array($status, ['active', 'disabled'], true) ? $status : null;
        $page = max(1, (int) $request->get('page', 1));
        $perPage = min(100, max(10, (int) $request->get('per_page', 20)));
        $result = $this->management->search($keyword, $status, $page, $perPage);
        $clients = array_merge(...array_map(static fn (array $item): array => $item['clients'], $result['items']));
        $configurations = $this->configurations($clients);
        $items = array_map(
            fn (array $item): array => $this->serializeApplication(
                $item['application'],
                $item['clients'],
                $configurations,
            ),
            $result['items'],
        );

        return ApiResponse::success($request, [
            'items' => $items, 'total' => $result['total'], 'page' => $result['page'], 'per_page' => $result['per_page'],
        ]);
    }

    #[Get('/{applicationId}', 'admin.v1.applications.detail')]
    public function detail(Request $request, string $applicationId): Response
    {
        $result = $this->management->detail($applicationId);
        return ApiResponse::success($request, $this->serializeApplication(
            $result['application'],
            $result['clients'],
            $this->configurations($result['clients']),
        ));
    }

    #[Put('/{applicationId}', 'admin.v1.applications.update')]
    public function update(Request $request, string $applicationId): Response
    {
        /** @var array<string, mixed> $data */
        $data = UpdateApplicationValidator::make((array) $request->post())->validate();
        $result = $this->management->update(
            $applicationId,
            (string) $data['name'],
            isset($data['description']) ? (string) $data['description'] : null,
            isset($data['logo_url']) ? (string) $data['logo_url'] : null,
        );
        return ApiResponse::success($request, $this->serializeApplication(
            $result['application'],
            $result['clients'],
            $this->configurations($result['clients']),
        ));
    }

    #[Delete('/{applicationId}', 'admin.v1.applications.delete')]
    public function delete(Request $request, string $applicationId): Response
    {
        $this->management->delete($applicationId);
        return ApiResponse::success($request, ['deleted' => true]);
    }

    /**
     * @param list<OAuthClient> $clients
     * @param array<int, array{redirect_uris: list<string>, scopes: list<string>}> $configurations
     * @return array<string, mixed>
     */
    private function serializeApplication(Application $application, array $clients, array $configurations): array
    {
        $serializedClients = array_map(
            fn (OAuthClient $client): array => $this->serializeClient($client, $configurations),
            $clients,
        );
        return [
            'id' => $application->public_id,
            'name' => $application->name,
            'description' => $application->description,
            'logo_url' => $application->logo_url,
            'status' => (string) $application->status,
            'capabilities' => array_values(array_unique(array_map(
                static fn (OAuthClient $client): string => $client->application_type === OAuthApplicationType::Service ? 'service' : 'login',
                $clients,
            ))),
            'clients' => $serializedClients,
            'created_at' => $application->created_at?->format(DATE_ATOM),
        ];
    }

    /**
     * @param array<int, array{redirect_uris: list<string>, scopes: list<string>}> $configurations
     * @return array<string, mixed>
     */
    private function serializeClient(OAuthClient $client, array $configurations): array
    {
        $configuration = $configurations[$client->id] ?? ['redirect_uris' => [], 'scopes' => []];

        return [
            'client_id' => $client->client_id,
            'name' => $client->name,
            'client_type' => $this->enumValue($client->client_type),
            'application_type' => $this->enumValue($client->application_type),
            'token_endpoint_auth_method' => $this->enumValue($client->token_endpoint_auth_method),
            'status' => $this->enumValue($client->status),
            'redirect_uris' => $configuration['redirect_uris'],
            'scopes' => $configuration['scopes'],
        ];
    }

    /**
     * @param list<OAuthClient> $clients
     * @return array<int, array{redirect_uris: list<string>, scopes: list<string>}>
     */
    private function configurations(array $clients): array
    {
        return $this->management->clientConfigurations(array_map(
            static fn (OAuthClient $client): int => $client->id,
            $clients,
        ));
    }

    private function enumValue(OAuthClientType|OAuthApplicationType|TokenEndpointAuthMethod|OAuthClientStatus|string $value): string
    {
        return is_string($value) ? $value : $value->value;
    }

    private function identity(Request $request): AuthenticatedSession
    {
        $identity = $request->context[AuthenticateSession::CONTEXT_KEY] ?? null;
        if (!$identity instanceof AuthenticatedSession) {
            throw new BusinessException('unauthenticated', '请先登录。', 401);
        }
        return $identity;
    }
}

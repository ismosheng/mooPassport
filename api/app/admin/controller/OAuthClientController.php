<?php

declare(strict_types=1);

namespace app\admin\controller;

use app\common\enum\OAuthApplicationType;
use app\common\enum\OAuthClientStatus;
use app\common\enum\OAuthClientType;
use app\common\enum\TokenEndpointAuthMethod;
use app\common\exception\BusinessException;
use app\common\model\OAuthClient;
use app\common\support\ApiResponse;
use app\admin\middleware\RequirePermission;
use app\passport\dto\AuthenticatedSession;
use app\common\dto\CreateOAuthClientInput;
use app\common\dto\UpdateOAuthClientInput;
use app\passport\middleware\AuthenticateSession;
use app\common\service\OAuthClientManagementService;
use app\passport\validator\CreateOAuthClientValidator;
use app\passport\validator\UpdateOAuthClientValidator;
use app\passport\validator\UpdateOAuthClientStatusValidator;
use support\annotation\Middleware;
use support\annotation\route\DisableDefaultRoute;
use support\annotation\route\Get;
use support\annotation\route\Post;
use support\annotation\route\Put;
use support\annotation\route\RouteGroup;
use Webman\Http\Request;
use Webman\Http\Response;

/** 提供超级管理员使用的 OAuth 应用创建、列表和维护接口。 */
#[DisableDefaultRoute]
#[RouteGroup('/admin/v1/oauth/clients')]
#[Middleware(AuthenticateSession::class, RequirePermission::class)]
final class OAuthClientController
{
    public function __construct(private readonly OAuthClientManagementService $management)
    {
    }

    #[Post('', 'admin.v1.oauth_clients.create')]
    public function create(Request $request): Response
    {
        /** @var array<string, mixed> $data */
        $data = CreateOAuthClientValidator::make((array) $request->post())->validate();
        $created = $this->management->create(
            $this->identity($request)->user->id,
            new CreateOAuthClientInput(
                name: (string) $data['name'],
                description: isset($data['description']) ? (string) $data['description'] : null,
                applicationType: (string) $data['application_type'],
                redirectUris: array_values(array_map('strval', (array) $data['redirect_uris'])),
                scopes: array_values(array_map('strval', (array) $data['scopes'])),
            ),
        );

        $payload = $this->serialize($created->client, $this->configurations([$created->client]));
        $payload['client_secret'] = $created->plainSecret;
        $payload['client_secret_notice'] = $created->plainSecret === null
            ? '公开客户端不签发 AppSecret，必须使用 PKCE。'
            : 'AppSecret 仅展示本次，请立即安全保存。';

        return ApiResponse::success($request, $payload, 201);
    }

    #[Get('', 'admin.v1.oauth_clients.list')]
    public function list(Request $request): Response
    {
        $clients = $this->management->list($this->identity($request)->user->id, false);
        $configurations = $this->configurations($clients);
        $items = array_map(
            fn (OAuthClient $client): array => $this->serialize($client, $configurations),
            $clients,
        );

        return ApiResponse::success($request, ['items' => $items]);
    }

    #[Get('/{clientId}', 'admin.v1.oauth_clients.detail')]
    public function detail(Request $request, string $clientId): Response
    {
        $client = $this->management->detail($this->identity($request)->user->id, $clientId, false);

        return ApiResponse::success($request, $this->serialize($client, $this->configurations([$client])));
    }

    #[Put('/{clientId}', 'admin.v1.oauth_clients.update')]
    public function update(Request $request, string $clientId): Response
    {
        $raw = (array) $request->post();
        /** @var array<string, mixed> $data */
        $data = UpdateOAuthClientValidator::make($raw)->validate();
        $client = $this->management->update(
            $this->identity($request)->user->id,
            $clientId,
            new UpdateOAuthClientInput(
                name: isset($data['name']) ? (string) $data['name'] : null,
                description: isset($data['description']) ? (string) $data['description'] : null,
                descriptionProvided: array_key_exists('description', $raw),
                redirectUris: isset($data['redirect_uris'])
                    ? array_values(array_map('strval', (array) $data['redirect_uris']))
                    : null,
                scopes: isset($data['scopes'])
                    ? array_values(array_map('strval', (array) $data['scopes']))
                    : null,
            ),
            false,
        );

        return ApiResponse::success($request, $this->serialize($client, $this->configurations([$client])));
    }

    #[Post('/{clientId}/rotate-secret', 'admin.v1.oauth_clients.rotate_secret')]
    public function rotateSecret(Request $request, string $clientId): Response
    {
        $plainSecret = $this->management->rotateSecret(
            $this->identity($request)->user->id,
            $clientId,
            false,
        );

        return ApiResponse::success($request, [
            'client_id' => $clientId,
            'client_secret' => $plainSecret,
            'client_secret_notice' => 'AppSecret 仅展示本次，旧 AppSecret 已立即失效。',
        ]);
    }

    #[Post('/{clientId}/status', 'admin.v1.oauth_clients.status')]
    public function updateStatus(Request $request, string $clientId): Response
    {
        /** @var array<string, mixed> $data */
        $data = UpdateOAuthClientStatusValidator::make((array) $request->post())->validate();
        $client = $this->management->updateStatus(
            $this->identity($request)->user->id,
            $clientId,
            OAuthClientStatus::from((string) $data['status']),
            false,
        );

        return ApiResponse::success($request, $this->serialize($client, $this->configurations([$client])));
    }

    /**
     * @param array<int, array{redirect_uris: list<string>, scopes: list<string>}> $configurations
     * @return array<string, mixed>
     */
    private function serialize(OAuthClient $client, array $configurations): array
    {
        $configuration = $configurations[$client->id] ?? ['redirect_uris' => [], 'scopes' => []];

        return [
            'client_id' => $client->client_id,
            'name' => $client->name,
            'description' => $client->description,
            'client_type' => $this->enumValue($client->client_type),
            'application_type' => $this->enumValue($client->application_type),
            'token_endpoint_auth_method' => $this->enumValue($client->token_endpoint_auth_method),
            'require_pkce' => (bool) $client->require_pkce,
            'status' => $this->enumValue($client->status),
            'redirect_uris' => $configuration['redirect_uris'],
            'scopes' => $configuration['scopes'],
            'created_at' => $client->created_at?->format(DATE_ATOM),
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

    private function enumValue(
        OAuthClientType|OAuthApplicationType|TokenEndpointAuthMethod|OAuthClientStatus|string $value,
    ): string {
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


<?php

declare(strict_types=1);

namespace app\common\model;

use app\common\enum\OAuthApplicationType;
use app\common\enum\OAuthClientStatus;
use app\common\enum\OAuthClientType;
use app\common\enum\TokenEndpointAuthMethod;

/**
 * 映射已注册的 OAuth 客户端及其协议能力。
 *
 * @property int $id
 * @property int|null $application_id
 * @property string $client_id
 * @property OAuthClientType $client_type
 * @property TokenEndpointAuthMethod $token_endpoint_auth_method
 * @property OAuthClientStatus $status
 * @property string $name
 * @property string|null $description
 * @property string|null $logo_url
 * @property OAuthApplicationType $application_type
 * @property bool $require_consent
 * @property bool $require_pkce
 * @property list<string> $allowed_grant_types
 * @property list<string> $allowed_response_types
 * @property int $access_token_ttl
 * @property int $refresh_token_ttl
 * @property \DateTimeImmutable|null $created_at
 */
final class OAuthClient extends BaseModel
{
    protected $table = 'moo_oauth_clients';

    protected $fillable = [
        'application_id',
        'client_id', 'name', 'description', 'logo_url', 'client_type',
        'application_type', 'token_endpoint_auth_method', 'require_pkce',
        'require_consent', 'allowed_grant_types', 'allowed_response_types',
        'access_token_ttl', 'refresh_token_ttl', 'status', 'owner_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'client_type' => OAuthClientType::class,
        'application_type' => OAuthApplicationType::class,
        'token_endpoint_auth_method' => TokenEndpointAuthMethod::class,
        'status' => OAuthClientStatus::class,
        'require_pkce' => 'boolean',
        'require_consent' => 'boolean',
        'allowed_grant_types' => 'array',
        'allowed_response_types' => 'array',
        'access_token_ttl' => 'integer',
        'refresh_token_ttl' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}


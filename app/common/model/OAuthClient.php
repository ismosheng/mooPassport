<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * Maps a registered OAuth client and its protocol capabilities.
 */
final class OAuthClient extends BaseModel
{
    protected $table = 'moo_oauth_clients';

    protected $fillable = [
        'client_id', 'name', 'description', 'logo_url', 'client_type',
        'application_type', 'token_endpoint_auth_method', 'require_pkce',
        'require_consent', 'allowed_grant_types', 'allowed_response_types',
        'access_token_ttl', 'refresh_token_ttl', 'status', 'owner_user_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
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


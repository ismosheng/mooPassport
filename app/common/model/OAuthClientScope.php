<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * Maps the allowed-scope assignment for an OAuth client.
 */
final class OAuthClientScope extends BaseModel
{
    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'moo_oauth_client_scopes';

    protected $fillable = ['client_id', 'scope_id', 'created_at'];

    /** @var array<string, string> */
    protected $casts = ['created_at' => 'immutable_datetime'];
}


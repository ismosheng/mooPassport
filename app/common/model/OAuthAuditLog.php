<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * Maps immutable OAuth security events without storing raw credentials or tokens.
 */
final class OAuthAuditLog extends BaseModel
{
    public $timestamps = false;

    protected $table = 'moo_oauth_audit_logs';

    protected $fillable = [
        'event_type', 'user_id', 'client_id', 'ip_address', 'user_agent',
        'request_id', 'success', 'details', 'created_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'success' => 'boolean',
        'details' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}


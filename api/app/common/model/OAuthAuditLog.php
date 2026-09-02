<?php

declare(strict_types=1);

namespace app\common\model;

/**
 * 映射不可变的 OAuth 安全事件，禁止保存原始凭据或令牌。
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


<?php

declare(strict_types=1);

namespace app\admin\service;

use app\admin\repository\contract\SystemSettingsRepositoryInterface;
use app\common\exception\BusinessException;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;
use app\common\support\TransactionalMailTemplates;

/** 管理白名单系统设置；敏感凭据不进入设置表，修改使用版本号避免覆盖。 */
final class SystemSettingsService
{
    /** @var array<string, array{type:string,default:mixed,label:string,description:string,min?:int,max?:int,max_length?:int,multiline?:bool,format?:string,options?:array<string,string>}> */
    private const DEFINITIONS = [
        'site.name' => ['type' => 'string', 'default' => 'Moo Passport', 'label' => '站点名称', 'description' => '后台和事务邮件显示的站点名称', 'max_length' => 100],
        'site.public_url' => ['type' => 'string', 'default' => 'http://127.0.0.1:3000', 'label' => '公开访问地址', 'description' => '用户访问前端的完整地址，用于生成邮件操作链接', 'max_length' => 500, 'format' => 'url'],
        'auth.registration_enabled' => ['type' => 'boolean', 'default' => true, 'label' => '开放注册', 'description' => '是否允许新用户注册'],
        'auth.session_lifetime' => ['type' => 'integer', 'default' => 604800, 'label' => '登录会话时长', 'description' => '浏览器登录会话有效秒数', 'min' => 300, 'max' => 2592000],
        'oauth.access_token_ttl' => ['type' => 'integer', 'default' => 900, 'label' => 'Access Token 时长', 'description' => 'Access Token 有效秒数', 'min' => 60, 'max' => 86400],
        'oauth.refresh_token_ttl' => ['type' => 'integer', 'default' => 2592000, 'label' => 'Refresh Token 时长', 'description' => 'Refresh Token 有效秒数', 'min' => 3600, 'max' => 31536000],
        'audit.hot_retention_days' => ['type' => 'integer', 'default' => 90, 'label' => '在线审计保留天数', 'description' => '热表保留安全审计记录的天数', 'min' => 1, 'max' => 3650],
        'storage.driver' => ['type' => 'string', 'default' => 'local', 'label' => '存储位置', 'description' => '用户头像等上传文件的保存位置', 'max_length' => 20, 'options' => ['local' => '本地存储', 'qiniu' => '七牛云存储']],
        'storage.qiniu.bucket' => ['type' => 'string', 'default' => '', 'label' => 'Bucket', 'description' => '七牛云对象存储空间名称', 'max_length' => 100],
        'storage.qiniu.domain' => ['type' => 'string', 'default' => '', 'label' => '访问域名', 'description' => '绑定到 Bucket 的完整 HTTP 或 HTTPS 访问地址', 'max_length' => 500, 'format' => 'url_optional'],
        'storage.qiniu.prefix' => ['type' => 'string', 'default' => 'moo-passport', 'label' => '存储目录', 'description' => '七牛空间中的路径前缀，例如 moo-passport', 'max_length' => 120, 'format' => 'path_prefix'],
        'mail.verification.subject' => ['type' => 'string', 'default' => TransactionalMailTemplates::DEFAULTS['mail.verification.subject'], 'label' => '验证邮件主题', 'description' => '可使用 {{site_name}}', 'max_length' => 200],
        'mail.verification.text' => ['type' => 'string', 'default' => TransactionalMailTemplates::DEFAULTS['mail.verification.text'], 'label' => '验证邮件纯文本', 'description' => '可使用 {{site_name}}、{{display_name}}、{{action_url}}、{{expires_minutes}}', 'max_length' => 10000, 'multiline' => true],
        'mail.verification.html' => ['type' => 'string', 'default' => TransactionalMailTemplates::DEFAULTS['mail.verification.html'], 'label' => '验证邮件 HTML', 'description' => 'HTML 中的占位符会自动转义后替换', 'max_length' => 20000, 'multiline' => true],
        'mail.password_reset.subject' => ['type' => 'string', 'default' => TransactionalMailTemplates::DEFAULTS['mail.password_reset.subject'], 'label' => '重置邮件主题', 'description' => '可使用 {{site_name}}', 'max_length' => 200],
        'mail.password_reset.text' => ['type' => 'string', 'default' => TransactionalMailTemplates::DEFAULTS['mail.password_reset.text'], 'label' => '重置邮件纯文本', 'description' => '可使用 {{site_name}}、{{display_name}}、{{action_url}}、{{expires_minutes}}', 'max_length' => 10000, 'multiline' => true],
        'mail.password_reset.html' => ['type' => 'string', 'default' => TransactionalMailTemplates::DEFAULTS['mail.password_reset.html'], 'label' => '重置邮件 HTML', 'description' => 'HTML 中的占位符会自动转义后替换', 'max_length' => 20000, 'multiline' => true],
    ];

    public function __construct(
        private readonly SystemSettingsRepositoryInterface $settings,
        private readonly AuditLogRepositoryInterface $auditLogs,
        private readonly TransactionManagerInterface $transactions,
    ) {
    }

    /** @return array<string, array<string,mixed>> */
    public function all(): array
    {
        $rows = $this->settings->allByKey();
        $result = [];
        foreach (self::DEFINITIONS as $key => $definition) {
            $row = $rows[$key] ?? null;
            $value = $row === null ? $this->defaultValue($key, $definition['default']) : $this->decode($row['value'], $definition['type']);
            $result[$key] = [
                'key' => $key,
                'value' => $value,
                'type' => $definition['type'],
                'label' => $definition['label'],
                'description' => $definition['description'],
                'version' => $row['version'] ?? 0,
                'min' => $definition['min'] ?? null,
                'max' => $definition['max'] ?? null,
                'max_length' => $definition['max_length'] ?? null,
                'multiline' => $definition['multiline'] ?? false,
                'options' => $definition['options'] ?? null,
            ];
        }
        $result['storage.driver']['credential_configured'] = $this->qiniuCredentialsConfigured();
        return $result;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, int> $versions
     */
    public function update(int $actorUserId, array $values, array $versions): void
    {
        $this->assertStorageConfiguration($values);
        $this->transactions->run(function () use ($actorUserId, $values, $versions): void {
            foreach ($values as $key => $value) {
                $definition = self::DEFINITIONS[$key] ?? throw new BusinessException('setting_not_allowed', '包含不允许修改的设置。', 422);
                $encoded = $this->encode($value, $definition);
                $expected = (int) ($versions[$key] ?? 0);
                $existing = $this->settings->findForUpdate($key);
                if ($existing === null) {
                    if ($expected !== 0) throw new BusinessException('setting_version_conflict', '设置已被其他管理员修改，请刷新后重试。', 409);
                    $this->settings->create($key, $definition['type'], $encoded, $definition['description'], $actorUserId);
                } elseif ($existing['version'] !== $expected) {
                    throw new BusinessException('setting_version_conflict', '设置已被其他管理员修改，请刷新后重试。', 409);
                } else {
                    $this->settings->update($existing['id'], $encoded, $expected + 1, $actorUserId);
                }
            }
            $this->auditLogs->record(['event_type' => 'admin.settings.updated', 'user_id' => $actorUserId, 'success' => true, 'details' => ['keys' => array_keys($values)], 'created_at' => new \DateTimeImmutable()]);
        });
    }

    /** @param array{type:string,default:mixed,label:string,description:string,min?:int,max?:int,max_length?:int,multiline?:bool,format?:string,options?:array<string,string>} $definition */
    private function encode(mixed $value, array $definition): string
    {
        if ($definition['type'] === 'boolean') return $value === true || $value === 1 ? '1' : ($value === false || $value === 0 ? '0' : throw new BusinessException('setting_invalid_value', '设置值类型无效。', 422));
        if ($definition['type'] === 'integer' && (is_int($value) || (is_string($value) && ctype_digit($value)))) {
            $number = (int) $value;
            if (($definition['min'] ?? PHP_INT_MIN) > $number || ($definition['max'] ?? PHP_INT_MAX) < $number) throw new BusinessException('setting_out_of_range', '设置值超出允许范围。', 422);
            return (string) $number;
        }
        if ($definition['type'] === 'string' && is_string($value)) {
            if (mb_strlen($value) > ($definition['max_length'] ?? 500)) {
                throw new BusinessException('setting_too_long', '设置内容超过允许长度。', 422);
            }
            if (($definition['format'] ?? null) === 'url') {
                $url = parse_url($value);
                if (!is_array($url) || !in_array($url['scheme'] ?? null, ['http', 'https'], true) || empty($url['host'])) {
                    throw new BusinessException('setting_invalid_url', '公开访问地址必须是完整的 HTTP 或 HTTPS 地址。', 422);
                }
                return rtrim($value, '/');
            }
            if (($definition['format'] ?? null) === 'url_optional') {
                if (trim($value) === '') return '';
                $url = parse_url($value);
                if (!is_array($url) || !in_array($url['scheme'] ?? null, ['http', 'https'], true) || empty($url['host'])) {
                    throw new BusinessException('setting_invalid_url', '七牛访问域名必须是完整的 HTTP 或 HTTPS 地址。', 422);
                }
                return rtrim($value, '/');
            }
            if (($definition['format'] ?? null) === 'path_prefix') {
                $prefix = trim($value, '/');
                if ($prefix !== '' && preg_match('#^[A-Za-z0-9/_-]+$#', $prefix) !== 1) {
                    throw new BusinessException('setting_invalid_path_prefix', '存储目录只能包含字母、数字、斜杠、横线和下划线。', 422);
                }
                return $prefix;
            }
            if (isset($definition['options']) && !array_key_exists($value, $definition['options'])) {
                throw new BusinessException('setting_invalid_option', '设置选项无效。', 422);
            }
            return $value;
        }
        throw new BusinessException('setting_invalid_value', '设置值类型无效。', 422);
    }

    private function decode(string $value, string $type): mixed { return $type === 'boolean' ? $value === '1' : ($type === 'integer' ? (int) $value : $value); }

    private function defaultValue(string $key, mixed $fallback): mixed
    {
        $configured = match ($key) {
            'site.name' => (string) config('app.name', $fallback),
            'site.public_url' => (string) config('mail.verification_url', $fallback),
            'storage.driver' => (string) config('storage.default', $fallback),
            'storage.qiniu.bucket' => (string) config('storage.qiniu.bucket', $fallback),
            'storage.qiniu.domain' => (string) config('storage.qiniu.domain', $fallback),
            'storage.qiniu.prefix' => (string) config('storage.qiniu.prefix', $fallback),
            default => $fallback,
        };

        return is_string($configured) && trim($configured) === '' ? $fallback : $configured;
    }

    /** @param array<string, mixed> $values */
    private function assertStorageConfiguration(array $values): void
    {
        $changesStorage = false;
        foreach (array_keys($values) as $key) {
            if (str_starts_with($key, 'storage.')) {
                $changesStorage = true;
                break;
            }
        }
        if (!$changesStorage) return;

        $rows = $this->settings->allByKey();
        $currentValue = function (string $key) use ($rows): mixed {
            $definition = self::DEFINITIONS[$key];
            return isset($rows[$key])
                ? $this->decode($rows[$key]['value'], $definition['type'])
                : $this->defaultValue($key, $definition['default']);
        };
        $driver = (string) ($values['storage.driver'] ?? $currentValue('storage.driver'));
        if ($driver !== 'qiniu') return;

        $bucket = trim((string) ($values['storage.qiniu.bucket'] ?? $currentValue('storage.qiniu.bucket')));
        $domain = trim((string) ($values['storage.qiniu.domain'] ?? $currentValue('storage.qiniu.domain')));
        if (!$this->qiniuCredentialsConfigured() || $bucket === '' || $domain === '') {
            throw new BusinessException('qiniu_not_configured', '启用七牛云前，请配置 AK/SK、Bucket 和访问域名。', 422);
        }
    }

    private function qiniuCredentialsConfigured(): bool
    {
        return trim((string) config('storage.qiniu.access_key')) !== ''
            && trim((string) config('storage.qiniu.secret_key')) !== '';
    }
}

<?php

declare(strict_types=1);

namespace app\admin\service;

use app\admin\repository\contract\SystemSettingsRepositoryInterface;
use app\common\exception\BusinessException;
use app\common\infrastructure\database\TransactionManagerInterface;
use app\common\repository\contract\AuditLogRepositoryInterface;

/** 管理白名单系统设置；敏感凭据不进入设置表，修改使用版本号避免覆盖。 */
final class SystemSettingsService
{
    /** @var array<string, array{type:string,default:mixed,description:string,min?:int,max?:int}> */
    private const DEFINITIONS = [
        'site.name' => ['type' => 'string', 'default' => 'Moo Passport', 'description' => '后台和协议页面显示的站点名称'],
        'site.public_url' => ['type' => 'string', 'default' => 'http://127.0.0.1:3000', 'description' => '公开访问地址'],
        'auth.registration_enabled' => ['type' => 'boolean', 'default' => true, 'description' => '是否允许新用户注册'],
        'auth.session_lifetime' => ['type' => 'integer', 'default' => 604800, 'description' => '登录会话时长（秒）', 'min' => 300, 'max' => 2592000],
        'oauth.access_token_ttl' => ['type' => 'integer', 'default' => 900, 'description' => 'Access Token 时长（秒）', 'min' => 60, 'max' => 86400],
        'oauth.refresh_token_ttl' => ['type' => 'integer', 'default' => 2592000, 'description' => 'Refresh Token 时长（秒）', 'min' => 3600, 'max' => 31536000],
        'audit.hot_retention_days' => ['type' => 'integer', 'default' => 90, 'description' => '在线审计保留天数', 'min' => 1, 'max' => 3650],
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
            $value = $row === null ? $definition['default'] : $this->decode($row['value'], $definition['type']);
            $result[$key] = ['key' => $key, 'value' => $value, 'type' => $definition['type'], 'description' => $definition['description'], 'version' => $row['version'] ?? 0];
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, int> $versions
     */
    public function update(int $actorUserId, array $values, array $versions): void
    {
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

    /** @param array{type:string,default:mixed,description:string,min?:int,max?:int} $definition */
    private function encode(mixed $value, array $definition): string
    {
        if ($definition['type'] === 'boolean') return $value === true || $value === 1 ? '1' : ($value === false || $value === 0 ? '0' : throw new BusinessException('setting_invalid_value', '设置值类型无效。', 422));
        if ($definition['type'] === 'integer' && (is_int($value) || (is_string($value) && ctype_digit($value)))) {
            $number = (int) $value;
            if (($definition['min'] ?? PHP_INT_MIN) > $number || ($definition['max'] ?? PHP_INT_MAX) < $number) throw new BusinessException('setting_out_of_range', '设置值超出允许范围。', 422);
            return (string) $number;
        }
        if ($definition['type'] === 'string' && is_string($value) && mb_strlen($value) <= 500) return $value;
        throw new BusinessException('setting_invalid_value', '设置值类型无效。', 422);
    }

    private function decode(string $value, string $type): mixed { return $type === 'boolean' ? $value === '1' : ($type === 'integer' ? (int) $value : $value); }
}

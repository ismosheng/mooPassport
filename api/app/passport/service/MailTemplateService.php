<?php

declare(strict_types=1);

namespace app\passport\service;

use app\common\model\User;
use app\common\repository\contract\SystemSettingReaderInterface;
use app\common\support\TransactionalMailTemplates;

/**
 * 渲染账号事务邮件。
 *
 * 后台模板仅执行固定占位符替换，不解释 PHP、表达式或模板语法，避免管理员输入
 * 获得代码执行能力。SMTP 凭据不属于本服务，也不会进入系统设置表。
 */
final class MailTemplateService
{
    public function __construct(
        private readonly SystemSettingReaderInterface $settings,
        private readonly string $fallbackPublicUrl,
        private readonly string $fallbackSiteName,
    ) {
    }

    /** @return array{subject:string,text:string,html:string} */
    public function verification(User $user, string $rawToken, int $expiresMinutes): array
    {
        return $this->render(
            'mail.verification',
            $user,
            '/verify-email#token=' . rawurlencode($rawToken),
            $expiresMinutes,
        );
    }

    /** @return array{subject:string,text:string,html:string} */
    public function passwordReset(User $user, string $rawToken, int $expiresMinutes): array
    {
        return $this->render(
            'mail.password_reset',
            $user,
            '/reset-password#token=' . rawurlencode($rawToken),
            $expiresMinutes,
        );
    }

    /** @return array{subject:string,text:string,html:string} */
    private function render(string $prefix, User $user, string $actionPath, int $expiresMinutes): array
    {
        $rows = $this->settings->allByKey();
        $siteName = $this->value($rows, 'site.name', $this->fallbackSiteName);
        $publicUrl = rtrim($this->value($rows, 'site.public_url', $this->fallbackPublicUrl), '/');
        $actionUrl = $publicUrl . $actionPath;
        $displayName = trim((string) $user->display_name) ?: (string) $user->username;

        $plainVariables = [
            '{{site_name}}' => $siteName,
            '{{display_name}}' => $displayName,
            '{{action_url}}' => $actionUrl,
            '{{expires_minutes}}' => (string) $expiresMinutes,
        ];
        $htmlVariables = array_map(
            static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            $plainVariables,
        );

        return [
            'subject' => strtr($this->value($rows, $prefix . '.subject', TransactionalMailTemplates::DEFAULTS[$prefix . '.subject']), $plainVariables),
            'text' => strtr($this->value($rows, $prefix . '.text', TransactionalMailTemplates::DEFAULTS[$prefix . '.text']), $plainVariables),
            'html' => strtr($this->value($rows, $prefix . '.html', TransactionalMailTemplates::DEFAULTS[$prefix . '.html']), $htmlVariables),
        ];
    }

    /**
     * @param array<string, array{value:string,version:int}> $rows
     */
    private function value(array $rows, string $key, string $fallback): string
    {
        $value = trim((string) ($rows[$key]['value'] ?? ''));

        return $value === '' ? $fallback : $value;
    }
}

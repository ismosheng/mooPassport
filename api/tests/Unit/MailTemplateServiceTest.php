<?php

declare(strict_types=1);

namespace tests\Unit;

use app\common\model\User;
use app\common\repository\contract\SystemSettingReaderInterface;
use app\passport\service\MailTemplateService;
use PHPUnit\Framework\TestCase;

/** 覆盖邮件公开域名、后台模板和 HTML 占位符转义。 */
final class MailTemplateServiceTest extends TestCase
{
    public function testVerificationTemplateUsesConfiguredPublicUrlAndEscapesHtmlVariables(): void
    {
        $settings = new class implements SystemSettingReaderInterface {
            public function allByKey(): array
            {
                return [
                    'site.name' => ['value' => '测试通行证', 'version' => 1],
                    'site.public_url' => ['value' => 'https://id.example.com/', 'version' => 1],
                    'mail.verification.subject' => ['value' => '{{site_name}}邮箱验证', 'version' => 1],
                    'mail.verification.html' => ['value' => '<b>{{display_name}}</b><a href="{{action_url}}">验证</a>', 'version' => 1],
                ];
            }
        };
        $user = new User();
        $user->username = 'alice';
        $user->display_name = '<Admin>';

        $message = (new MailTemplateService($settings, 'http://fallback.test', 'Fallback'))
            ->verification($user, 'token-value', 30);

        self::assertSame('测试通行证邮箱验证', $message['subject']);
        self::assertStringContainsString('https://id.example.com/verify-email#token=token-value', $message['text']);
        self::assertStringContainsString('&lt;Admin&gt;', $message['html']);
        self::assertStringContainsString('https://id.example.com/verify-email#token=token-value', $message['html']);
    }
}

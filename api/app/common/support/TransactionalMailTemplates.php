<?php

declare(strict_types=1);

namespace app\common\support;

/** 集中定义事务邮件默认模板，供后台白名单和发送流程共享。 */
final class TransactionalMailTemplates
{
    public const DEFAULTS = [
        'mail.verification.subject' => '验证你的{{site_name}}邮箱',
        'mail.verification.text' => "你好，{{display_name}}：\n\n请在 {{expires_minutes}} 分钟内打开以下链接完成邮箱验证：\n{{action_url}}\n\n如果不是你本人操作，请忽略此邮件。",
        'mail.verification.html' => '<p>你好，{{display_name}}：</p><p>请在 {{expires_minutes}} 分钟内完成邮箱验证。</p><p><a href="{{action_url}}">验证邮箱</a></p><p>如果不是你本人操作，请忽略此邮件。</p>',
        'mail.password_reset.subject' => '重置你的{{site_name}}密码',
        'mail.password_reset.text' => "请在 {{expires_minutes}} 分钟内打开以下链接重置密码：\n{{action_url}}\n\n如果不是你本人操作，请忽略此邮件。",
        'mail.password_reset.html' => '<p>请在 {{expires_minutes}} 分钟内完成密码重置。</p><p><a href="{{action_url}}">重置密码</a></p><p>如果不是你本人操作，请忽略此邮件。</p>',
    ];

    private function __construct()
    {
    }
}

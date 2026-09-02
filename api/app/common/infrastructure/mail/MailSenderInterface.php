<?php

declare(strict_types=1);

namespace app\common\infrastructure\mail;

/** 定义可替换的事务邮件投递边界。 */
interface MailSenderInterface
{
    public function send(string $recipient, string $subject, string $text, string $html): void;
}

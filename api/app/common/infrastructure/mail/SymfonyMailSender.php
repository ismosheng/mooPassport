<?php

declare(strict_types=1);

namespace app\common\infrastructure\mail;

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/** 通过已配置的 Symfony Mailer 传输器发送事务邮件。 */
final class SymfonyMailSender implements MailSenderInterface
{
    private readonly Mailer $mailer;

    public function __construct(
        string $dsn,
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {
        $this->mailer = new Mailer(Transport::fromDsn($dsn));
    }

    public function send(string $recipient, string $subject, string $text, string $html): void
    {
        $message = (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($recipient)
            ->subject($subject)
            ->text($text)
            ->html($html);

        $this->mailer->send($message);
    }
}

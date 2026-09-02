<?php

declare(strict_types=1);

$mailer = getenv('MAIL_MAILER') ?: 'smtp';
$host = getenv('MAIL_HOST') ?: '127.0.0.1';
$port = (int) (getenv('MAIL_PORT') ?: 25);
$username = rawurlencode((string) (getenv('MAIL_USERNAME') ?: ''));
$password = rawurlencode((string) (getenv('MAIL_PASSWORD') ?: ''));
$encryption = strtolower((string) (getenv('MAIL_ENCRYPTION') ?: ''));
$scheme = $encryption === 'ssl' ? 'smtps' : $mailer;
$query = $encryption === 'tls' ? '?require_tls=true' : '';

return [
    'dsn' => sprintf('%s://%s:%s@%s:%d%s', $scheme, $username, $password, $host, $port, $query),
    'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@example.com',
    'from_name' => getenv('MAIL_FROM_NAME') ?: 'Moo Passport',
    'verification_url' => rtrim(getenv('PASSPORT_WEB_URL') ?: (getenv('APP_URL') ?: ''), '/'),
    'password_reset_url' => rtrim(getenv('PASSPORT_WEB_URL') ?: (getenv('APP_URL') ?: ''), '/'),
];

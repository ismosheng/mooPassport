<?php

declare(strict_types=1);

use app\oauth\service\SigningKeyService;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;
use support\App;

require dirname(__DIR__) . '/vendor/autoload.php';

$root = dirname(__DIR__);
$environmentFile = $root . '/.env';
if (!is_file($environmentFile)) {
    fwrite(STDERR, ".env 文件不存在。\n");
    exit(1);
}

Dotenv::createUnsafeImmutable($root)->safeLoad();
$encryptionKey = getenv('OIDC_PRIVATE_KEY_ENCRYPTION_KEY') ?: '';
if ($encryptionKey === '') {
    $encryptionKey = base64_encode(random_bytes(32));
    $contents = file_get_contents($environmentFile);
    if (!is_string($contents)) {
        fwrite(STDERR, "读取 .env 失败。\n");
        exit(1);
    }

    $replacement = 'OIDC_PRIVATE_KEY_ENCRYPTION_KEY=' . $encryptionKey;
    if (preg_match('/^OIDC_PRIVATE_KEY_ENCRYPTION_KEY=.*$/m', $contents) === 1) {
        $updated = preg_replace('/^OIDC_PRIVATE_KEY_ENCRYPTION_KEY=.*$/m', $replacement, $contents);
    } else {
        $updated = rtrim($contents) . PHP_EOL . $replacement . PHP_EOL;
    }
    if (!is_string($updated) || file_put_contents($environmentFile, $updated, LOCK_EX) === false) {
        fwrite(STDERR, "写入 OIDC 私钥加密主密钥失败。\n");
        exit(1);
    }

    // 当前初始化进程必须立即使用新密钥；Webman 常驻进程仍需手动重启。
    putenv('OIDC_PRIVATE_KEY_ENCRYPTION_KEY=' . $encryptionKey);
    $_ENV['OIDC_PRIVATE_KEY_ENCRYPTION_KEY'] = $encryptionKey;
    $_SERVER['OIDC_PRIVATE_KEY_ENCRYPTION_KEY'] = $encryptionKey;
}

App::loadAllConfig();
/** @var ContainerInterface $container */
$container = require $root . '/config/container.php';
$key = $container->get(SigningKeyService::class)->ensureActiveKey();

fwrite(STDOUT, "OIDC 签名密钥已就绪，kid={$key->kid}\n");
fwrite(STDOUT, "请重启 Webman，使常驻进程加载新的环境变量。\n");

<?php

use Hobotix\Lightbot\{Config, TelegramClient};

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../vendor/autoload.php';
if (($argv[1] ?? '') !== '--apply') {
    fwrite(STDERR, "Usage: php tools/register-commands.php --apply\n");
    exit(2);
}
$telegram = new TelegramClient();
$failed = false;
foreach (Config::getDomains() as $domain => $config) {
    if (empty($config['tg_token']) || empty($config['tg_chat_id']) || empty($config['dtek'])) {
        continue;
    }
    try {
        $telegram->registerDtekCommand($config['tg_token'], (string)$config['tg_chat_id']);
        echo $domain . ": /dtek menu registered and verified\n";
    } catch (Throwable $e) {
        fwrite(STDERR, $domain . ": command registration failed\n");
        $failed = true;
    }
}
exit($failed ? 1 : 0);

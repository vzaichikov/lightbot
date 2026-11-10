<?php

use Hobotix\Lightbot\{Config, RuntimeStore, ScheduleCache, MessageComposer, TelegramClient, RefreshCallbacks, CallbackPoller};

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/vendor/autoload.php';
date_default_timezone_set('Europe/Kyiv');
$domains = Config::getDomains();
$domain = $argv[1] ?? '';
$token = $domains[$domain]['tg_token'] ?? '';
if ($token === '' || empty($domains[$domain]['dtek'])) {
    fwrite(STDERR, "Usage: php callbacks.php <configured-domain-with-DTEK>\n");
    exit(2);
}
try {
    $store = new RuntimeStore(Config::runtimeDirectory());
    $lock = $store->lock(CallbackPoller::key($token));
    if ($lock === null) {
        exit(0);
    }
    $telegram = new TelegramClient();
    $handler = new RefreshCallbacks($store, new ScheduleCache($store), new MessageComposer(), $telegram);
    $poller = new CallbackPoller($store, $telegram, $handler, $telegram->username($token));
    // Hestia cron acts as the watchdog; each healthy worker handles clicks immediately.
    // Recycling also bounds the time until configuration/code changes are reloaded.
    $deadline = hrtime(true) + 3600 * 1000000000;
    do {
        $results = $poller->poll($token, $domains, new DateTimeImmutable('now'));
        foreach ($results as $result) {
            if ($result !== 'ignored') {
                echo $domain . ': refresh ' . $result . PHP_EOL;
            }
        }
        usleep(100000);
    } while (hrtime(true) < $deadline);
} catch (Throwable $e) {
    fwrite(STDERR, "Callback worker stopped; check private runtime state and Telegram connectivity.\n");
    exit(1);
}

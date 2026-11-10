<?php

use Hobotix\Lightbot\{Config, RuntimeStore, ScheduleCache, MorningDigest, MessageComposer, TelegramClient};

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/vendor/autoload.php';
date_default_timezone_set('Europe/Kyiv');
$action = $argv[1] ?? '';
if (!in_array($action, ['refresh', 'morning', 'status'], true)) {
    fwrite(STDERR, "Usage: php schedule.php refresh|morning|status\n");
    exit(2);
}
try {
    $store = new RuntimeStore(Config::runtimeDirectory());
    $cache = new ScheduleCache($store);
    $now = new DateTimeImmutable('now');
    $domains = Config::getDomains();
    $failed = false;
    if ($action === 'morning') {
        $results = (new MorningDigest($store, $cache, new MessageComposer(), new TelegramClient()))->run($domains, $now);
        foreach ($results as $domain => $result) {
            if (!in_array($result, ['suppressed', 'disabled', 'locked'], true)) {
                echo $domain . ': ' . $result . PHP_EOL;
            }
            $failed = $failed || in_array($result, ['error', 'uncertain', 'rejected'], true);
        }
    } else {
        foreach ($domains as $domain => $config) {
            if (empty($config['dtek'])) {
                continue;
            }
            $snapshot = $action === 'refresh' ? $cache->refresh($config['dtek'], $now) : $cache->read($config['dtek'], $now);
            echo json_encode(['domain' => $domain, 'group' => $config['dtek']['group'], 'stale' => $snapshot['stale'],
                'feed_updated_at' => $snapshot['data']['feed_updated_at'] ?? null,
                'plan_updated_at' => $snapshot['data']['plan_updated_at'] ?? null,
                'slots' => count($snapshot['data']['slots'] ?? []), 'error' => $snapshot['error']], JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $failed = $failed || $snapshot['stale'];
        }
    }
    exit($failed ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Schedule operation failed; check private runtime storage and configuration.\n");
    exit(1);
}

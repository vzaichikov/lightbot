<?php

// Manual release verification only. Never install this command in cron.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../vendor/autoload.php';

use Hobotix\Lightbot\{Config, RuntimeStore, ScheduleCache, Schedule, MessageComposer, TelegramClient};

date_default_timezone_set('Europe/Kyiv');
$options = getopt('', ['send', 'id:']);
$send = isset($options['send']);
$id = $options['id'] ?? '';
if ($send && (!is_string($id) || !preg_match('/\A[a-zA-Z0-9_-]{1,80}\z/', $id))) {
    fwrite(STDERR, "Sending requires --send --id=unique-release-id; reuse the same ID on restart.\n");
    exit(2);
}
$failed = false;
try {
    $store = new RuntimeStore(Config::runtimeDirectory());
    $cache = new ScheduleCache($store);
    $composer = new MessageComposer();
    $telegram = new TelegramClient();
    foreach (Config::getDomains() as $domain => $config) {
        $lock = null;
        try {
            $key = 'verification-' . hash('sha256', $domain . ':' . $id);
            if ($send) {
                $lock = $store->lock($key);
                if ($lock === null) {
                    throw new RuntimeException('Verification busy');
                }
                $receipt = $store->read($key);
                if ($receipt !== null) {
                    echo json_encode(['domain' => $domain, 'previous_receipt' => $receipt], JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    $failed = $failed || ($receipt['status'] ?? '') !== 'sent';
                    continue;
                }
            }
            $now = new DateTimeImmutable('now');
            $snapshot = $cache->refresh($config['dtek'], $now);
            if ($snapshot['stale'] || Schedule::day($snapshot, $now)['status'] !== 'published') {
                throw new RuntimeException('Fresh published plan required');
            }
            $message = $composer->verification($domain, $config, $snapshot, $now);
            if (!$send) {
                echo json_encode(['domain' => $domain, 'rich' => $message['rich']], JSON_UNESCAPED_UNICODE) . PHP_EOL;
                continue;
            }
            $store->write($key, ['status' => 'sending', 'at' => $now->format(DATE_ATOM)]);
            $result = $telegram->send($config['tg_token'], (string)$config['tg_chat_id'], $message);
            $result['feed_updated_at'] = $snapshot['data']['feed_updated_at'];
            $result['plan_updated_at'] = $snapshot['data']['plan_updated_at'];
            $result['group'] = $config['dtek']['group'];
            $store->write($key, $result);
            echo json_encode(['domain' => $domain, 'receipt' => $result], JSON_UNESCAPED_UNICODE) . PHP_EOL;
            $failed = $failed || $result['status'] !== 'sent';
        } catch (Throwable $e) {
            fwrite(STDERR, $domain . ": verification not completed; fresh plan, credentials and private state required.\n");
            $failed = true;
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Verification setup failed.\n");
    $failed = true;
}
exit($failed ? 1 : 0);

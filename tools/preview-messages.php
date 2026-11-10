<?php

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../tests/bootstrap.php';

use Hobotix\Lightbot\{MessageComposer, Schedule, ScheduleCache};

$composer = new MessageComposer();
$configs = require __DIR__ . '/../config.example.php';
$now = at('2026-11-10 09:00');
echo '<!doctype html><html lang="uk"><meta charset="utf-8"><title>LightBot message previews</title>';
echo '<style>body{font:16px/1.5 system-ui;max-width:540px;margin:32px auto;padding:16px;background:#f3f4f5;color:#18232b}article{background:white;padding:20px;margin:24px 0;border-radius:12px}h2{font-size:22px}h3{font-size:18px}blockquote{border-left:4px solid #187c62;padding:8px 12px;margin:16px 0;background:#ecf8f2}table{border-collapse:collapse;width:100%}td,th{text-align:left;padding:8px;border-bottom:1px solid #ddd}footer{font-size:13px;color:#52616b}a{color:#116f91}hr{border:0;border-top:1px solid #ddd}</style>';
echo '<p>Fixture previews. Telegram controls the final typography and theme. Times below are examples.</p>';
foreach ($configs['domains'] as $domain => $config) {
    $raw = feed(hours: [10 => 'no', 11 => 'first', 15 => 'maybe', 20 => 'no'], region: $config['dtek']['region'], group: $config['dtek']['group']);
    $snapshot = ['data' => Schedule::normalize($raw, $config['dtek']['region'], $config['dtek']['group']), 'stale' => false];
    foreach ([
        $composer->event($domain, $config, true, 2, 30, $snapshot, $now->modify('-1 hour')),
        $composer->event($domain, $config, false, 3, 15, $snapshot, $now),
        $composer->digest($domain, $config, $snapshot, $now),
        $composer->digest($domain, $config, ScheduleCache::unavailable(), $now),
    ] as $message) {
        echo '<article>' . $message['rich'] . '</article>';
    }
}
echo '</html>';

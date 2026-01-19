<?php

require_once __DIR__ . '/vendor/autoload.php';

use Hobotix\Lightbot\Config;
use Hobotix\Lightbot\Database;

$host = $_SERVER['HTTP_HOST'] ?? '';
$domain = strtolower(trim(explode(':', $host)[0]));

if ($domain === '' || !array_key_exists($domain, Config::domains)) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$database = new Database(Config::db);
$status = $database->fetchStatus($domain);
$events = $database->fetchRecentEvents($domain, 5);

$currentStatus = $status ? (int)$status['status'] : 0;
$lastChangeAt = $status ? new DateTimeImmutable($status['last_change_at']) : null;
$durationSeconds = $lastChangeAt ? (new DateTimeImmutable('now'))->getTimestamp() - $lastChangeAt->getTimestamp() : null;

$statusLabel = $status ? ($currentStatus ? 'Світло увімкнено' : 'Світло вимкнено') : 'Немає даних';
$statusClass = $currentStatus ? 'status-on' : 'status-off';
$durationLabel = $durationSeconds === null ? '—' : formatDuration($durationSeconds);

function formatDuration(int $seconds): string
{
    $seconds = max(0, $seconds);
    $days = intdiv($seconds, 86400);
    $seconds %= 86400;
    $hours = intdiv($seconds, 3600);
    $seconds %= 3600;
    $minutes = intdiv($seconds, 60);

    $parts = [];
    if ($days > 0) {
        $parts[] = $days . ' д';
    }
    if ($hours > 0 || $days > 0) {
        $parts[] = $hours . ' год';
    }
    $parts[] = $minutes . ' хв';

    return implode(' ', $parts);
}
?>
<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LightBot — <?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f6f7fb;
            --card: #ffffff;
            --text: #1d1f23;
            --muted: #6b7280;
            --accent-on: #10b981;
            --accent-off: #ef4444;
            --shadow: 0 12px 32px rgba(15, 23, 42, 0.08);
            --radius: 20px;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: "Inter", "Segoe UI", system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .wrap {
            width: 100%;
            max-width: 420px;
        }
        .card {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .title {
            font-size: 18px;
            font-weight: 600;
        }
        .domain {
            font-size: 12px;
            color: var(--muted);
        }
        .status {
            display: grid;
            place-items: center;
            padding: 20px;
            border-radius: 16px;
            background: rgba(15, 23, 42, 0.03);
            text-align: center;
            margin-bottom: 20px;
        }
        .status-icon {
            width: 64px;
            height: 64px;
            margin-bottom: 12px;
        }
        .status-on .status-icon path,
        .status-on .status-icon circle {
            stroke: var(--accent-on);
        }
        .status-off .status-icon path,
        .status-off .status-icon circle {
            stroke: var(--accent-off);
        }
        .status-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 6px;
        }
        .status-subtitle {
            font-size: 14px;
            color: var(--muted);
            margin: 0;
        }
        .section-title {
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .events {
            display: grid;
            gap: 12px;
        }
        .event {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            border-radius: 14px;
            background: rgba(148, 163, 184, 0.14);
            font-size: 14px;
        }
        .event-status {
            font-weight: 600;
        }
        .event-time {
            color: var(--muted);
            font-size: 12px;
        }
        .event-status--on {
            color: var(--accent-on);
        }
        .event-status--off {
            color: var(--accent-off);
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: var(--muted);
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card <?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
            <div class="header">
                <div>
                    <div class="title">Моніторинг світла</div>
                    <div class="domain"><?= htmlspecialchars($domain, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>

            <div class="status">
                <svg class="status-icon" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="32" cy="32" r="24" stroke-width="4"/>
                    <path d="M32 12v16" stroke-width="4" stroke-linecap="round"/>
                </svg>
                <h2 class="status-title"><?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="status-subtitle">Поточний стан триває: <?= htmlspecialchars($durationLabel, ENT_QUOTES, 'UTF-8') ?></p>
            </div>

            <div>
                <div class="section-title">Останні зміни</div>
                <div class="events">
                    <?php if ($events === []) : ?>
                        <div class="event">
                            <span class="event-status">Поки що немає змін</span>
                            <span class="event-time">—</span>
                        </div>
                    <?php else : ?>
                        <?php foreach ($events as $event) : ?>
                            <?php $eventStatus = (int)$event['status']; ?>
                            <div class="event">
                            <span class="event-status <?= $eventStatus ? 'event-status--on' : 'event-status--off' ?>">
                                <?= $eventStatus ? 'Світло увімкнено' : 'Світло вимкнено' ?>
                            </span>
                                <span class="event-time">
                                    <?= htmlspecialchars(date('d.m.Y H:i', strtotime($event['started_at'])), ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="footer">Дані оновлюються автоматично.</div>
    </div>
</body>
</html>

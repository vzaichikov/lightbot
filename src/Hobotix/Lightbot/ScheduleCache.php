<?php

namespace Hobotix\Lightbot;

use DateTimeImmutable;

final class ScheduleCache
{
    public function __construct(private RuntimeStore $store, private ?\Closure $fetch = null) {}

    private function key(array $config): string
    {
        if (!isset(Schedule::SOURCES[$config['region'] ?? ''])
            || !preg_match('/\AGPV\d{1,2}\.[12]\z/', $config['group'] ?? '')) {
            throw new \InvalidArgumentException('Invalid DTEK configuration');
        }
        return 'schedule-' . $config['region'] . '-' . $config['group'];
    }

    public function read(array $config, DateTimeImmutable $now): array
    {
        try {
            $snapshot = $this->store->read($this->key($config));
            if (!is_array($snapshot) || !is_array($snapshot['data'] ?? null)
                || !is_array($snapshot['data']['slots'] ?? null)
                || !is_int($snapshot['data']['feed_updated_at'] ?? null)
                || !is_bool($snapshot['data']['collector_ok'] ?? null)
                || ($snapshot['data']['region'] ?? null) !== $config['region']
                || ($snapshot['data']['group'] ?? null) !== $config['group']) {
                return self::unavailable();
            }
            $end = null;
            foreach ($snapshot['data']['slots'] as $slot) {
                if (!is_array($slot) || !is_int($slot['start'] ?? null) || !is_int($slot['end'] ?? null)
                    || $slot['end'] <= $slot['start'] || ($end !== null && $slot['start'] < $end)
                    || !in_array($slot['state'] ?? null, ['on', 'off', 'possible'], true)) {
                    return self::unavailable();
                }
                $end = $slot['end'];
            }
            return $this->freshness($snapshot, $now);
        } catch (\Throwable $e) {
            return self::unavailable();
        }
    }

    public static function unavailable(): array
    {
        return ['data' => null, 'stale' => true, 'error' => 'unavailable', 'checked_at' => null];
    }

    private function freshness(array $snapshot, DateTimeImmutable $now): array
    {
        $updated = $snapshot['data']['feed_updated_at'] ?? 0;
        $age = $now->getTimestamp() - $updated;
        $snapshot['stale'] = !empty($snapshot['error']) || empty($snapshot['data']['collector_ok']) || $age > 1200 || $age < -60;
        return $snapshot;
    }

    public function refresh(array $config, DateTimeImmutable $now, bool $requireFetch = false): array
    {
        $key = $this->key($config);
        $lock = $this->store->lock($key);
        if ($lock === null) {
            return $requireFetch ? self::unavailable() : $this->read($config, $now);
        }
        try {
            $previous = $this->read($config, $now);
            try {
                $url = 'https://raw.githubusercontent.com/Baskerville42/outage-data-ua/main/data/' . $config['region'] . '.json';
                $raw = $this->fetch !== null ? ($this->fetch)($url) : self::fetch($url);
                $snapshot = ['data' => Schedule::normalize($raw, $config['region'], $config['group']),
                    'checked_at' => $now->getTimestamp(), 'error' => null];
            } catch (\Throwable $e) {
                $snapshot = $previous;
                $snapshot['error'] = 'refresh_failed';
            }
            $snapshot = $this->freshness($snapshot, $now);
            $this->store->write($key, $snapshot);
            return $snapshot;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private static function fetch(string $url): array
    {
        $body = '';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/json'], CURLOPT_USERAGENT => 'Lightbot-DTEK/1.0',
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 2 * 1024 * 1024) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($ok === false || $status !== 200) {
            throw new \RuntimeException('Schedule fetch failed');
        }
        return json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    }
}

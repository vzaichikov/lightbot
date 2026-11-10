<?php

namespace Hobotix\Lightbot;

use DateTimeImmutable;

final class MorningDigest
{
    public function __construct(
        private RuntimeStore $store,
        private ScheduleCache $cache,
        private MessageComposer $composer,
        private TelegramClient $telegram,
    ) {}

    public function run(array $domains, DateTimeImmutable $now): array
    {
        $now = Schedule::local($now);
        $hour = (int)$now->format('G');
        if ($hour < 9 || $hour >= 12) {
            return [];
        }
        $results = [];
        foreach ($domains as $domain => $config) {
            if (empty($config['tg_token']) || empty($config['tg_chat_id']) || empty($config['dtek'])) {
                $results[$domain] = 'disabled';
                continue;
            }
            $key = 'digest-' . hash('sha256', $domain) . '-' . $now->format('Y-m-d');
            $lock = null;
            try {
                $lock = $this->store->lock($key);
                if ($lock === null) {
                    $results[$domain] = 'locked';
                    continue;
                }
                $state = $this->store->read($key);
                if ($state !== null) {
                    if (!in_array($state['status'] ?? null, ['sending', 'sent', 'uncertain', 'rejected'], true)
                        || !is_int($state['attempts'] ?? null) || $state['attempts'] < 1
                        || !is_int($state['next_attempt'] ?? null)) {
                        throw new \RuntimeException('Invalid digest state');
                    }
                    if ($state['status'] !== 'rejected' || $state['attempts'] >= 3
                        || $state['next_attempt'] > $now->getTimestamp()) {
                        $results[$domain] = 'suppressed';
                        continue;
                    }
                }
                $snapshot = $this->cache->refresh($config['dtek'], $now);
                $message = $this->composer->digest($domain, $config, $snapshot, $now);
                $state = ['status' => 'sending', 'attempts' => ($state['attempts'] ?? 0) + 1,
                    'next_attempt' => 0, 'attempted_at' => $now->getTimestamp()];
                // Intent must persist BEFORE dispatch. Interrupted/uncertain sends never retry.
                $this->store->write($key, $state);
                $delivery = $this->telegram->send($config['tg_token'], (string)$config['tg_chat_id'], $message);
                $state['status'] = $delivery['status'];
                $state['next_attempt'] = $now->getTimestamp() + max(300, $delivery['retry_after']);
                $state['code'] = $delivery['code'];
                $this->store->write($key, $state);
                $results[$domain] = $delivery['status'];
            } catch (\Throwable $e) {
                // Do not overwrite persisted intent when saving the outcome fails.
                $results[$domain] = 'error';
            } finally {
                if (is_resource($lock)) {
                    flock($lock, LOCK_UN);
                    fclose($lock);
                }
            }
        }
        return $results;
    }
}

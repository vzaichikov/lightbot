<?php

namespace Hobotix\Lightbot;

use DateTimeImmutable;

final class CallbackPoller
{
    public function __construct(private RuntimeStore $store, private TelegramClient $telegram,
        private RefreshCallbacks $handler, private string $username = '') {}

    public static function key(string $token): string
    {
        return 'callbacks-' . hash('sha256', $token);
    }

    /** Caller holds the token-scoped worker lock across all polls. */
    public function poll(string $token, array $domains, DateTimeImmutable $now, int $timeout = 20): array
    {
        $started = hrtime(true);
        $key = self::key($token);
        $state = $this->store->read($key) ?? ['offset' => 0, 'next_poll' => 0, 'last_update_at' => 0];
        if (!is_int($state['offset'] ?? null) || $state['offset'] < 0 || !is_int($state['next_poll'] ?? null)
            || !is_int($state['last_update_at'] ?? null)) {
            throw new \RuntimeException('Invalid polling cursor');
        }
        if ($state['next_poll'] > $now->getTimestamp()) {
            throw new \RuntimeException('Polling is cooling down');
        }
        // Telegram retains updates for 24h and can randomize IDs after a quiet week.
        // A 48h idle expiry is beyond replay retention but before a new ID epoch.
        if ($state['offset'] > 0 && $now->getTimestamp() - $state['last_update_at'] >= 172800) {
            $state['offset'] = 0;
            $this->store->write($key, $state);
        }
        try {
            $updates = $this->telegram->updates($token, $state['offset'], $timeout);
        } catch (\RuntimeException $e) {
            $respondedAt = $now->getTimestamp() + (int)ceil((hrtime(true) - $started) / 1000000000);
            $state['next_poll'] = $respondedAt + max(60, $e->getCode());
            $this->store->write($key, $state);
            throw $e;
        }
        // Validate the entire ordered batch before advancing any cursor.
        $last = -1;
        foreach ($updates as $update) {
            if (!is_array($update) || !is_int($update['update_id'] ?? null) || $update['update_id'] <= $last) {
                throw new \RuntimeException('Invalid update batch');
            }
            $last = $update['update_id'];
        }
        $results = [];
        foreach ($updates as $update) {
            if ($update['update_id'] < $state['offset']) {
                continue;
            }
            // At-most-once dispatch: a crash after this point may lose this click,
            // but cannot replay a potentially successful group message.
            $state['offset'] = $update['update_id'] + 1;
            $state['last_update_at'] = $now->getTimestamp() + (int)((hrtime(true) - $started) / 1000000000);
            $this->store->write($key, $state);
            $elapsed = (int)((hrtime(true) - $started) / 1000000000);
            if (is_array($update['callback_query'] ?? null)) {
                $results[] = $this->handler->handle($token, $domains, $update['callback_query'], $now->modify('+' . $elapsed . ' seconds'));
            } elseif (is_array($update['message'] ?? null)) {
                $results[] = $this->handler->handleCommand($token, $domains, $update['message'], $this->username, $now->modify('+' . $elapsed . ' seconds'));
            }
        }
        return $results;
    }
}

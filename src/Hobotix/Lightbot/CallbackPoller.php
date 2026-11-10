<?php

namespace Hobotix\Lightbot;

use DateTimeImmutable;

final class CallbackPoller
{
    public function __construct(private RuntimeStore $store, private TelegramClient $telegram,
        private RefreshCallbacks $handler) {}

    public static function key(string $token): string
    {
        return 'callbacks-' . hash('sha256', $token);
    }

    /** Caller holds the token-scoped worker lock across all polls. */
    public function poll(string $token, array $domains, DateTimeImmutable $now, int $timeout = 20): array
    {
        $started = hrtime(true);
        $key = self::key($token);
        $state = $this->store->read($key) ?? ['offset' => 0, 'next_poll' => 0];
        if (!is_int($state['offset'] ?? null) || $state['offset'] < 0 || !is_int($state['next_poll'] ?? null)) {
            throw new \RuntimeException('Invalid polling cursor');
        }
        if ($state['next_poll'] > $now->getTimestamp()) {
            throw new \RuntimeException('Polling is cooling down');
        }
        try {
            $updates = $this->telegram->updates($token, $state['offset'], $timeout);
        } catch (\RuntimeException $e) {
            $state['next_poll'] = $now->getTimestamp() + max(60, $e->getCode());
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
            $this->store->write($key, $state);
            if (is_array($update['callback_query'] ?? null)) {
                $elapsed = (int)((hrtime(true) - $started) / 1000000000);
                $results[] = $this->handler->handle($token, $domains, $update['callback_query'], $now->modify('+' . $elapsed . ' seconds'));
            }
        }
        return $results;
    }
}

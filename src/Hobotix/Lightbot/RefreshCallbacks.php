<?php

namespace Hobotix\Lightbot;

use DateTimeImmutable;

/** Only accepts updates retrieved from Telegram by the token-owning poller. */
final class RefreshCallbacks
{
    public function __construct(private RuntimeStore $store, private ScheduleCache $cache,
        private MessageComposer $composer, private TelegramClient $telegram) {}

    public function handle(string $token, array $domains, array $query, DateTimeImmutable $now): string
    {
        $id = $query['id'] ?? null;
        if (!is_string($id) || $id === '') {
            return 'ignored';
        }
        if (($query['data'] ?? null) !== 'dtek:refresh') {
            $this->telegram->answerCallback($token, $id, 'Ця кнопка недоступна в цьому чаті.');
            return 'ignored';
        }
        return $this->refresh($token, $domains, $query['message']['chat'] ?? [], $now, $id);
    }

    public function handleCommand(string $token, array $domains, array $message, string $username, DateTimeImmutable $now): string
    {
        $text = $message['text'] ?? null;
        $entity = $message['entities'][0] ?? [];
        if (!preg_match('/\A[A-Za-z0-9_]{5,32}\z/', $username) || !is_string($text)
            || ($message['from']['is_bot'] ?? true) !== false
            || ($entity['type'] ?? null) !== 'bot_command' || ($entity['offset'] ?? null) !== 0
            || !preg_match('/\A(\/dtek(?:@([A-Za-z0-9_]{5,32}))?)(?:\s.*)?\z/is', $text, $command)
            || ($entity['length'] ?? null) !== strlen($command[1])
            || (isset($command[2]) && strcasecmp($command[2], $username) !== 0)) {
            return 'ignored';
        }
        return $this->refresh($token, $domains, $message['chat'] ?? [], $now);
    }

    private function refresh(string $token, array $domains, array $chat, DateTimeImmutable $now, ?string $id = null): string
    {
        $started = hrtime(true);
        // Commands produce only the digest; callbacks additionally get private feedback.
        $notify = function (string $text) use ($token, $id): void {
            if ($id !== null) {
                $this->telegram->answerCallback($token, $id, $text);
            }
        };
        $matches = [];
        foreach ($domains as $domain => $config) {
            if ($token !== '' && ($config['tg_token'] ?? '') === $token && !empty($config['dtek'])
                && isset($chat['id']) && (string)($config['tg_chat_id'] ?? '') === (string)$chat['id']) {
                $matches[$domain] = $config;
            }
        }
        if (count($matches) !== 1
            || !in_array($chat['type'] ?? null, ['group', 'supergroup'], true)) {
            $notify('Ця кнопка недоступна в цьому чаті.');
            return 'ignored';
        }
        $domain = array_key_first($matches);
        $config = $matches[$domain];
        $key = 'refresh-' . hash('sha256', $token . ':' . $config['tg_chat_id']);
        $lock = null;
        $acknowledged = false;
        try {
            $lock = $this->store->lock($key);
            if ($lock === null) {
                $notify('Графік уже оновлюється. Зачекайте трохи.');
                return 'busy';
            }
            $state = $this->store->read($key);
            if ($state !== null && (!is_int($state['next_allowed'] ?? null) || !is_string($state['status'] ?? null))) {
                throw new \RuntimeException('Invalid callback state');
            }
            if (($state['next_allowed'] ?? 0) > $now->getTimestamp()) {
                $wait = $state['next_allowed'] - $now->getTimestamp();
                $notify('Без спаму, бляха. Наступне оновлення через ' . $wait . ' с.');
                return 'cooldown';
            }
            $state = ['next_allowed' => $now->getTimestamp() + 60, 'status' => 'sending',
                'requested_at' => $now->getTimestamp(), 'domain' => $domain];
            $this->store->write($key, $state);
            $notify('Отримую останній опублікований графік ДТЕК…');
            $acknowledged = true;
            $snapshot = $this->cache->refresh($config['dtek'], $now, requireFetch: true);
            $message = $this->composer->digest($domain, $config, $snapshot, $now);
            $notice = '🔄 Запит на оновлення: ' . Schedule::timeLabel($now->getTimestamp()) . ' · Київ';
            $message['rich'] = '<p><b>' . $notice . '</b></p>' . $message['rich'];
            $message['basic'] = '<b>' . $notice . "</b>\n\n" . $message['basic'];
            $result = $this->telegram->send($token, (string)$config['tg_chat_id'], $message);
            $respondedAt = $now->getTimestamp() + (int)ceil((hrtime(true) - $started) / 1000000000);
            $state = array_merge($state, $result, ['next_allowed' => max($state['next_allowed'], $respondedAt + $result['retry_after']),
                'feed_updated_at' => $snapshot['data']['feed_updated_at'] ?? null,
                'checked_at' => $snapshot['checked_at'] ?? null, 'stale' => $snapshot['stale']]);
            $this->store->write($key, $state);
            return $result['status'];
        } catch (\Throwable $e) {
            if (!$acknowledged) {
                $notify('Не вдалося оновити графік. Спробуйте трохи пізніше.');
            }
            return 'error';
        } finally {
            if ($lock !== null) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}

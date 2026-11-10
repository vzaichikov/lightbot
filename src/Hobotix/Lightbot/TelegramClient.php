<?php

namespace Hobotix\Lightbot;

final class TelegramClient
{
    public function __construct(private ?\Closure $request = null) {}

    public function send(string $token, string $chat, array $message): array
    {
        if ($token === '' || $chat === '') {
            return ['status' => 'rejected', 'code' => 'disabled', 'retry_after' => 0];
        }
        $method = 'sendRichMessage';
        $payload = ['chat_id' => $chat, 'rich_message' => ['html' => $message['rich']]];
        $options = isset($message['reply_markup']) ? ['reply_markup' => $message['reply_markup']] : [];
        $payload += $options;
        $response = $this->call($method, $token, $payload);
        $body = $response['body'] ?? null;
        $description = strtolower(is_string($body['description'] ?? null) ? $body['description'] : '');
        // Only a definite API rejection can safely trigger another send.
        if (empty($response['error']) && ($body['ok'] ?? null) === false
            && in_array($response['http'], [400, 404], true)
            && (str_contains($description, 'method not found')
                || (str_contains($description, 'rich message') && str_contains($description, 'not supported'))
                || str_contains($description, 'unsupported start tag'))) {
            $method = 'sendMessage';
            $response = $this->call($method, $token, ['chat_id' => $chat, 'text' => $message['basic'],
                'parse_mode' => 'HTML', 'link_preview_options' => ['is_disabled' => true]] + $options);
        }
        $body = $response['body'] ?? null;
        if (empty($response['error']) && $response['http'] === 200 && ($body['ok'] ?? null) === true
            && is_int($body['result']['message_id'] ?? null)) {
            return ['status' => 'sent', 'code' => 'ok', 'retry_after' => 0,
                'message_id' => $body['result']['message_id'], 'method' => $method];
        }
        if (empty($response['error']) && ($body['ok'] ?? null) === false && $response['http'] < 500) {
            return ['status' => 'rejected', 'code' => (string)($body['error_code'] ?? $response['http']),
                'retry_after' => max(0, (int)($body['parameters']['retry_after'] ?? 0))];
        }
        return ['status' => 'uncertain', 'code' => 'transport_or_invalid_response', 'retry_after' => 0];
    }

    public function answerCallback(string $token, string $id, string $text): void
    {
        $this->call('answerCallbackQuery', $token, ['callback_query_id' => $id, 'text' => $text, 'cache_time' => 0]);
    }

    public function updates(string $token, int $offset, int $timeout): array
    {
        $response = $this->call('getUpdates', $token, ['offset' => $offset, 'limit' => 20,
            'timeout' => max(0, min(20, $timeout)), 'allowed_updates' => ['callback_query', 'message']]);
        $body = $response['body'] ?? null;
        if (!empty($response['error']) || ($response['http'] ?? 0) !== 200 || ($body['ok'] ?? null) !== true
            || !is_array($body['result'] ?? null) || !array_is_list($body['result'])) {
            throw new \RuntimeException('Telegram polling failed', max(60, (int)($body['parameters']['retry_after'] ?? 0)));
        }
        return $body['result'];
    }

    public function username(string $token): string
    {
        $me = $this->apiResult('getMe', $token, []);
        if (!is_array($me) || ($me['is_bot'] ?? null) !== true || !is_string($me['username'] ?? null)
            || !preg_match('/\A[A-Za-z0-9_]{5,32}\z/', $me['username'])) {
            throw new \RuntimeException('Invalid bot identity');
        }
        return $me['username'];
    }

    /** Explicit operational action, never called by a polling worker. */
    public function registerDtekCommand(string $token, string $chat): void
    {
        $scope = ['scope' => ['type' => 'chat', 'chat_id' => $chat]];
        $commands = $this->apiResult('getMyCommands', $token, $scope);
        if (!is_array($commands) || !array_is_list($commands)) {
            throw new \RuntimeException('Invalid command list');
        }
        $commands = array_values(array_filter($commands, static fn(array $command): bool => ($command['command'] ?? '') !== 'dtek'));
        $commands[] = ['command' => 'dtek', 'description' => 'Оновити графік ДТЕК і надіслати в групу'];
        if (count($commands) > 100 || $this->apiResult('setMyCommands', $token, $scope + ['commands' => $commands]) !== true
            || $this->apiResult('getMyCommands', $token, $scope) !== $commands) {
            throw new \RuntimeException('Command registration failed');
        }
    }

    private function apiResult(string $method, string $token, array $payload): mixed
    {
        $response = $this->call($method, $token, $payload);
        if (!empty($response['error']) || ($response['http'] ?? 0) !== 200 || ($response['body']['ok'] ?? null) !== true) {
            throw new \RuntimeException('Telegram operation failed', max(60, (int)($response['body']['parameters']['retry_after'] ?? 0)));
        }
        return $response['body']['result'] ?? null;
    }

    private function call(string $method, string $token, array $payload): array
    {
        try {
            return $this->request !== null ? ($this->request)($method, $token, $payload) : self::request($method, $token, $payload);
        } catch (\Throwable $e) {
            return ['http' => 0, 'body' => null, 'error' => true];
        }
    }

    private static function request(string $method, string $token, array $payload): array
    {
        $ch = curl_init('https://api.telegram.org/bot' . $token . '/' . $method);
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode((object)$payload, JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $method === 'getUpdates' ? 30 : 15, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
                if (strlen($body) + strlen($chunk) > 512 * 1024) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['http' => $http, 'body' => json_decode($body, true), 'error' => $ok === false];
    }
}

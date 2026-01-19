<?php

namespace Hobotix\Lightbot;

final class LightBot
{
    private Database $database;

    public function __construct(?Database $database = null)
    {
        $this->database = $database ?? new Database(Config::getDb());
    }

    public static function echoLine(string $line, string $type = 'l'): void
    {
        echo match ($type) {
            'e' => "\033[31m$line \033[0m" . PHP_EOL,
            's' => "\033[32m$line \033[0m" . PHP_EOL,
            'w' => "\033[33m$line \033[0m" . PHP_EOL,
            'i' => "\033[36m$line \033[0m" . PHP_EOL,
            'd' => "\033[35m$line \033[0m" . PHP_EOL,
            default => $line . PHP_EOL,
        };
    }

    private function sendTelegramMessage(string $botToken, string $chatId, string $message): string
    {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

        $postFields = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        // curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    private function resolveStatus(array $config): bool
    {
        $simulated = getenv('LIGHTBOT_SIMULATE_STATUS');
        if ($simulated !== false) {
            $value = strtolower(trim($simulated));
            return in_array($value, ['1', 'true', 'on', 'yes'], true);
        }

        self::echoLine('Resolving device ' . $config['ip'] . ':' . $config['port'], 'd');

        $connection = @fsockopen(
            $config['ip'],
            (int)$config['port'],
            $errno,
            $errstr,
            (int)$config['timeout']
        );
        $status = ($connection !== false);

        if (is_resource($connection)) {
            fclose($connection);
        }

        return $status;
    }

    private function shouldSendTelegram(array $config): bool
    {
        return !empty($config['tg_token']) && !empty($config['tg_chat_id']);
    }

    public function checkStatus(): string
    {
        $domains = Config::getDomains();
        if ($domains === []) {
            self::echoLine('No domains configured. Nothing to check.', 'e');
            return '0';
        }

        $lastStatus = '0';

        foreach ($domains as $domain => $config) {
            $lastStatus = $this->checkDomainStatus($domain, $config);
        }

        return $lastStatus;
    }

    private function checkDomainStatus(string $domain, array $config): string
    {
        self::echoLine("Started checking {$domain}", 'i');

        $statusBool = $this->resolveStatus($config);

        if ($statusBool) {
            self::echoLine('Port was successfully opened, device is online, light is ON', 's');
        } else {
            self::echoLine('Port is closed, device supposed to be offline, light is OFF', 'e');
        }

        $currentStatus = (int)$statusBool;
        $now = new \DateTimeImmutable('now');
        $existingStatus = $this->database->fetchStatus($domain);

        if ($existingStatus === null) {
            self::echoLine('No previous status found, storing initial status', 'i');
            $this->database->insertInitialStatus($domain, $currentStatus, $now);
            return (string)$currentStatus;
        }

        $previousStatus = (int)$existingStatus['status'];
        $pendingStatus = $existingStatus['pending_status'];

        self::echoLine('Previous confirmed status was ' . $previousStatus, 'i');
        self::echoLine('Pending status value is ' . ($pendingStatus === null ? 'none' : $pendingStatus), 'i');

        if ($currentStatus === $previousStatus) {
            self::echoLine('Current status equals confirmed status, nothing changed', 's');
            $this->database->updateChecked($domain, null, $now);
            return (string)$currentStatus;
        }

        self::echoLine('Current status differs from confirmed, checking debounce...', 'w');

        if ($pendingStatus !== null && (int)$pendingStatus === $currentStatus) {
            self::echoLine('Same changed status detected twice in a row, confirming change and sending alert', 'w');
            if ($this->shouldSendTelegram($config)) {
                $this->sendTelegramMessage(
                    $config['tg_token'],
                    $config['tg_chat_id'],
                    Texts::get_random_text((bool)$currentStatus, $config['texts_block'])
                );
            }
            $this->database->confirmStatusChange($domain, $currentStatus, $now);
        } else {
            self::echoLine('First time we see this different status, waiting for next run to confirm', 'i');
            $this->database->updateChecked($domain, $currentStatus, $now);
        }

        return (string)$currentStatus;
    }

}

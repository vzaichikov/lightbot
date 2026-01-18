<?php

namespace Hobotix\Lightbot;

final class LightBot
{
    private string $status_dir;

    // Internal “pending” files to debounce status changes
    private const MAIN_PENDING_FILE = 'main_status_pending';
    private const GEN_PENDING_FILE  = 'gen_status_pending';

    public function __construct()
    {
        $this->status_dir = dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'status' . DIRECTORY_SEPARATOR;

        if (!is_dir($this->status_dir)) {
            mkdir($this->status_dir, 0755, true);
        }

        // Main line status files
        if (!file_exists($this->status_dir . Config::status_file)){
            touch($this->status_dir . Config::status_file);
        }

        if (!file_exists($this->status_dir . Config::time_on_file)){
            touch($this->status_dir . Config::time_on_file);
        }

        if (!file_exists($this->status_dir . Config::time_off_file)){
            touch($this->status_dir . Config::time_off_file);
        }

        // Generator status files
        if (!file_exists($this->status_dir . Config::gen_status_file)){
            touch($this->status_dir . Config::gen_status_file);
        }

        if (!file_exists($this->status_dir . Config::gen_time_on_file)){
            touch($this->status_dir . Config::gen_time_on_file);
        }

        if (!file_exists($this->status_dir . Config::gen_time_off_file)){
            touch($this->status_dir . Config::gen_time_off_file);
        }

        // Pending files for debounce logic
        if (!file_exists($this->status_dir . self::MAIN_PENDING_FILE)) {
            touch($this->status_dir . self::MAIN_PENDING_FILE);
        }

        if (!file_exists($this->status_dir . self::GEN_PENDING_FILE)) {
            touch($this->status_dir . self::GEN_PENDING_FILE);
        }
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

    private function writeCurrentStatus(bool $status, string $file): void
    {
        file_put_contents($this->status_dir . $file, (int)$status);

        if ($file == Config::status_file){
            if ($status){
                file_put_contents($this->status_dir . Config::time_on_file, (int)time());
            } else {
                file_put_contents($this->status_dir . Config::time_off_file, (int)time());
            }
        }

        if ($file == Config::gen_status_file){
            if ($status){
                file_put_contents($this->status_dir . Config::gen_time_on_file, (int)time());
            } else {
                file_put_contents($this->status_dir . Config::gen_time_off_file, (int)time());
            }
        }
    }

    private function getPreviousStatus(string $file): int
    {
        $contents = @file_get_contents($this->status_dir . $file);

        if ($contents === false || $contents === '') {
            return 0;
        }

        return (int)$contents;
    }

    private function setPendingStatus(string $file, int $status): void
    {
        file_put_contents($this->status_dir . $file, $status);
    }

    private function getPendingStatus(string $file): int
    {
        $contents = @file_get_contents($this->status_dir . $file);

        if ($contents === false || $contents === '') {
            return 0;
        }

        return (int)$contents;
    }

    private function sendTelegramMessage($botToken, $chatId, $message) {
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

    public function checkStatus(): string
    {
        // MAIN LINE
        self::echoLine('Started checking main line', 'i');

        $connection = @fsockopen(Config::ip, Config::port, $errno, $errstr, Config::timeout);
        $statusBool = ($connection !== false);

        if ($statusBool){
            self::echoLine('Port was successfully opened, device is online, light is ON', 's');
        } else {
            self::echoLine('Port is closed, device supposed to be offline, light is OFF', 'e');
        }

        $current_status  = (int)$statusBool;
        $previous_status = $this->getPreviousStatus(Config::status_file);
        $pending_status  = $this->getPendingStatus(self::MAIN_PENDING_FILE);

        self::echoLine('Previous confirmed status was ' . $previous_status, 'i');
        self::echoLine('Pending status value is ' . $pending_status, 'i');

        if ($current_status === $previous_status) {
            // Same as confirmed status: reset pending, nothing changed
            self::echoLine('Current status equals confirmed status, nothing changed', 's');
            $this->setPendingStatus(self::MAIN_PENDING_FILE, 0);
        } else {
            // Current differs from confirmed -> debounce
            self::echoLine('Current status differs from confirmed, checking debounce...', 'w');

            if ($pending_status === $current_status) {
                // Second run with the same new status → confirm change & alert
                self::echoLine('Same changed status detected twice in a row, confirming change and sending alert', 'w');
                $this->sendTelegramMessage(Config::tg_token, Config::tg_chat_id, Texts::get_random_text((bool)$current_status));
                $this->writeCurrentStatus((bool)$current_status, Config::status_file);
                $this->setPendingStatus(self::MAIN_PENDING_FILE, 0);
            } else {
                // First time we see this changed status
                self::echoLine('First time we see this different status, waiting for next run to confirm', 'i');
                $this->setPendingStatus(self::MAIN_PENDING_FILE, $current_status);
            }
        }

        // GENERATOR LINE
        if (Config::gen_ip){
            self::echoLine('Started checking generator line', 'i');

            $connection = @fsockopen(Config::gen_ip, Config::gen_port, $errno, $errstr, Config::gen_timeout);
            $genStatusBool = ($connection !== false);

            if ($genStatusBool){
                self::echoLine('Port was successfully opened, second device is online, gen is ON', 's');
            } else {
                self::echoLine('Port is closed, second device supposed to be offline, gen is OFF', 'e');
            }

            $gen_current_status  = (int)$genStatusBool;
            $gen_previous_status = $this->getPreviousStatus(Config::gen_status_file);
            $gen_pending_status  = $this->getPendingStatus(self::GEN_PENDING_FILE);

            self::echoLine('Generator previous confirmed status was ' . $gen_previous_status, 'i');
            self::echoLine('Generator pending status value is ' . $gen_pending_status, 'i');

            if ($gen_current_status === $gen_previous_status) {
                self::echoLine('Generator current status equals confirmed status, nothing changed', 's');
                $this->setPendingStatus(self::GEN_PENDING_FILE, 0);
            } else {
                self::echoLine('Generator current status differs from confirmed, checking debounce...', 'w');

                if ($gen_pending_status === $gen_current_status) {
                    self::echoLine('Same generator changed status detected twice in a row, confirming change and sending alert', 'w');
                    $this->sendTelegramMessage(Config::tg_token, Config::tg_chat_id, Texts2::get_random_text((bool)$gen_current_status));
                    $this->writeCurrentStatus((bool)$gen_current_status, Config::gen_status_file);
                    $this->setPendingStatus(self::GEN_PENDING_FILE, 0);
                } else {
                    self::echoLine('First time we see this different generator status, waiting for next run to confirm', 'i');
                    $this->setPendingStatus(self::GEN_PENDING_FILE, $gen_current_status);
                }
            }

            // Keep return behavior same as before: last checked status (generator)
            return (string)$gen_current_status;
        }

        // If no generator line, return main status
        return (string)$current_status;
    }

    public function getShedule()
    {
        // Still empty, still judging you for leaving empty methods.
    }
}

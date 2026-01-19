<?php

namespace Hobotix\Lightbot;

final class Config
{
    private const CONFIG_FILE = 'config.php';
    private const CONFIG_EXAMPLE_FILE = 'config.example.php';

    private static ?array $config = null;

    public static function getDb(): array
    {
        $config = self::loadConfig();

        return $config['db'] ?? [];
    }

    public static function getDomains(): array
    {
        $config = self::loadConfig();

        return $config['domains'] ?? [];
    }

    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $root = dirname(__DIR__, 3);
        $configPath = $root . DIRECTORY_SEPARATOR . self::CONFIG_FILE;
        $examplePath = $root . DIRECTORY_SEPARATOR . self::CONFIG_EXAMPLE_FILE;

        $config = [];
        if (file_exists($configPath)) {
            $config = require $configPath;
        } elseif (file_exists($examplePath)) {
            $config = require $examplePath;
        }

        if (!is_array($config)) {
            $config = [];
        }

        self::$config = $config;

        return self::$config;
    }
}

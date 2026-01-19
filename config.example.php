<?php

return [
    'db' => [
        'host' => '127.0.0.1',
        'database' => 'lightbot',
        'user' => 'lightbot',
        'password' => 'change-me',
        'charset' => 'utf8mb4',
    ],
    'domains' => [
        'light.hobotix.dev' => [
            'ip' => '203.0.113.10',
            'port' => 8899,
            'timeout' => 5,
            'tg_token' => 'change-me',
            'tg_chat_id' => '-1000000000000',
        ],
        'light.charmpole.dance' => [
            'ip' => '203.0.113.11',
            'port' => 8899,
            'timeout' => 5,
            'tg_token' => 'change-me',
            'tg_chat_id' => '-1000000000001',
        ],
    ],
];

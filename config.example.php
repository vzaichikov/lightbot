<?php

return [
    'db' => [
        'host' => 'localhost',
        'database' => 'lightbot',
        'user' => 'lightbot',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'domains' => [
        'example.com' => [
            'ip' => '127.0.0.1',
            'port' => 80,
            'timeout' => 2,
            'tg_token' => '',
            'tg_chat_id' => '',
        ],
    ],
];

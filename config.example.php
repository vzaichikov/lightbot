<?php

return [
    'db' => [
        'host' => '127.0.0.1',
        'database' => 'db',
        'user' => 'user',
        'password' => 'change-me',
        'charset' => 'utf8mb4',
    ],
    'domains' => [
        'light.hobotix.dev' => [
            'label' => 'Hobotix',
            'dtek' => ['region' => 'kyiv-region', 'group' => 'GPV5.2', 'address' => 'Київщина'],
            'ip' => 'change-me',
            'port' => 8899,
            'timeout' => 5,
            'tg_token' => 'change-me',
            'tg_chat_id' => 'change-me',
            'texts_block' => 'default',
        ],
        'light.charmpole.dance' => [
            'label' => 'Charmpole',
            'dtek' => ['region' => 'kyiv', 'group' => 'GPV14.1', 'address' => 'Київ, Берестейський 56А'],
            'ip' => 'change-me',
            'port' => 443,
            'timeout' => 5,
            'tg_token' => 'change-me',
            'tg_chat_id' => 'change-me',
            'texts_block' => 'charmpole',
        ],
    ],
];

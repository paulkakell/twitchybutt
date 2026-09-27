<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),
    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME', 'smtps'),
            'host' => env('MAIL_HOST', 'localhost'),
            'port' => (int) env('MAIL_PORT', 465),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => 10,
        ],
        'array' => ['transport' => 'array'],
    ],
    'from' => ['address' => env('MAIL_FROM_ADDRESS', 'noreply@example.test'), 'name' => env('MAIL_FROM_NAME', 'Creator site')],
];

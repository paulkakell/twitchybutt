<?php

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\NullHandler;

return [
    'default' => env('LOG_CHANNEL', 'daily'),
    'deprecations' => ['channel' => 'null', 'trace' => false],
    'channels' => [
        'daily' => ['driver' => 'daily', 'path' => storage_path('logs/cms.json'), 'level' => env('LOG_LEVEL', 'info'), 'days' => 14, 'permission' => 0600, 'formatter' => JsonFormatter::class, 'replace_placeholders' => true],
        'null' => ['driver' => 'monolog', 'handler' => NullHandler::class],
    ],
];

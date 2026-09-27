<?php

return [
    'default' => 'database',
    'connections' => [
        'media' => ['driver' => 'database', 'connection' => null, 'table' => 'jobs', 'queue' => 'media', 'retry_after' => 300, 'after_commit' => true],
        'database' => ['driver' => 'database', 'connection' => null, 'table' => 'jobs', 'queue' => 'account-mail', 'retry_after' => 90, 'after_commit' => true],
    ],
    // Failed payloads and exception text must not be persisted to an unredacted table.
    'failed' => ['driver' => 'null'],
];

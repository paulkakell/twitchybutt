<?php

return [
    'default' => 'database',
    'connections' => [
        'database' => ['driver' => 'database', 'connection' => null, 'table' => 'jobs', 'queue' => 'account-mail', 'retry_after' => 90, 'after_commit' => true],
    ],
    // Failed payloads and exception text must not be persisted to an unredacted table.
    'failed' => ['driver' => 'null'],
];

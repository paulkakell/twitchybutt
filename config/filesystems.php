<?php

return [
    'default' => 'local',
    'disks' => [
        'local' => ['driver' => 'local', 'root' => storage_path('app/private'), 'serve' => false, 'visibility' => 'private', 'throw' => true, 'report' => false],
    ],
    // No generic signed-file route or public symlink may expose quarantined originals.
    'links' => [],
];

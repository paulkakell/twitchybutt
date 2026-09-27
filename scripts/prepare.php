<?php

$root = dirname(__DIR__);
foreach (['bootstrap/cache', 'storage/app/private', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'build'] as $directory) {
    $path = $root.'/'.$directory;
    if (! is_dir($path) && ! mkdir($path, 0770, true) && ! is_dir($path)) {
        throw new RuntimeException('Cannot prepare '.$directory);
    }
}
if (! file_exists($root.'/database/database.sqlite')) {
    touch($root.'/database/database.sqlite');
    chmod($root.'/database/database.sqlite', 0600);
}

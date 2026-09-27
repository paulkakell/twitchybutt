<?php

use Illuminate\Http\Request;

// Keep engine/bootstrap diagnostics out of HTTP responses, including before Laravel boots.
ini_set('display_errors', '0');
define('LARAVEL_START', microtime(true));
try {
    require __DIR__.'/../vendor/autoload.php';
    (require_once __DIR__.'/../bootstrap/app.php')->handleRequest(Request::capture());
} catch (Throwable) {
    if (! headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: private, no-store, max-age=0');
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
    }
    error_log('{"event":"cms.bootstrap.failed"}');
    echo 'Service unavailable.';
}

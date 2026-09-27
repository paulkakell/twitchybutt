<?php

return [
    'idle_minutes' => (int) env('CMS_SESSION_IDLE_MINUTES', 30),
    'absolute_minutes' => (int) env('CMS_SESSION_ABSOLUTE_MINUTES', 720),
    'mail_enabled' => env('CMS_ACCOUNT_MAIL_ENABLED', false),
];

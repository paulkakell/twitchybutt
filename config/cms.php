<?php

return [
    'version' => trim(file_get_contents(base_path('VERSION'))),
    'payments_enabled' => (bool) env('CMS_PAYMENTS_ENABLED', false),
    'restricted_publishing_enabled' => (bool) env('CMS_RESTRICTED_PUBLISHING_ENABLED', false),
    'token_label' => 'TEST',
    'token_decimals' => 6,
];

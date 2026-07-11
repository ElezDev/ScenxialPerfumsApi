<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('FRONTEND_URL', 'http://localhost:5173')))
    ),
    'allowed_origins_patterns' => env('APP_ENV', 'production') === 'local'
        ? ['#^http://localhost:\d+$#', '#^http://127\.0\.0\.1:\d+$#']
        : [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];

<?php

declare(strict_types=1);

return [
    'paths' => ['v1/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        mb_trim(...),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'Idempotency-Key',
        // Conditional requests: If-None-Match for cheap re-fetches, If-Match to stop lost updates.
        'If-Match',
        'If-None-Match',
        'Origin',
        'X-Request-Id',
        'X-Requested-With',
    ],

    'exposed_headers' => [
        'Content-Language',
        'Deprecation',
        // A browser client cannot send an ETag back unless it can read it.
        'ETag',
        'Idempotency-Key',
        'Idempotency-Replayed',
        'Last-Modified',
        'Link',
        'Retry-After',
        'Sunset',
        'X-Request-Id',
    ],

    'max_age' => 0,

    'supports_credentials' => false,
];

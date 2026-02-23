<?php

declare(strict_types=1);

return [
    'paths' => ['v1/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin): string => trim($origin),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'Idempotency-Key',
        'Origin',
        'X-Request-Id',
        'X-Requested-With',
    ],

    'exposed_headers' => [
        'Content-Language',
        'Deprecation',
        'Idempotency-Key',
        'Idempotency-Replayed',
        'Link',
        'Retry-After',
        'Sunset',
        'X-Request-Id',
    ],

    'max_age' => 0,

    'supports_credentials' => false,
];

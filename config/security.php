<?php

declare(strict_types=1);

return [
    'force_https' => (bool) env('SECURITY_FORCE_HTTPS', false),

    'hsts' => [
        'enabled' => (bool) env('SECURITY_HSTS_ENABLED', true),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        'include_subdomains' => (bool) env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', true),
        'preload' => (bool) env('SECURITY_HSTS_PRELOAD', false),
    ],

    'trusted_proxies' => env('TRUSTED_PROXIES', '*'),

    'trusted_hosts' => array_values(array_filter(array_map(
        static fn (string $host): string => trim($host),
        explode(',', (string) env('TRUSTED_HOSTS', '')),
    ))),
];

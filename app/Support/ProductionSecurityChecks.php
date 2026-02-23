<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class ProductionSecurityChecks
{
    public static function assertForEnvironment(string $environment): void
    {
        if ($environment !== 'production') {
            return;
        }

        if ((bool) config('app.debug', false)) {
            throw new RuntimeException('In production, APP_DEBUG must be false.');
        }

        if (! (bool) config('security.force_https', false)) {
            throw new RuntimeException('In production, SECURITY_FORCE_HTTPS must be enabled.');
        }

        $appUrl = mb_strtolower((string) config('app.url', ''));
        if (! str_starts_with($appUrl, 'https://')) {
            throw new RuntimeException('In production, APP_URL must use https://.');
        }

        $allowedOrigins = config('cors.allowed_origins', []);
        if (is_array($allowedOrigins) && in_array('*', $allowedOrigins, true)) {
            throw new RuntimeException('In production, CORS allowed origins must not use wildcard "*".');
        }

        $trustedHosts = config('security.trusted_hosts', []);
        if (! is_array($trustedHosts) || $trustedHosts === []) {
            throw new RuntimeException('In production, TRUSTED_HOSTS must be configured.');
        }
    }
}

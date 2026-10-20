<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * The rate an endpoint grants with WebHook-Allowed-Rate (CloudEvents webhook specification,
 * section 4.2.2): a positive number of deliveries a minute, or "*" for no limit.
 */
final class AllowedRate
{
    /**
     * The rate to deliver at, from the granted value, or the one asked for when none was granted:
     * the spec says a callback without the header, such as a browser's GET, grants the requested
     * rate. Null means no limit.
     */
    public static function from(?string $granted): ?int
    {
        $granted = mb_trim((string) $granted);

        if ('*' === $granted) {
            return null;
        }

        if (1 === preg_match('/^[1-9]\d{0,8}$/', $granted)) {
            return (int) $granted;
        }

        return self::requested();
    }

    /**
     * The rate asked for with WebHook-Request-Rate, from webhooks.request_rate, or null to ask for
     * none.
     */
    public static function requested(): ?int
    {
        $rate = config('webhooks.request_rate');

        return is_numeric($rate) && (int) $rate > 0 ? (int) $rate : null;
    }
}

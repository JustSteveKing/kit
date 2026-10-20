<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Models\WebhookSubscription;
use Illuminate\Support\Facades\URL;

/**
 * The WebHook-Request-Callback URL of the CloudEvents webhook handshake (section 4.1.3): a delivery
 * target that cannot answer the OPTIONS request in time, or an administrator who finds the URL in a
 * log, grants consent by calling it with GET or POST. The signature is the secret that makes it
 * hard to guess, url_hash ties the consent to the URL that was asked, and request ties it to one
 * validation request, so a callback from an earlier request cannot replace a newer grant.
 */
final class ConsentCallback
{
    public const string ROUTE = 'v1.webhooks.consent';

    public static function url(WebhookSubscription $subscription, string $request): string
    {
        return URL::temporarySignedRoute(
            self::ROUTE,
            now()->addHours(config()->integer('webhooks.callback_hours', 72)),
            ['webhook' => $subscription->id, 'url_hash' => self::urlHash($subscription->url), 'request' => $request],
        );
    }

    public static function urlHash(string $url): string
    {
        return hash('sha256', $url);
    }
}

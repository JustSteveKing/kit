<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Models\WebhookSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Asks an endpoint for consent to deliveries, by the CloudEvents webhook handshake (section 4): an
 * OPTIONS request with WebHook-Request-Origin, answered with WebHook-Allowed-Origin naming this
 * origin or "*". Status codes are not consent, so only that header validates the subscription.
 */
final class ValidateWebhookEndpoint implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $subscriptionId,
    ) {}

    public function handle(WebhookTargets $targets): void
    {
        $subscription = WebhookSubscription::query()->find($this->subscriptionId);

        if ( ! $subscription instanceof WebhookSubscription) {
            return;
        }

        $origin = config()->string('webhooks.origin');
        $url = $subscription->url;

        // Each request supersedes the last: only its answer or its callback may grant consent.
        $request = bin2hex(random_bytes(16));
        $subscription->forceFill(['validation_request' => $request])->save();

        try {
            $address = $targets->resolve($url);
            $host = (string) parse_url($url, PHP_URL_HOST);
            $port = parse_url($url, PHP_URL_PORT) ?? ('https' === parse_url($url, PHP_URL_SCHEME) ? 443 : 80);

            // The callback lets the target consent later instead, by GET or POST (section 4.1.3).
            $response = Http::withHeaders(array_filter([
                'WebHook-Request-Origin' => $origin,
                'WebHook-Request-Callback' => ConsentCallback::url($subscription, $request),
                'WebHook-Request-Rate' => null === AllowedRate::requested() ? null : (string) AllowedRate::requested(),
            ]))
                ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$address}"]]])
                ->withoutRedirecting()
                ->timeout(config()->integer('webhooks.timeout', 10))
                ->send('OPTIONS', $url);
        } catch (RefusedTarget|ConnectionException) {
            return;
        }

        // The URL may have changed while this was queued; consent was given to the URL asked.
        $subscription->refresh();

        if ($subscription->url === $url && $subscription->validation_request === $request && in_array(mb_trim($response->header('WebHook-Allowed-Origin')), [$origin, '*'], true)) {
            $subscription->forceFill([
                'validated_at' => now(),
                'allowed_rate' => AllowedRate::from($response->header('WebHook-Allowed-Rate')),
            ])->save();
        }
    }
}

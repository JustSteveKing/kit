<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Models\WebhookDelivery;
use App\Models\WebhookDeliveryAttempt;
use App\Models\WebhookEvent as WebhookEventModel;
use App\Models\WebhookSubscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Makes one delivery attempt, records it, and schedules the next from config('webhooks.backoff')
 * itself, so the published schedule is exactly the one that runs: Laravel's own backoff repeats its
 * last value once the list runs out.
 */
final class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    private const int RESPONSE_EXCERPT = 2048;

    /**
     * What the signature covers: the whole target URI, and the authority and path that
     * api-toolbox's signature.verify requires by default, so its receivers verify these as they are.
     */
    private const array COVERED = ['@method', '@target-uri', '@authority', '@path', 'content-type', 'content-digest'];

    public function __construct(
        public readonly string $deliveryId,
    ) {}

    public function handle(WebhookTargets $targets): void
    {
        $delivery = WebhookDelivery::query()->with(['subscription', 'event'])->find($this->deliveryId);

        if ( ! $delivery instanceof WebhookDelivery || in_array($delivery->state, [DeliveryState::Succeeded, DeliveryState::Failed], true)) {
            return;
        }

        $subscription = $delivery->subscription;

        if ( ! $subscription->receivesDeliveries()) {
            $this->finish($delivery, DeliveryState::Failed);

            return;
        }

        // The rate the endpoint granted. Waiting for it is not an attempt, so it costs no retry.
        if ($this->overRate($subscription)) {
            self::dispatch($delivery->id)->delay(RateLimiter::availableIn($this->rateKey($subscription)));

            return;
        }

        $number = $delivery->attempts()->count() + 1;
        $attempt = new WebhookDeliveryAttempt(['attempt' => $number, 'attempted_at' => now()]);
        $started = hrtime(true);
        $retry = true;
        $response = null;

        try {
            $response = $this->send($delivery, $targets->resolve($subscription->url));
            $attempt->response_status = $response->status();
            $attempt->response_body = Str::limit($response->body(), self::RESPONSE_EXCERPT, '');
        } catch (RefusedTarget $exception) {
            // A refused address will not become acceptable on retry.
            $attempt->error = $exception->getMessage();
            $retry = false;
        } catch (ConnectionException $exception) {
            $attempt->error = Str::limit($exception->getMessage(), 250, '');
        }

        $attempt->duration_ms = (int) ((hrtime(true) - $started) / 1_000_000);
        $delivery->attempts()->save($attempt);

        $status = $attempt->response_status;

        if (null !== $status && $status >= 200 && $status < 300) {
            $this->finish($delivery, DeliveryState::Succeeded);

            return;
        }

        // CloudEvents webhook specification, section 2.2: 410 means the target is retired and the
        // sender should stop, and 415 means the format is not understood, which a retry won't fix.
        if (410 === $status) {
            $this->finish($delivery, DeliveryState::Failed);
            $this->disable($subscription, __('api.webhooks.disabled_gone'));

            return;
        }

        if (415 === $status) {
            $retry = false;
        }

        $backoff = $this->schedule();

        if ($retry && $number <= count($backoff)) {
            // A 429 names when to try again, and the sender must not try sooner.
            $delay = max($backoff[$number - 1], 429 === $status ? $this->secondsFromRetryAfter($response) : 0);
            $delivery->update(['state' => DeliveryState::Retrying, 'next_attempt_at' => now()->addSeconds($delay)]);
            self::dispatch($delivery->id)->delay($delay);

            return;
        }

        $this->finish($delivery, DeliveryState::Failed);
    }

    private function send(WebhookDelivery $delivery, string $address): Response
    {
        $subscription = $delivery->subscription;
        $url = $subscription->url;
        $host = (string) parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?? ('https' === parse_url($url, PHP_URL_SCHEME) ? 443 : 80);

        return Http::withSignature($subscription->id, self::COVERED)
            ->withHeaders([
                'Idempotency-Key' => '"' . $delivery->event->id . '"',
                'WebHook-Request-Origin' => config()->string('webhooks.origin'),
                'User-Agent' => config()->string('app.name', 'Laravel') . ' Webhooks',
            ])
            ->withOptions(['curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$address}"]]])
            ->withoutRedirecting()
            ->timeout(config()->integer('webhooks.timeout', 10))
            ->acceptJson()
            ->withBody((string) json_encode($delivery->event->payload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), WebhookEventModel::CONTENT_TYPE)
            ->post($url);
    }

    private function overRate(WebhookSubscription $subscription): bool
    {
        // The sync queue runs a delayed job at once, so waiting there would loop; a rate needs a
        // queue that can delay.
        if (null === $subscription->allowed_rate || 'sync' === $this->job?->getConnectionName()) {
            return false;
        }

        // Increment first, then compare: the increment is atomic, so two workers delivering to one
        // subscription each claim their own slot. Checking before incrementing would let both pass.
        return RateLimiter::hit($this->rateKey($subscription), 60) > $subscription->allowed_rate;
    }

    private function rateKey(WebhookSubscription $subscription): string
    {
        return "webhooks:rate:{$subscription->id}";
    }

    private function finish(WebhookDelivery $delivery, DeliveryState $state): void
    {
        $delivery->update(['state' => $state, 'next_attempt_at' => null]);

        $subscription = $delivery->subscription;

        if (DeliveryState::Succeeded === $state) {
            $subscription->forceFill(['consecutive_failures' => 0])->save();

            return;
        }

        $subscription->increment('consecutive_failures');
        $this->disableIfFailing($subscription->refresh());
    }

    private function disableIfFailing(WebhookSubscription $subscription): void
    {
        $limit = config()->integer('webhooks.disable_after', 10);

        if ($limit > 0 && $subscription->consecutive_failures >= $limit) {
            $this->disable($subscription, __('api.webhooks.disabled_after_failures', ['count' => (string) $limit]));
        }
    }

    private function disable(WebhookSubscription $subscription, string $reason): void
    {
        if ($subscription->isEnabled()) {
            $subscription->forceFill(['disabled_at' => now(), 'disabled_reason' => $reason])->save();
        }
    }

    /**
     * Seconds from a Retry-After field, which is either a number of seconds or an HTTP date.
     */
    private function secondsFromRetryAfter(?Response $response): int
    {
        $value = mb_trim((string) $response?->header('Retry-After'));

        if ('' === $value) {
            return 0;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $date = strtotime($value);

        return false === $date ? 0 : max(0, $date - now()->getTimestamp());
    }

    /**
     * @return list<int>
     */
    private function schedule(): array
    {
        return array_values(array_filter(
            array_map(static fn(mixed $seconds): int => is_numeric($seconds) ? (int) $seconds : -1, config()->array('webhooks.backoff', [])),
            static fn(int $seconds): bool => $seconds >= 0,
        ));
    }
}

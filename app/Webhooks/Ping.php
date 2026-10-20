<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Models\WebhookSubscription;

/**
 * Sent on request, to check an endpoint receives and verifies deliveries.
 */
final readonly class Ping implements WebhookEvent
{
    public const string TYPE = 'webhook.ping';

    public function __construct(
        private WebhookSubscription $subscription,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function subject(): string
    {
        return $this->subscription->id;
    }

    /**
     * @return array{subscription_id: string}
     */
    public function data(): array
    {
        return ['subscription_id' => $this->subscription->id];
    }
}

<?php

declare(strict_types=1);

namespace App\Webhooks;

use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEvent as StoredEvent;
use App\Models\WebhookSubscription;

/**
 * Records events and queues their deliveries. Jobs are dispatched after the surrounding transaction
 * commits, so a subscriber is never told about a row it then cannot find.
 */
final class Webhooks
{
    /**
     * Sends the event to every enabled subscription of the owner that asked for its type.
     *
     * @return list<WebhookDelivery>
     */
    public function dispatch(User $owner, WebhookEvent $event): array
    {
        $subscriptions = $owner->webhookSubscriptions()->enabled()->get()
            ->filter(static fn(WebhookSubscription $subscription): bool => $subscription->receivesDeliveries() && $subscription->wants($event->type()));

        if ($subscriptions->isEmpty()) {
            return [];
        }

        $stored = $this->store($owner, $event);

        return array_values($subscriptions
            ->map(fn(WebhookSubscription $subscription): WebhookDelivery => $this->queue($subscription, $stored))
            ->all());
    }

    /**
     * Sends the event to one subscription, whatever types it asked for: used for pings.
     */
    public function deliverTo(WebhookSubscription $subscription, WebhookEvent $event): WebhookDelivery
    {
        return $this->queue($subscription, $this->store($subscription->user, $event));
    }

    /**
     * A new delivery of an event already sent, with the same body and event ID.
     */
    public function redeliver(WebhookDelivery $delivery): WebhookDelivery
    {
        return $this->queue($delivery->subscription, $delivery->event);
    }

    private function store(User $owner, WebhookEvent $event): StoredEvent
    {
        return StoredEvent::query()->create([
            'user_id' => $owner->id,
            'type' => $event->type(),
            'source' => config()->string('webhooks.source'),
            'subject' => $event->subject(),
            'data' => $event->data(),
        ]);
    }

    private function queue(WebhookSubscription $subscription, StoredEvent $event): WebhookDelivery
    {
        $delivery = WebhookDelivery::query()->create([
            'webhook_subscription_id' => $subscription->id,
            'webhook_event_id' => $event->id,
            'state' => DeliveryState::Pending,
        ]);

        DeliverWebhook::dispatch($delivery->id)->afterCommit();

        return $delivery;
    }
}

<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * Something a webhook subscriber can be told about, delivered as a CloudEvents 1.0 event. Implement
 * it once per event type, list the type in config/webhooks.php, and dispatch it with
 * Webhooks::dispatch().
 */
interface WebhookEvent
{
    /**
     * Dotted and past tense, such as project.created. Subscribers filter on it and branch on it.
     */
    public function type(): string;

    /**
     * CloudEvents' subject: what the event is about within this API, such as the project's ID, so a
     * subscriber can route or filter without parsing data. Null when there is no single subject.
     */
    public function subject(): ?string;

    /**
     * The event's data member. Keep it stable: a subscriber parses it.
     *
     * @return array<string, mixed>
     */
    public function data(): array;
}

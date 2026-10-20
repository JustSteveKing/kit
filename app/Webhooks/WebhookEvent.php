<?php

declare(strict_types=1);

namespace App\Webhooks;

/**
 * Something a webhook subscriber can be told about. Implement it once per event type, list the type
 * in config/webhooks.php, and dispatch it with Webhooks::dispatch().
 */
interface WebhookEvent
{
    /**
     * Dotted and past tense, such as project.created. Subscribers filter on it and branch on it.
     */
    public function type(): string;

    /**
     * The payload's data member. Keep it stable: a subscriber parses it.
     *
     * @return array<string, mixed>
     */
    public function data(): array;
}

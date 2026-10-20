<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WebhookDelivery;
use App\Models\WebhookDeliveryAttempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WebhookDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WebhookDelivery $delivery */
        $delivery = $this->resource;

        return [
            'id' => $delivery->id,
            'event' => [
                'id' => $delivery->event->id,
                'type' => $delivery->event->type,
            ],
            'state' => $delivery->state->value,
            'next_attempt_at' => $delivery->next_attempt_at?->toAtomString(),
            'created_at' => $delivery->created_at?->toAtomString(),
            'attempts' => $delivery->attempts->map(static fn(WebhookDeliveryAttempt $attempt): array => [
                'attempt' => $attempt->attempt,
                'response_status' => $attempt->response_status,
                'response_body' => $attempt->response_body,
                'error' => $attempt->error,
                'duration_ms' => $attempt->duration_ms,
                'attempted_at' => $attempt->attempted_at->toAtomString(),
            ])->all(),
        ];
    }
}

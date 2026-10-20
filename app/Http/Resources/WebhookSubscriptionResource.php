<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\WebhookSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WebhookSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WebhookSubscription $subscription */
        $subscription = $this->resource;

        return [
            'id' => $subscription->id,
            'url' => $subscription->url,
            'description' => $subscription->description,
            'events' => $subscription->events,
            'enabled' => $subscription->isEnabled(),
            'validated_at' => $subscription->validated_at?->toAtomString(),
            'allowed_rate' => $subscription->allowed_rate,
            'disabled_at' => $subscription->disabled_at?->toAtomString(),
            'disabled_reason' => $subscription->disabled_reason,
            'created_at' => $subscription->created_at?->toAtomString(),
            'updated_at' => $subscription->updated_at?->toAtomString(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Webhooks\DeliveryState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property string $id
 * @property string $webhook_subscription_id
 * @property string $webhook_event_id
 * @property DeliveryState $state
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WebhookSubscription $subscription
 * @property-read WebhookEvent $event
 */
final class WebhookDelivery extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['webhook_subscription_id', 'webhook_event_id', 'state', 'next_attempt_at'];

    /**
     * @return BelongsTo<WebhookSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }

    /**
     * @return BelongsTo<WebhookEvent, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(WebhookEvent::class, 'webhook_event_id');
    }

    /**
     * @return HasMany<WebhookDeliveryAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(WebhookDeliveryAttempt::class)->orderBy('attempt');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'state' => DeliveryState::class,
            'next_attempt_at' => 'datetime',
        ];
    }
}

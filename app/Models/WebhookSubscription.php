<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WebhookSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Override;

/**
 * @property string $id
 * @property string $user_id
 * @property string $url
 * @property string|null $description
 * @property list<string> $events
 * @property string $secret
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 * @property string|null $disabled_reason
 * @property Carbon|null $validated_at
 * @property int|null $allowed_rate
 * @property string|null $validation_request
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
final class WebhookSubscription extends Model
{
    /** @use HasFactory<WebhookSubscriptionFactory> */
    use HasFactory;
    use HasUlids;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['url', 'description', 'events', 'secret'];

    /**
     * @var list<string>
     */
    #[Override]
    protected $hidden = ['secret'];

    public static function newSecret(): string
    {
        return 'whsec_' . bin2hex(random_bytes(32));
    }

    public static function validationRequired(): bool
    {
        return config()->boolean('webhooks.validate_endpoints', false);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function isEnabled(): bool
    {
        return null === $this->disabled_at;
    }

    /**
     * Whether deliveries may go to it: enabled, and, when endpoint validation is on, consented.
     */
    public function receivesDeliveries(): bool
    {
        return $this->isEnabled() && ($this->validated_at instanceof Carbon || ! self::validationRequired());
    }

    public function wants(string $type): bool
    {
        return in_array('*', $this->events, true) || in_array($type, $this->events, true);
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function enabled(Builder $query): void
    {
        $query->whereNull('disabled_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'disabled_at' => 'datetime',
            'validated_at' => 'datetime',
            'allowed_rate' => 'integer',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * An event as it was sent: every delivery and redelivery of it sends this same CloudEvent.
 *
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property string $source
 * @property string|null $subject
 * @property array<string, mixed> $data
 * @property Carbon $created_at
 */
final class WebhookEvent extends Model
{
    use HasUlids;
    use MassPrunable;

    public const null UPDATED_AT = null;

    public const string SPEC_VERSION = '1.0';

    public const string CONTENT_TYPE = 'application/cloudevents+json; charset=utf-8';

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['user_id', 'type', 'source', 'subject', 'data'];

    /**
     * The body every delivery sends: a CloudEvent in structured content mode (CloudEvents 1.0 and
     * its JSON format). An event with no subject leaves the attribute out rather than sending null.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return array_filter([
            'specversion' => self::SPEC_VERSION,
            'id' => $this->id,
            'source' => $this->source,
            'type' => $this->type,
            'subject' => $this->subject,
            'time' => $this->created_at->toRfc3339String(),
            'datacontenttype' => 'application/json',
            'data' => $this->data,
        ], static fn(mixed $value): bool => null !== $value);
    }

    /**
     * Deliveries and their attempts go with the event, through the foreign keys.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return self::query()->where('created_at', '<', now()->subDays(config()->integer('webhooks.retention_days', 30)));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

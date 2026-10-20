<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An event as it was sent: every delivery and redelivery of it sends this same body.
 *
 * @property string $id
 * @property string $user_id
 * @property string $type
 * @property array<string, mixed> $data
 * @property Carbon $created_at
 */
final class WebhookEvent extends Model
{
    use HasUlids;
    use MassPrunable;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'type', 'data'];

    /**
     * The body every delivery sends.
     *
     * @return array{id: string, type: string, api_version: string, created_at: string, data: array<string, mixed>}
     */
    public function payload(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'api_version' => 'v1',
            'created_at' => $this->created_at->toAtomString(),
            'data' => $this->data,
        ];
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

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Override;

/**
 * One HTTP attempt. A null response_status means no response arrived, which needs a different fix
 * from a response that said 500.
 *
 * @property int $id
 * @property string $webhook_delivery_id
 * @property int $attempt
 * @property int|null $response_status
 * @property string|null $response_body
 * @property string|null $error
 * @property int|null $duration_ms
 * @property Carbon $attempted_at
 */
final class WebhookDeliveryAttempt extends Model
{
    #[Override]
    public $timestamps = false;

    /**
     * @var list<string>
     */
    #[Override]
    protected $fillable = ['attempt', 'response_status', 'response_body', 'error', 'duration_ms', 'attempted_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
        ];
    }
}

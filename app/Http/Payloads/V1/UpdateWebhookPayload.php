<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Only the fields that were sent: an absent field stays as it is.
 */
final readonly class UpdateWebhookPayload
{
    /**
     * @param  array{url?: string, description?: string|null, events?: list<string>}  $changes
     */
    public function __construct(
        public array $changes,
        public ?bool $enabled,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = self::validatedData($request);
        $changes = [];

        if (array_key_exists('url', $data)) {
            $changes['url'] = Arr::string($data, 'url');
        }

        if (array_key_exists('description', $data)) {
            $changes['description'] = null === $data['description'] ? null : Arr::string($data, 'description');
        }

        if (array_key_exists('events', $data)) {
            $changes['events'] = array_values(array_filter(Arr::array($data, 'events'), is_string(...)));
        }

        return new self(
            changes: $changes,
            enabled: array_key_exists('enabled', $data) ? Arr::boolean($data, 'enabled') : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function validatedData(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $request instanceof FormRequest ? $request->validated() : $request->all();

        return $data;
    }
}

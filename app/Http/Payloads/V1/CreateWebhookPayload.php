<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final readonly class CreateWebhookPayload
{
    /**
     * @param  list<string>  $events
     */
    public function __construct(
        public string $url,
        public ?string $description,
        public array $events,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = self::validatedData($request);

        return new self(
            url: Arr::string($data, 'url'),
            description: isset($data['description']) ? Arr::string($data, 'description') : null,
            events: array_values(array_filter(Arr::array($data, 'events'), is_string(...))),
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

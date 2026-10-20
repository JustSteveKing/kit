<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

final readonly class CreateTokenPayload
{
    /**
     * @param  list<string>  $abilities
     */
    public function __construct(
        public string $name,
        public array $abilities,
        public ?Carbon $expiresAt,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = self::validatedData($request);

        return new self(
            name: Arr::string($data, 'name'),
            abilities: array_values(array_filter(Arr::array($data, 'abilities'), is_string(...))),
            expiresAt: isset($data['expires_at']) ? Carbon::parse(Arr::string($data, 'expires_at')) : null,
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

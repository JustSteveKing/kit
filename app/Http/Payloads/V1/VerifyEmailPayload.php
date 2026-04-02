<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

final readonly class VerifyEmailPayload
{
    public function __construct(
        public string $id,
        public string $hash,
    ) {}

    public static function fromReqest(Request $request): self
    {
        $data = self::validatedData($request);

        $id = $data['id'] ?? '';
        $hash = $data['hash'] ?? '';

        return new self(
            id: is_string($id) ? $id : '',
            hash: is_string($hash) ? $hash : '',
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

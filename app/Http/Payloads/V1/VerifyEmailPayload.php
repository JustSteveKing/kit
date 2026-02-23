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

        return new self(
            id: (string) $data['id'],
            hash: (string) $data['hash'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function validatedData(Request $request): array
    {
        if ($request instanceof FormRequest) {
            return $request->validated();
        }

        return $request->all();
    }
}

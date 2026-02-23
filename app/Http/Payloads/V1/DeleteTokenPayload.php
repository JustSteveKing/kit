<?php

declare(strict_types=1);

namespace App\Http\Payloads\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

final readonly class DeleteTokenPayload
{
    public function __construct(
        public int $tokenId,
    ) {}

    public static function fromReqest(Request $request): self
    {
        $data = self::validatedData($request);

        return new self(
            tokenId: (int) $data['token_id'],
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

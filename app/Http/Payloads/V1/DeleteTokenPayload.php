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
        $tokenId = $data['token_id'] ?? 0;

        return new self(
            tokenId: is_numeric($tokenId) ? (int) $tokenId : 0,
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

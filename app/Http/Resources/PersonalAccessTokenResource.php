<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Laravel\Sanctum\PersonalAccessToken;

final class PersonalAccessTokenResource extends JsonApiResource
{
    public function toId(Request $request): ?string
    {
        /** @var PersonalAccessToken $token */
        $token = $this->resource;

        return (string) $token->getKey();
    }

    public function toType(Request $request): ?string
    {
        return 'personal-access-tokens';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        /** @var PersonalAccessToken $token */
        $token = $this->resource;

        $currentTokenId = $request->user()?->currentAccessToken()?->getKey();

        return [
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toAtomString(),
            'expires_at' => $token->expires_at?->toAtomString(),
            'created_at' => $token->created_at?->toAtomString(),
            'updated_at' => $token->updated_at?->toAtomString(),
            'is_current' => $currentTokenId !== null && (string) $currentTokenId === (string) $token->getKey(),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;
use Laravel\Sanctum\PersonalAccessToken;

final class PersonalAccessTokenResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        assert($this->resource instanceof PersonalAccessToken);
        $token = $this->resource;
        $id = $token->getKey();

        return is_string($id) || is_int($id) ? (string) $id : '';
    }

    public function toType(Request $request): string
    {
        return 'personal-access-tokens';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        assert($this->resource instanceof PersonalAccessToken);
        $token = $this->resource;

        $currentTokenId = $request->user()?->currentAccessToken()?->getKey();
        $tokenId = $token->getKey();

        $currentTokenIdStr = is_string($currentTokenId) || is_int($currentTokenId) ? (string) $currentTokenId : '';
        $tokenIdStr = is_string($tokenId) || is_int($tokenId) ? (string) $tokenId : '';

        return [
            'name' => $token->name,
            'abilities' => $token->abilities,
            'last_used_at' => $token->last_used_at?->toAtomString(),
            'expires_at' => $token->expires_at?->toAtomString(),
            'created_at' => $token->created_at?->toAtomString(),
            'updated_at' => $token->updated_at?->toAtomString(),
            'is_current' => $currentTokenId !== null && $currentTokenIdStr === $tokenIdStr,
        ];
    }
}

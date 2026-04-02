<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

final class UserResource extends JsonApiResource
{
    public function toId(Request $request): string
    {
        assert($this->resource instanceof User);
        $user = $this->resource;
        $id = $user->getKey();

        return is_string($id) || is_int($id) ? (string) $id : '';
    }

    public function toType(Request $request): string
    {
        return 'users';
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Request $request): array
    {
        assert($this->resource instanceof User);
        $user = $this->resource;

        return [
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toAtomString(),
            'created_at' => $user->created_at?->toAtomString(),
            'updated_at' => $user->updated_at?->toAtomString(),
        ];
    }
}

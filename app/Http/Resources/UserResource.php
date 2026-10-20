<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class UserResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, email: string, email_verified_at: ?string, created_at: ?string, updated_at: ?string}
     */
    public function toArray(Request $request): array
    {
        /** @var User $user */
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toAtomString(),
            'created_at' => $user->created_at?->toAtomString(),
            'updated_at' => $user->updated_at?->toAtomString(),
        ];
    }
}

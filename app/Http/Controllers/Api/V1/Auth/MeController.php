<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Token Authentication')]
#[Endpoint(
    title: 'Get Current User',
    description: 'Return the user the bearer token belongs to. Use it to check a token still works and to read the account\'s profile and verification state. The response carries an ETag: send it back in If-None-Match and an unchanged user returns 304 with no body.',
)]
#[Authenticated]
#[ResponseFromApiResource(name: UserResource::class, model: User::class, status: 200, description: 'Authenticated user profile.')]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/unauthorized',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
    ],
    status: 401,
    description: 'Authentication failed.',
)]
final class MeController
{
    public function __invoke(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        return UserResource::make($user)->response();
    }
}

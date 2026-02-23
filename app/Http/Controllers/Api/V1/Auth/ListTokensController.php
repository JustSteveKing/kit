<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Token Authentication')]
#[Endpoint(title: 'List Tokens', description: 'List personal access tokens for the authenticated user.')]
#[Authenticated]
#[Response(
    content: [
        'data' => [[
            'id' => '1',
            'type' => 'personal-access-tokens',
            'attributes' => [
                'name' => 'ios-app',
                'abilities' => ['auth:me', 'auth:logout'],
                'last_used_at' => null,
                'expires_at' => '2026-02-24T12:00:00+00:00',
                'created_at' => '2026-02-23T12:00:00+00:00',
                'updated_at' => '2026-02-23T12:00:00+00:00',
                'is_current' => true,
            ],
        ]],
    ],
    status: 200,
    description: 'Token list payload.'
)]
#[Response(content: ['message' => 'Forbidden.'], status: 403, description: 'Token is missing required ability.')]
#[Response(content: ['message' => 'Unauthenticated.'], status: 401, description: 'Authentication failed.')]
final class ListTokensController
{
    public function __invoke(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $tokens = $user->tokens()
            ->latest('id')
            ->get();

        SecurityAudit::log('auth.tokens.listed', [
            'user_id' => (string) $user->getKey(),
            'count' => $tokens->count(),
        ]);

        return PersonalAccessTokenResource::collection($tokens)
            ->additional([
                'meta' => [
                    'count' => $tokens->count(),
                ],
            ])
            ->response();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Token Authentication')]
#[Endpoint(
    title: 'Revoke All Tokens',
    description: 'Revoke every personal access token the authenticated user holds, including the one making this request. Use it to sign out everywhere after a suspected compromise. Returns 204 with no body; every later request with these tokens is a 401.',
)]
#[Authenticated]
#[ScribeResponse(content: null, status: 204, description: 'All tokens revoked.')]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Forbidden.',
    ],
    status: 403,
    description: 'Token is missing required ability.',
)]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/unauthorized',
        'title' => 'Unauthorized',
        'status' => 401,
        'detail' => 'Unauthenticated.',
    ],
    status: 401,
    description: 'Authentication failed.',
)]
final class DeleteAllTokensController
{
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $deletedCount = $user->tokens()->count();
        $user->tokens()->delete();

        SecurityAudit::log('auth.tokens.revoked_all', [
            'user_id' => $user->id,
            'count' => $deletedCount,
        ]);

        return response()->noContent();
    }
}

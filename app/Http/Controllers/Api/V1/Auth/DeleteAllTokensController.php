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
#[Endpoint(title: 'Revoke All Tokens', description: 'Revoke all personal access tokens for the authenticated user.')]
#[Authenticated]
#[ScribeResponse(content: null, status: 204, description: 'All tokens revoked.')]
#[ScribeResponse(content: ['message' => 'Forbidden.'], status: 403, description: 'Token is missing required ability.')]
#[ScribeResponse(content: ['message' => 'Unauthenticated.'], status: 401, description: 'Authentication failed.')]
final class DeleteAllTokensController
{
    public function __invoke(#[CurrentUser] User $user): Response
    {
        $deletedCount = $user->tokens()->count();
        $user->tokens()->delete();

        SecurityAudit::log('auth.tokens.revoked_all', [
            'user_id' => (string) $user->getKey(),
            'count' => $deletedCount,
        ]);

        return response()->noContent();
    }
}

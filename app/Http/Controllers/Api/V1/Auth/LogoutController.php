<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\Subgroup;
use Laravel\Sanctum\PersonalAccessToken;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Token Authentication')]
#[Endpoint(
    title: 'Logout',
    description: 'Revoke the bearer token that made this request, and only that one. Use it when a client signs out; the user\'s other tokens keep working. Returns 204 with no body.',
)]
#[Authenticated]
#[ScribeResponse(content: null, status: 204, description: 'Token revoked.')]
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
final class LogoutController
{
    public function __invoke(Request $request, #[CurrentUser] User $user): Response
    {
        $token = $user->currentAccessToken();

        // Sanctum types this as the token model, but a session-authenticated request gets a
        // TransientToken, which has nothing to revoke.
        // @phpstan-ignore instanceof.alwaysTrue
        if ($token instanceof PersonalAccessToken) {
            SecurityAudit::log('auth.logout.succeeded', [
                'user_id' => $user->id,
                'token_id' => (string) $token->id,
            ]);

            $token->delete();
        } else {
            SecurityAudit::log('auth.logout.succeeded', [
                'user_id' => $user->id,
                'token_id' => null,
            ]);
        }

        return response()->noContent();
    }
}

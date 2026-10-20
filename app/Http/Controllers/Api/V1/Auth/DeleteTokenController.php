<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Payloads\V1\DeleteTokenPayload;
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
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Token Authentication')]
#[Endpoint(
    title: 'Revoke Token',
    description: 'Revoke one of the authenticated user\'s personal access tokens by its ID, such as a lost device\'s. Use List Tokens to find the ID. Returns 204 with no body; a token the user does not own, or that no longer exists, is a 404.',
)]
#[Authenticated]
#[UrlParam('token_id', type: 'integer', description: 'Personal access token id.', required: true, example: 42)]
#[ScribeResponse(content: null, status: 204, description: 'Token revoked.')]
#[ScribeResponse(
    content: [
        'type' => 'https://apiguide.dev/errors/resource-not-found',
        'title' => 'Resource Not Found',
        'status' => 404,
        'detail' => 'Token not found.',
    ],
    status: 404,
    description: 'Token does not belong to current user or no longer exists.',
)]
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
final class DeleteTokenController
{
    public function __invoke(Request $request, #[CurrentUser] User $user): Response
    {
        $payload = DeleteTokenPayload::fromRequest($request);

        $token = $user->tokens()->whereKey($payload->tokenId)->first();

        if ( ! $token) {
            SecurityAudit::log('auth.tokens.revoke_failed', [
                'user_id' => $user->id,
                'token_id' => (string) $payload->tokenId,
                'reason' => 'not_found',
            ]);

            abort(404, __('api.auth.token_not_found'));
        }

        SecurityAudit::log('auth.tokens.revoked', [
            'user_id' => $user->id,
            'token_id' => (string) $token->id,
        ]);

        $token->delete();

        return response()->noContent();
    }
}

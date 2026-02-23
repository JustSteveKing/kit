<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Payloads\V1\DeleteTokenPayload;
use App\Http\Requests\Auth\DeleteTokenRequest;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response as ScribeResponse;
use Knuckles\Scribe\Attributes\Subgroup;
use Knuckles\Scribe\Attributes\UrlParam;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Token Authentication')]
#[Endpoint(title: 'Revoke Token', description: 'Revoke one personal access token owned by the authenticated user.')]
#[Authenticated]
#[UrlParam('token_id', type: 'integer', description: 'Personal access token id.', required: true, example: 42)]
#[ScribeResponse(content: null, status: 204, description: 'Token revoked.')]
#[ScribeResponse(content: ['message' => 'Token not found.'], status: 404, description: 'Token does not belong to current user or no longer exists.')]
#[ScribeResponse(content: ['message' => 'Forbidden.'], status: 403, description: 'Token is missing required ability.')]
#[ScribeResponse(content: ['message' => 'Unauthenticated.'], status: 401, description: 'Authentication failed.')]
final class DeleteTokenController
{
    public function __invoke(DeleteTokenRequest $request, #[CurrentUser] User $user): Response|JsonResponse
    {
        $payload = DeleteTokenPayload::fromReqest($request);

        $token = $user->tokens()->whereKey($payload->tokenId)->first();

        if (! $token) {
            SecurityAudit::log('auth.tokens.revoke_failed', [
                'user_id' => (string) $user->getKey(),
                'token_id' => (string) $payload->tokenId,
                'reason' => 'not_found',
            ]);

            return new JsonResponse([
                'message' => __('api.auth.token_not_found'),
            ], 404);
        }

        SecurityAudit::log('auth.tokens.revoked', [
            'user_id' => (string) $user->getKey(),
            'token_id' => (string) $token->getKey(),
        ]);

        $token->delete();

        return response()->noContent();
    }
}

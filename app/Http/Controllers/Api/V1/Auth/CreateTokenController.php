<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Payloads\V1\CreateTokenPayload;
use App\Http\Requests\Auth\CreateTokenRequest;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Models\User;
use App\Support\AccessTokens;
use App\Support\SecurityAudit;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Subgroup;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Token Authentication')]
#[Endpoint(
    title: 'Create API Key',
    description: 'Create a long-lived personal access token with chosen abilities, for a script, integration or agent that should not sign in with a password. It can only hold abilities the token making this request holds, and expires on expires_at, or after the configured default when that is left out. Returns the token with its plain-text value in meta.token, which is shown once and cannot be read again.',
)]
#[Authenticated]
#[Header('Idempotency-Key', '"token-retry-1"')]
#[BodyParam('name', type: 'string', description: 'A label for the key, shown when listing tokens.', required: true, example: 'deploy-bot')]
#[BodyParam('abilities', type: 'string[]', description: 'Abilities to grant, each one held by the token making this request.', required: true, example: ['auth:me', 'auth:tokens:read'])]
#[BodyParam('expires_at', type: 'string', description: 'ISO 8601 expiry, in the future and no later than the configured maximum (365 days by default). Leave it out for the default (90 days).', required: false, example: '2027-01-01T00:00:00+00:00')]
#[Response(
    content: [
        'data' => [
            'id' => '7',
            'name' => 'deploy-bot',
            'abilities' => ['auth:me', 'auth:tokens:read'],
            'last_used_at' => null,
            'expires_at' => '2027-01-01T00:00:00+00:00',
            'created_at' => '2026-10-20T12:00:00+00:00',
            'updated_at' => '2026-10-20T12:00:00+00:00',
            'is_current' => false,
        ],
        'meta' => ['token' => '7|example-token', 'token_type' => 'Bearer'],
    ],
    status: 201,
    description: 'API key created. meta.token is the only time its value is returned.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'You can only grant abilities your current token holds.',
        'errors' => ['abilities.0' => ['You can only grant abilities your current token holds.']],
    ],
    status: 422,
    description: 'A requested ability is not held by this token, or the expiry is invalid.',
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/forbidden',
        'title' => 'Forbidden',
        'status' => 403,
        'detail' => 'Forbidden.',
    ],
    status: 403,
    description: 'Token is missing the auth:tokens:create ability.',
)]
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
final class CreateTokenController
{
    public function __invoke(CreateTokenRequest $request, #[CurrentUser] User $user, AccessTokens $tokens): JsonResponse
    {
        $payload = CreateTokenPayload::fromRequest($request);

        $token = $tokens->issueApiKey(
            $user,
            $payload->name,
            $payload->abilities,
            $payload->expiresAt ?? $tokens->defaultApiKeyExpiry(),
        );

        SecurityAudit::log('auth.tokens.created', [
            'user_id' => $user->id,
            'token_id' => (string) $token->accessToken->id,
            'abilities' => $payload->abilities,
            'expires_at' => $token->accessToken->expires_at?->toAtomString(),
        ]);

        return PersonalAccessTokenResource::make($token->accessToken)->additional([
            'meta' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
            ],
        ])->response()->setStatusCode(201);
    }
}

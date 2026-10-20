<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Payloads\V1\RegisterPayload;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\AccessTokens;
use App\Support\SecurityAudit;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Endpoint;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\ResponseFromApiResource;
use Knuckles\Scribe\Attributes\Subgroup;
use Knuckles\Scribe\Attributes\Unauthenticated;

#[Group(name: 'Authentication')]
#[Subgroup(name: 'Token Authentication')]
#[Endpoint(
    title: 'Register',
    description: 'Create an account and issue its first bearer token. Use it once per new user; send an Idempotency-Key so a retried request cannot create a second account. Returns the user with the token, its type and expiry in meta, and sends a verification email.',
)]
#[Unauthenticated]
#[BodyParam('name', type: 'string', description: 'Display name for the account.', required: true, example: 'Jane Doe')]
#[BodyParam('email', type: 'string', description: 'Unique email address for login.', required: true, example: 'jane@example.com')]
#[BodyParam('password', type: 'string', description: 'Account password.', required: true, example: 'password123')]
#[BodyParam('device_name', type: 'string', description: 'Client device label for token tracking.', required: false, example: 'ios-app')]
#[ResponseFromApiResource(
    name: UserResource::class,
    model: User::class,
    status: 201,
    description: 'Registration succeeded.',
    additional: ['meta' => ['token' => '1|example-token', 'token_type' => 'Bearer', 'expires_at' => null]],
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The email has already been taken.',
        'errors' => ['email' => ['The email has already been taken.']],
    ],
    status: 422,
    description: 'Validation failed.',
)]
final class RegisterController
{
    public function __invoke(RegisterRequest $request, AccessTokens $tokens): JsonResponse
    {
        $payload = RegisterPayload::fromRequest($request);

        $user = User::create([
            'name' => $payload->name,
            'email' => $payload->email,
            'password' => $payload->password,
        ]);

        event(new Registered($user));

        $token = $tokens->issueSession($user, $payload->deviceName);
        $expiresAt = $token->accessToken->expires_at;

        SecurityAudit::log('auth.register.succeeded', [
            'user_id' => $user->id,
            'email_hash' => SecurityAudit::hashEmail($user->email),
            'device_name' => $payload->deviceName,
            'token_expires_at' => $expiresAt?->toAtomString(),
        ]);

        return UserResource::make($user)->additional([
            'meta' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt?->toAtomString(),
            ],
        ])->response()->setStatusCode(201);
    }
}

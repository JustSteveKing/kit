<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Payloads\V1\LoginPayload;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\AccessTokens;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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
    title: 'Login',
    description: 'Exchange an email and password for a bearer token. Use it when a user signs in on a new device, naming the device in device_name. Returns the user with the token, its type and expiry in meta; wrong credentials are a 422 on the email field.',
)]
#[Unauthenticated]
#[BodyParam('email', type: 'string', description: 'User email address.', required: true, example: 'jane@example.com')]
#[BodyParam('password', type: 'string', description: 'User password.', required: true, example: 'password123')]
#[BodyParam('device_name', type: 'string', description: 'Client device label for token tracking.', required: true, example: 'ios-app')]
#[ResponseFromApiResource(
    name: UserResource::class,
    model: User::class,
    status: 200,
    description: 'Login succeeded.',
    additional: ['meta' => ['token' => '1|example-token', 'token_type' => 'Bearer', 'expires_at' => null]],
)]
#[Response(
    content: [
        'type' => 'https://apiguide.dev/errors/validation-failed',
        'title' => 'Validation Failed',
        'status' => 422,
        'detail' => 'The provided credentials are incorrect.',
        'errors' => ['email' => ['The provided credentials are incorrect.']],
    ],
    status: 422,
    description: 'Credentials were invalid.',
)]
final class LoginController
{
    public function __invoke(LoginRequest $request, AccessTokens $tokens): JsonResponse
    {
        $payload = LoginPayload::fromRequest($request);

        $user = User::query()->where('email', $payload->email)->first();

        if ( ! $user || ! Hash::check($payload->password, $user->password)) {
            SecurityAudit::log('auth.login.failed', [
                'email_hash' => SecurityAudit::hashEmail($payload->email),
                'device_name' => $payload->deviceName,
            ]);

            throw ValidationException::withMessages([
                'email' => [__('api.auth.invalid_credentials')],
            ]);
        }

        $token = $tokens->issueSession($user, $payload->deviceName);
        $expiresAt = $token->accessToken->expires_at;

        SecurityAudit::log('auth.login.succeeded', [
            'user_id' => $user->id,
            'email_hash' => SecurityAudit::hashEmail($payload->email),
            'device_name' => $payload->deviceName,
            'token_expires_at' => $expiresAt?->toAtomString(),
        ]);

        return UserResource::make($user)->additional([
            'meta' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt?->toAtomString(),
            ],
        ])->response();
    }
}

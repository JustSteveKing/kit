<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Payloads\V1\LoginPayload;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
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
#[Endpoint(title: 'Login', description: 'Authenticate a user and issue a Sanctum bearer token.')]
#[Unauthenticated]
#[BodyParam('email', type: 'string', description: 'User email address.', required: true, example: 'jane@example.com')]
#[BodyParam('password', type: 'string', description: 'User password.', required: true, example: 'password123')]
#[BodyParam('device_name', type: 'string', description: 'Client device label for token tracking.', required: true, example: 'ios-app')]
#[ResponseFromApiResource(
    name: UserResource::class,
    model: User::class,
    status: 200,
    description: 'Login succeeded.',
    additional: ['meta' => ['token' => '1|example-token', 'token_type' => 'Bearer', 'expires_at' => null]]
)]
#[Response(
    content: [
        'message' => 'The given data was invalid.',
        'errors' => ['email' => ['The provided credentials are incorrect.']],
    ],
    status: 422,
    description: 'Credentials were invalid.'
)]
final class LoginController
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $payload = LoginPayload::fromReqest($request);

        $user = User::query()->where('email', $payload->email)->first();

        if (! $user || ! Hash::check($payload->password, $user->password)) {
            SecurityAudit::log('auth.login.failed', [
                'email_hash' => SecurityAudit::hashEmail($payload->email),
                'device_name' => $payload->deviceName,
            ]);

            throw ValidationException::withMessages([
                'email' => [__('api.auth.invalid_credentials')],
            ]);
        }

        [$token, $expiresAt] = $this->issueToken($user, $payload->deviceName);

        SecurityAudit::log('auth.login.succeeded', [
            'user_id' => (string) $user->getKey(),
            'email_hash' => SecurityAudit::hashEmail($payload->email),
            'device_name' => $payload->deviceName,
            'token_expires_at' => $expiresAt?->toAtomString(),
        ]);

        return UserResource::make($user)->additional([
            'meta' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt?->toAtomString(),
            ],
        ])->response();
    }

    /**
     * @return array{0:string,1:\Illuminate\Support\Carbon|null}
     */
    private function issueToken(User $user, string $deviceName): array
    {
        $configuredExpiration = config('sanctum.expiration');
        $expirationMinutes = filter_var(
            $configuredExpiration,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        $expiresAt = $expirationMinutes !== false
            ? now()->addMinutes($expirationMinutes)
            : null;

        $token = $user->createToken($deviceName, $this->defaultAbilities(), $expiresAt);

        return [$token->plainTextToken, $expiresAt];
    }

    /**
     * @return list<string>
     */
    private function defaultAbilities(): array
    {
        $abilities = config('sanctum.abilities.default', []);

        if (! is_array($abilities)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $ability): string => trim((string) $ability), $abilities),
            static fn (string $ability): bool => $ability !== '',
        ));
    }
}

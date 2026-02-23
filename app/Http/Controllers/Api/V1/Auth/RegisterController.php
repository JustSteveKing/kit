<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Payloads\V1\RegisterPayload;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
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
#[Endpoint(title: 'Register', description: 'Create a new account and issue a Sanctum bearer token.')]
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
    additional: ['meta' => ['token' => '1|example-token', 'token_type' => 'Bearer', 'expires_at' => null]]
)]
#[Response(
    content: [
        'message' => 'The given data was invalid.',
        'errors' => ['email' => ['The email has already been taken.']],
    ],
    status: 422,
    description: 'Validation failed.'
)]
final class RegisterController
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $payload = RegisterPayload::fromReqest($request);

        $user = User::create([
            'name' => $payload->name,
            'email' => $payload->email,
            'password' => $payload->password,
        ]);

        event(new Registered($user));

        [$token, $expiresAt] = $this->issueToken($user, $payload->deviceName);

        SecurityAudit::log('auth.register.succeeded', [
            'user_id' => (string) $user->getKey(),
            'email_hash' => SecurityAudit::hashEmail($user->email),
            'device_name' => $payload->deviceName,
            'token_expires_at' => $expiresAt?->toAtomString(),
        ]);

        return UserResource::make($user)->additional([
            'meta' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt?->toAtomString(),
            ],
        ])->response()->setStatusCode(201);
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

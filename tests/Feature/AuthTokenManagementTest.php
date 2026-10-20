<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\ListTokensController;
use App\Models\User;
use App\Support\AccessTokens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('requires auth for token management endpoints', function (): void {
    $this->getJson('/v1/auth/tokens')->assertUnauthorized();
    $this->deleteJson('/v1/auth/tokens/1')->assertUnauthorized();
    $this->deleteJson('/v1/auth/tokens')->assertUnauthorized();
});

it('lists only the authenticated user tokens', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $current = $user->createToken('current-device', ['auth:tokens:read']);
    $user->createToken('secondary-device', ['auth:tokens:read']);
    $otherUser->createToken('other-device', ['auth:tokens:read']);

    $response = $this->withToken($current->plainTextToken)
        ->getJson('/v1/auth/tokens')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonCount(2)
        ->assertLinkHeader('first')
        ->assertNoLinkHeader('next');

    /** @var array<int, array<string, mixed>> $data */
    $data = $response->json();
    $isCurrentValues = array_map(
        static fn(array $item): bool => (bool) ($item['is_current'] ?? false),
        $data,
    );

    expect($isCurrentValues)->toContain(true);
});

it('forbids listing tokens when token lacks required ability', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('limited-token', ['auth:me'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/v1/auth/tokens')
        ->assertProblem(403)
        ->assertJsonPath('detail', __('api.errors.forbidden'));
});

it('revokes a specific token owned by the authenticated user', function (): void {
    $user = User::factory()->create();

    $requestToken = $user->createToken('request-token', ['auth:tokens:delete']);
    $targetToken = $user->createToken('target-token', ['auth:tokens:read']);

    $this->withToken($requestToken->plainTextToken)
        ->deleteJson('/v1/auth/tokens/' . $targetToken->accessToken->id)
        ->assertNoContent();

    expect(PersonalAccessToken::query()->whereKey($targetToken->accessToken->id)->exists())->toBeFalse();
    expect(PersonalAccessToken::query()->whereKey($requestToken->accessToken->id)->exists())->toBeTrue();
});

it('returns not found when revoking a token not owned by the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $requestToken = $user->createToken('request-token', ['auth:tokens:delete']);
    $otherToken = $otherUser->createToken('other-token', ['auth:tokens:read']);

    $this->withToken($requestToken->plainTextToken)
        ->deleteJson('/v1/auth/tokens/' . $otherToken->accessToken->id)
        ->assertProblemType('https://apiguide.dev/errors/resource-not-found', 404)
        ->assertJsonPath('detail', __('api.auth.token_not_found'));
});

it('answers 404 for a token id that is not a number', function (): void {
    $user = User::factory()->create();
    $requestToken = $user->createToken('request-token', ['auth:tokens:delete']);

    $this->withToken($requestToken->plainTextToken)
        ->deleteJson('/v1/auth/tokens/not-an-integer')
        ->assertProblemType('https://apiguide.dev/errors/resource-not-found', 404);
});

it('forbids single token revocation when token lacks required ability', function (): void {
    $user = User::factory()->create();
    $targetToken = $user->createToken('target-token', ['auth:tokens:read']);
    $limitedToken = $user->createToken('limited-token', ['auth:me'])->plainTextToken;

    $this->withToken($limitedToken)
        ->deleteJson('/v1/auth/tokens/' . $targetToken->accessToken->id)
        ->assertProblem(403)
        ->assertJsonPath('detail', __('api.errors.forbidden'));
});

it('revokes all tokens for the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $requestToken = $user->createToken('request-token', ['auth:tokens:delete']);
    $user->createToken('secondary-token', ['auth:tokens:read']);
    $otherToken = $otherUser->createToken('other-token', ['auth:tokens:read']);

    $this->withToken($requestToken->plainTextToken)
        ->deleteJson('/v1/auth/tokens')
        ->assertNoContent();

    expect(PersonalAccessToken::query()->where('tokenable_id', $user->getKey())->exists())->toBeFalse();
    expect(PersonalAccessToken::query()->whereKey($otherToken->accessToken->id)->exists())->toBeTrue();
});

it('forbids bulk token revocation when token lacks required ability', function (): void {
    $user = User::factory()->create();
    $limitedToken = $user->createToken('limited-token', ['auth:me'])->plainTextToken;

    $this->withToken($limitedToken)
        ->deleteJson('/v1/auth/tokens')
        ->assertProblem(403)
        ->assertJsonPath('detail', __('api.errors.forbidden'));
});

it('pages token lists with link headers', function (): void {
    $user = User::factory()->create();
    $current = $user->createToken('current-device', ['auth:tokens:read']);

    foreach (range(1, ListTokensController::PER_PAGE) as $device) {
        $user->createToken('device-' . $device);
    }

    $first = $this->withToken($current->plainTextToken)
        ->getJson('/v1/auth/tokens')
        ->assertOk()
        ->assertJsonCount(ListTokensController::PER_PAGE)
        ->assertLinkHeader('first');

    preg_match('/<([^>]+)>;\\s*rel="next"/', (string) $first->headers->get('Link'), $next);

    expect($next)->toHaveKey(1);

    $this->withToken($current->plainTextToken)
        ->getJson($next[1])
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'current-device')
        ->assertLinkHeader('prev');
});

it('rejects token list cursors that cannot be decoded', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('cursor-device', ['auth:tokens:read'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/v1/auth/tokens?cursor=not-a-cursor')
        ->assertProblemType('https://apiguide.dev/errors/invalid-pagination-cursor', 400);
});

it('creates an api key holding a subset of the current token abilities', function (): void {
    $user = User::factory()->create();
    $session = $user->createToken('session', ['auth:me', 'auth:tokens:read', 'auth:tokens:create']);

    $response = $this->withToken($session->plainTextToken)
        ->postJson('/v1/auth/tokens', [
            'name' => 'deploy-bot',
            'abilities' => ['auth:me'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'deploy-bot')
        ->assertJsonPath('data.abilities', ['auth:me'])
        ->assertJsonPath('data.is_current', false)
        ->assertJsonPath('meta.token_type', 'Bearer');

    $key = PersonalAccessToken::findToken((string) $response->json('meta.token'));

    expect($key)->not->toBeNull();
    expect($key?->expires_at?->isSameDay(now()->addDays(90)))->toBeTrue();

    app('auth')->forgetGuards();

    $this->withToken((string) $response->json('meta.token'))->getJson('/v1/auth/me')->assertOk();

    app('auth')->forgetGuards();

    $this->withToken((string) $response->json('meta.token'))->getJson('/v1/auth/tokens')->assertProblem(403);
});

it('refuses to grant an ability the current token does not hold', function (): void {
    $user = User::factory()->create();
    $session = $user->createToken('session', ['auth:me', 'auth:tokens:create']);

    $this->withToken($session->plainTextToken)
        ->postJson('/v1/auth/tokens', [
            'name' => 'too-broad',
            'abilities' => ['auth:me', 'auth:tokens:delete'],
        ])
        ->assertProblemType('https://apiguide.dev/errors/validation-failed', 422)
        ->assertJsonPath('errors', ['abilities.1' => [__('api.auth.ability_not_grantable')]]);

    expect($user->tokens()->count())->toBe(1);
});

it('refuses abilities no route checks, even from a token holding every ability', function (): void {
    $user = User::factory()->create();
    $session = $user->createToken('session', ['*']);

    $this->withToken($session->plainTextToken)
        ->postJson('/v1/auth/tokens', [
            'name' => 'made-up',
            'abilities' => ['billing:refund'],
        ])
        ->assertProblem(422);
});

it('lets an api key outlive a session token, up to the configured maximum', function (): void {
    $user = User::factory()->create();
    $session = $user->createToken('session', ['auth:tokens:create', 'auth:me']);

    $this->withToken($session->plainTextToken)
        ->postJson('/v1/auth/tokens', [
            'name' => 'year-long',
            'abilities' => ['auth:me'],
            'expires_at' => now()->addDays(365)->toAtomString(),
        ])
        ->assertCreated();

    $this->withToken($session->plainTextToken)
        ->postJson('/v1/auth/tokens', [
            'name' => 'too-long',
            'abilities' => ['auth:me'],
            'expires_at' => now()->addDays(366)->toAtomString(),
        ])
        ->assertProblem(422)
        ->assertJsonValidationErrors(['expires_at']);

    $this->travel(3)->hours();
    app('auth')->forgetGuards();

    $key = $user->tokens()->where('name', 'year-long')->firstOrFail();

    expect($key->expires_at?->isSameDay(now()->addDays(365)->subHours(3)))->toBeTrue();
});

it('keeps an api key working after session tokens have expired', function (): void {
    $user = User::factory()->create();
    $session = app(AccessTokens::class)->issueSession($user, 'laptop');

    $key = $this->withToken($session->plainTextToken)
        ->postJson('/v1/auth/tokens', ['name' => 'nightly-job', 'abilities' => ['auth:me']])
        ->assertCreated()
        ->json('meta.token');

    $this->travel(config()->integer('sanctum.lifetimes.session_minutes') + 1)->minutes();
    app('auth')->forgetGuards();

    $this->withToken($session->plainTextToken)->getJson('/v1/auth/me')->assertUnauthorized();

    app('auth')->forgetGuards();

    $this->withToken((string) $key)->getJson('/v1/auth/me')->assertOk();
});

it('requires the tokens create ability to make an api key', function (): void {
    $user = User::factory()->create();
    $session = $user->createToken('session', ['auth:me']);

    $this->withToken($session->plainTextToken)
        ->postJson('/v1/auth/tokens', ['name' => 'nope', 'abilities' => ['auth:me']])
        ->assertProblem(403);
});

it('does not create a second key when a create request is retried', function (): void {
    $user = User::factory()->create();
    $session = $user->createToken('session', ['auth:me', 'auth:tokens:create']);
    $headers = ['Idempotency-Key' => 'create-key-12345'];
    $body = ['name' => 'retried', 'abilities' => ['auth:me']];

    $first = $this->withToken($session->plainTextToken)->withHeaders($headers)
        ->postJson('/v1/auth/tokens', $body)
        ->assertCreated();

    $this->withToken($session->plainTextToken)->withHeaders($headers)
        ->postJson('/v1/auth/tokens', $body)
        ->assertIdempotentReplay($first);

    expect($user->tokens()->where('name', 'retried')->count())->toBe(1);
});

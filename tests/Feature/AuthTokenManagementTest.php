<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Auth\ListTokensController;
use App\Models\User;
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

it('validates token id route parameter when revoking a single token', function (): void {
    $user = User::factory()->create();
    $requestToken = $user->createToken('request-token', ['auth:tokens:delete']);

    $this->withToken($requestToken->plainTextToken)
        ->deleteJson('/v1/auth/tokens/not-an-integer')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token_id']);
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

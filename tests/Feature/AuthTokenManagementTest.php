<?php

declare(strict_types=1);

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
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('meta.count', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.type', 'personal-access-tokens');

    /** @var array<int, array<string, mixed>> $data */
    $data = $response->json('data');
    $isCurrentValues = array_map(
        static fn (array $item): bool => (bool) data_get($item, 'attributes.is_current', false),
        $data,
    );

    expect($isCurrentValues)->toContain(true);
});

it('forbids listing tokens when token lacks required ability', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('limited-token', ['auth:me'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/v1/auth/tokens')
        ->assertForbidden()
        ->assertJsonPath('message', __('api.errors.forbidden'));
});

it('revokes a specific token owned by the authenticated user', function (): void {
    $user = User::factory()->create();

    $requestToken = $user->createToken('request-token', ['auth:tokens:delete']);
    $targetToken = $user->createToken('target-token', ['auth:tokens:read']);

    $this->withToken($requestToken->plainTextToken)
        ->deleteJson('/v1/auth/tokens/'.$targetToken->accessToken->id)
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
        ->deleteJson('/v1/auth/tokens/'.$otherToken->accessToken->id)
        ->assertNotFound()
        ->assertJsonPath('message', __('api.auth.token_not_found'));
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
        ->deleteJson('/v1/auth/tokens/'.$targetToken->accessToken->id)
        ->assertForbidden()
        ->assertJsonPath('message', __('api.errors.forbidden'));
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
        ->assertForbidden()
        ->assertJsonPath('message', __('api.errors.forbidden'));
});

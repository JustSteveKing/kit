<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('registers a user and returns a bearer token', function (): void {
    Notification::fake();

    $response = $this->postJson('/v1/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'device_name' => 'test-suite',
    ]);

    $response
        ->assertCreated()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonStructure([
            'meta' => ['token', 'token_type', 'expires_at'],
            'data' => ['id', 'type', 'attributes' => ['name', 'email']],
        ])
        ->assertJsonPath('meta.token_type', 'Bearer')
        ->assertJsonPath('data.type', 'users')
        ->assertJsonPath('data.attributes.email', 'jane@example.com');

    $user = User::query()->where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull();
    expect(PersonalAccessToken::query()->count())->toBe(1);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('logs in and returns a bearer token', function (): void {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/v1/auth/login', [
        'email' => 'john@example.com',
        'password' => 'password123',
        'device_name' => 'ios-app',
    ]);

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('meta.token_type', 'Bearer')
        ->assertJsonPath('data.type', 'users')
        ->assertJsonPath('data.attributes.email', 'john@example.com');

    expect(PersonalAccessToken::query()->count())->toBe(1);
});

it('issues scoped tokens without wildcard ability', function (): void {
    $response = $this->postJson('/v1/auth/register', [
        'name' => 'Scoped User',
        'email' => 'scoped@example.com',
        'password' => 'password123',
        'device_name' => 'ios-app',
    ])->assertCreated();

    $plainToken = (string) $response->json('meta.token');
    $tokenId = explode('|', $plainToken, 2)[0] ?? null;

    expect($tokenId)->not->toBeNull();

    $token = PersonalAccessToken::query()->findOrFail((int) $tokenId);

    expect($token->abilities)->toContain('auth:me');
    expect($token->abilities)->toContain('auth:logout');
    expect($token->abilities)->toContain('auth:verification:send');
    expect($token->abilities)->not->toContain('*');
});

it('rejects invalid credentials', function (): void {
    User::factory()->create([
        'email' => 'john@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/v1/auth/login', [
        'email' => 'john@example.com',
        'password' => 'wrong-password',
        'device_name' => 'ios-app',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('returns the authenticated user for me endpoint', function (): void {
    $user = User::factory()->create([
        'email' => 'me@example.com',
    ]);

    $token = $user->createToken('test-suite')->plainTextToken;

    $this->withToken($token)
        ->getJson('/v1/auth/me')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.api+json')
        ->assertJsonPath('data.type', 'users')
        ->assertJsonPath('data.attributes.email', 'me@example.com');
});

it('revokes current token on logout', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test-suite');

    $this->withToken($token->plainTextToken)
        ->postJson('/v1/auth/logout')
        ->assertNoContent();

    expect(PersonalAccessToken::query()->whereKey($token->accessToken->id)->exists())->toBeFalse();
});

it('requires sanctum auth for protected routes', function (): void {
    $this->getJson('/v1/auth/me')->assertUnauthorized();
});

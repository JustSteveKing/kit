<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('rejects non json payloads on write endpoints', function (): void {
    User::factory()->create([
        'email' => 'non-json@example.com',
        'password' => 'password123',
    ]);

    $this->post('/v1/auth/login', [
        'email' => 'non-json@example.com',
        'password' => 'password123',
        'device_name' => 'ios-app',
    ])
        ->assertStatus(415)
        ->assertJsonPath('message', __('api.errors.unsupported_media_type'));
});

it('localizes unsupported media type responses', function (): void {
    User::factory()->create([
        'email' => 'non-json-es@example.com',
        'password' => 'password123',
    ]);

    $this->withHeaders([
        'Accept-Language' => 'es',
    ])->post('/v1/auth/login', [
        'email' => 'non-json-es@example.com',
        'password' => 'password123',
        'device_name' => 'ios-app',
    ])
        ->assertStatus(415)
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('message', __('api.errors.unsupported_media_type', [], 'es'));
});

it('issues expiring sanctum tokens by default', function (): void {
    $response = $this->postJson('/v1/auth/register', [
        'name' => 'Expiring Token User',
        'email' => 'expiring@example.com',
        'password' => 'password123',
        'device_name' => 'ios-app',
    ])->assertCreated();

    $plainToken = (string) $response->json('meta.token');
    $tokenId = explode('|', $plainToken, 2)[0] ?? null;

    expect($response->json('meta.expires_at'))->not->toBeNull();
    expect($tokenId)->not->toBeNull();

    $token = PersonalAccessToken::query()->findOrFail((int) $tokenId);

    expect($token->expires_at)->not->toBeNull();
});

it('throttles repeated access on protected endpoints', function (): void {
    $user = User::factory()->create([
        'email' => 'protected-throttle@example.com',
    ]);

    $token = $user->createToken('protected-throttle')->plainTextToken;

    for ($i = 0; $i < 60; $i++) {
        $this->withToken($token)
            ->getJson('/v1/auth/me')
            ->assertOk();
    }

    $this->withToken($token)
        ->getJson('/v1/auth/me')
        ->assertTooManyRequests();
});

it('adds baseline security headers to api responses', function (): void {
    $this->getJson('/v1/auth/me')
        ->assertUnauthorized()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'no-referrer');
});

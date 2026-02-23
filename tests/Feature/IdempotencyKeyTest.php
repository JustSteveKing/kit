<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('replays register responses when idempotency key is reused with same payload', function (): void {
    $headers = ['Idempotency-Key' => 'register-key-12345'];
    $payload = [
        'name' => 'Idempotent User',
        'email' => 'idempotent@example.com',
        'password' => 'password123',
        'device_name' => 'ios-app',
    ];

    $first = $this->withHeaders($headers)
        ->postJson('/v1/auth/register', $payload)
        ->assertCreated()
        ->assertHeader('Idempotency-Key', 'register-key-12345');

    $second = $this->withHeaders($headers)
        ->postJson('/v1/auth/register', $payload)
        ->assertCreated()
        ->assertHeader('Idempotency-Key', 'register-key-12345')
        ->assertHeader('Idempotency-Replayed', 'true');

    expect(User::query()->where('email', 'idempotent@example.com')->count())->toBe(1);
    expect(PersonalAccessToken::query()->count())->toBe(1);
    expect($second->json('meta.token'))->toBe($first->json('meta.token'));
});

it('returns conflict when idempotency key is reused with a different payload', function (): void {
    $headers = ['Idempotency-Key' => 'register-key-conflict'];

    $this->withHeaders($headers)
        ->postJson('/v1/auth/register', [
            'name' => 'First User',
            'email' => 'first-idempotency@example.com',
            'password' => 'password123',
            'device_name' => 'ios-app',
        ])
        ->assertCreated();

    $this->withHeaders($headers)
        ->postJson('/v1/auth/register', [
            'name' => 'Second User',
            'email' => 'second-idempotency@example.com',
            'password' => 'password123',
            'device_name' => 'ios-app',
        ])
        ->assertStatus(409)
        ->assertJsonPath('message', __('api.errors.idempotency_key_conflict'));

    expect(User::query()->where('email', 'first-idempotency@example.com')->exists())->toBeTrue();
    expect(User::query()->where('email', 'second-idempotency@example.com')->exists())->toBeFalse();
});

it('replays forgot-password requests when idempotency key is reused', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'forgot-idempotency@example.com',
    ]);

    $headers = ['Idempotency-Key' => 'forgot-key-12345'];
    $payload = ['email' => $user->email];

    $this->withHeaders($headers)
        ->postJson('/v1/auth/password/forgot', $payload)
        ->assertOk();

    $this->withHeaders($headers)
        ->postJson('/v1/auth/password/forgot', $payload)
        ->assertOk()
        ->assertHeader('Idempotency-Replayed', 'true');

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

it('validates idempotency key format', function (): void {
    $this->withHeaders([
        'Idempotency-Key' => 'bad***key',
    ])->postJson('/v1/auth/password/forgot', [
        'email' => 'nobody@example.com',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('message', __('api.errors.idempotency_key_invalid'));
});

it('localizes idempotency conflict errors', function (): void {
    $headers = ['Idempotency-Key' => 'register-key-es'];

    $this->withHeaders($headers)
        ->postJson('/v1/auth/register', [
            'name' => 'Usuario Uno',
            'email' => 'idempotency-es-1@example.com',
            'password' => 'password123',
            'device_name' => 'ios-app',
        ])
        ->assertCreated();

    $this->withHeaders([
        'Idempotency-Key' => 'register-key-es',
        'Accept-Language' => 'es',
    ])->postJson('/v1/auth/register', [
        'name' => 'Usuario Dos',
        'email' => 'idempotency-es-2@example.com',
        'password' => 'password123',
        'device_name' => 'ios-app',
    ])
        ->assertStatus(409)
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('message', __('api.errors.idempotency_key_conflict', [], 'es'));
});

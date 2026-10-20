<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('replays register responses when idempotency key is reused with same payload', function (): void {
    $headers = ['Idempotency-Key' => '"register-key-12345"'];
    $payload = [
        'name' => 'Idempotent User',
        'email' => 'idempotent@example.com',
        'password' => 'password123',
        'device_name' => 'ios-app',
    ];

    $first = $this->withHeaders($headers)
        ->postJson('/v1/auth/register', $payload)
        ->assertCreated();

    $this->withHeaders($headers)
        ->postJson('/v1/auth/register', $payload)
        ->assertIdempotentReplay($first);

    expect(User::query()->where('email', 'idempotent@example.com')->count())->toBe(1);
    expect(PersonalAccessToken::query()->count())->toBe(1);
});

it('accepts the bare key form that common clients send', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'bare-key@example.com',
    ]);

    $first = $this->withHeaders(['Idempotency-Key' => 'bare-key-12345'])
        ->postJson('/v1/auth/password/forgot', ['email' => $user->email])
        ->assertOk();

    $this->withHeaders(['Idempotency-Key' => '"bare-key-12345"'])
        ->postJson('/v1/auth/password/forgot', ['email' => $user->email])
        ->assertIdempotentReplay($first);

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

it('rejects a reused key with a different payload', function (): void {
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
        ->assertProblemType('https://apiguide.dev/errors/idempotency-key-conflict', 422);

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

    $first = $this->withHeaders($headers)
        ->postJson('/v1/auth/password/forgot', $payload)
        ->assertOk();

    $this->withHeaders($headers)
        ->postJson('/v1/auth/password/forgot', $payload)
        ->assertIdempotentReplay($first);

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

it('rejects malformed idempotency keys', function (): void {
    $this->withHeaders([
        'Idempotency-Key' => 'bad key',
    ])->postJson('/v1/auth/password/forgot', [
        'email' => 'nobody@example.com',
    ])
        ->assertProblem(400);
});

it('localizes idempotency conflict problems', function (): void {
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
        ->assertProblemType('https://apiguide.dev/errors/idempotency-key-conflict', 422)
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('title', 'Idempotency Key Conflict')
        ->assertJsonPath('detail', __('api-toolbox::problems.idempotency.conflict', [], 'es'));
});

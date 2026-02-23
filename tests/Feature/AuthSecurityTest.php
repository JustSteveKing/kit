<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('rejects invalid registration payloads', function (): void {
    $this->postJson('/v1/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('rejects duplicate registration emails', function (): void {
    User::factory()->create([
        'email' => 'existing@example.com',
    ]);

    $this->postJson('/v1/auth/register', [
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'password' => 'password123',
        'device_name' => 'ios-app',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('requires device_name for login', function (): void {
    User::factory()->create([
        'email' => 'device-check@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/v1/auth/login', [
        'email' => 'device-check@example.com',
        'password' => 'password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['device_name']);
});

it('does not reveal if a password reset email exists', function (): void {
    Notification::fake();

    $this->postJson('/v1/auth/password/forgot', [
        'email' => 'unknown@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('message', __('api.auth.password_reset_link_sent'));

    Notification::assertNothingSent();
});

it('rejects password reset when confirmation does not match', function (): void {
    $user = User::factory()->create([
        'email' => 'mismatch@example.com',
    ]);

    $this->postJson('/v1/auth/password/reset', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-password123',
        'password_confirmation' => 'different-password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('requires auth to resend verification notifications', function (): void {
    $this->postJson('/v1/auth/email/verification-notification')
        ->assertUnauthorized();
});

it('returns already verified message when verification email is not needed', function (): void {
    $user = User::factory()->create([
        'email' => 'already-verified@example.com',
        'email_verified_at' => now(),
    ]);

    $token = $user->createToken('verified-device')->plainTextToken;

    $this->withToken($token)
        ->postJson('/v1/auth/email/verification-notification')
        ->assertOk()
        ->assertJsonPath('message', __('api.auth.email_already_verified'));
});

it('returns forbidden for signed links with mismatched email hash', function (): void {
    $user = User::factory()->unverified()->create([
        'email' => 'signed-check@example.com',
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(30),
        [
            'id' => $user->getKey(),
            'hash' => sha1('different@example.com'),
        ],
    );

    $this->getJson($url)->assertForbidden();
});

it('returns forbidden for expired signed verification links', function (): void {
    $user = User::factory()->unverified()->create([
        'email' => 'expired-link@example.com',
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ],
    );

    $this->getJson($url)->assertForbidden();
});

it('validates email query param in reset token payload route', function (): void {
    $this->getJson('/v1/auth/password/reset/token-value?email=not-an-email')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('revokes only the current token on logout', function (): void {
    $user = User::factory()->create();
    $firstToken = $user->createToken('device-a');
    $secondToken = $user->createToken('device-b');

    $this->withToken($firstToken->plainTextToken)
        ->postJson('/v1/auth/logout')
        ->assertNoContent();

    expect(PersonalAccessToken::query()->whereKey($firstToken->accessToken->id)->exists())->toBeFalse();
    expect(PersonalAccessToken::query()->whereKey($secondToken->accessToken->id)->exists())->toBeTrue();
});

it('requires auth for logout', function (): void {
    $this->postJson('/v1/auth/logout')->assertUnauthorized();
});

it('forbids me endpoint when token lacks required ability', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('limited-me', ['auth:logout'])->plainTextToken;

    $this->withToken($token)
        ->getJson('/v1/auth/me')
        ->assertForbidden()
        ->assertJsonPath('message', __('api.errors.forbidden'));
});

it('forbids logout when token lacks required ability', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('limited-logout', ['auth:me'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/v1/auth/logout')
        ->assertForbidden()
        ->assertJsonPath('message', __('api.errors.forbidden'));
});

it('forbids resend verification when token lacks required ability', function (): void {
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('limited-verification', ['auth:me'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/v1/auth/email/verification-notification')
        ->assertForbidden()
        ->assertJsonPath('message', __('api.errors.forbidden'));
});

it('throttles repeated failed login attempts', function (): void {
    User::factory()->create([
        'email' => 'throttle-login@example.com',
        'password' => 'password123',
    ]);

    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/v1/auth/login', [
            'email' => 'throttle-login@example.com',
            'password' => 'wrong-password',
            'device_name' => 'contract-test',
        ])->assertUnprocessable();
    }

    $this->postJson('/v1/auth/login', [
        'email' => 'throttle-login@example.com',
        'password' => 'wrong-password',
        'device_name' => 'contract-test',
    ])->assertTooManyRequests();
});

it('throttles repeated forgot-password requests', function (): void {
    Notification::fake();

    User::factory()->create([
        'email' => 'throttle-forgot@example.com',
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/v1/auth/password/forgot', [
            'email' => 'throttle-forgot@example.com',
        ])->assertOk();
    }

    $this->postJson('/v1/auth/password/forgot', [
        'email' => 'throttle-forgot@example.com',
    ])->assertTooManyRequests();

    Notification::assertSentToTimes(User::query()->firstWhere('email', 'throttle-forgot@example.com'), ResetPassword::class, 1);
});

it('throttles repeated registration attempts from the same ip', function (): void {
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/v1/auth/register', [
            'name' => 'Rate Limited',
            'email' => sprintf('rate-%d@example.com', $i),
            'password' => 'password123',
            'device_name' => 'rate-test',
        ])->assertCreated();
    }

    $this->postJson('/v1/auth/register', [
        'name' => 'Rate Limited',
        'email' => 'rate-over-limit@example.com',
        'password' => 'password123',
        'device_name' => 'rate-test',
    ])->assertTooManyRequests();
});

<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

it('sends an email verification notification for authenticated users', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $token = $user->createToken('test-suite')->plainTextToken;

    $this->withToken($token)
        ->postJson('/v1/auth/email/verification-notification')
        ->assertOk()
        ->assertJsonPath('message', __('api.auth.verification_link_sent'));

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('marks an email as verified through signed verify url', function (): void {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ],
    );

    $this->getJson($verificationUrl)
        ->assertOk()
        ->assertJsonPath('message', __('api.auth.email_verified'));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('returns reset token payload for API clients', function (): void {
    $this->getJson('/v1/auth/password/reset/test-token?email=jane@example.com')
        ->assertOk()
        ->assertJson([
            'token' => 'test-token',
            'email' => 'jane@example.com',
        ]);
});

it('sends a password reset link for existing users', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'recover@example.com',
    ]);

    $this->postJson('/v1/auth/password/forgot', [
        'email' => $user->email,
    ])
        ->assertOk()
        ->assertJsonPath('message', __('api.auth.password_reset_link_sent'));

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets password with a valid reset token', function (): void {
    $user = User::factory()->create([
        'email' => 'recover@example.com',
        'password' => 'password123',
    ]);

    $token = Password::broker()->createToken($user);

    $this->postJson('/v1/auth/password/reset', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])
        ->assertOk()
        ->assertJsonPath('message', __('api.auth.password_reset_success'));

    expect(Hash::check('new-password123', $user->fresh()->password))->toBeTrue();
});

it('rejects password reset when the token is invalid', function (): void {
    $user = User::factory()->create([
        'email' => 'recover@example.com',
    ]);

    $this->postJson('/v1/auth/password/reset', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-password123',
        'password_confirmation' => 'new-password123',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

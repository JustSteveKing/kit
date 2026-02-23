<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

it('logs successful login events with structured context', function (): void {
    Log::spy();

    $user = User::factory()->create([
        'email' => 'audit-success@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/v1/auth/login', [
        'email' => 'audit-success@example.com',
        'password' => 'password123',
        'device_name' => 'audit-device',
    ])->assertOk();

    Log::shouldHaveReceived('info')
        ->with('security.audit', \Mockery::on(function (array $context) use ($user): bool {
            return ($context['event'] ?? null) === 'auth.login.succeeded'
                && ($context['user_id'] ?? null) === (string) $user->getKey()
                && ($context['email_hash'] ?? null) === SecurityAudit::hashEmail('audit-success@example.com')
                && ($context['device_name'] ?? null) === 'audit-device'
                && ! empty($context['request_id']);
        }))
        ->atLeast()
        ->once();
});

it('logs failed login events without leaking credentials', function (): void {
    Log::spy();

    User::factory()->create([
        'email' => 'audit-failed@example.com',
        'password' => 'password123',
    ]);

    $this->postJson('/v1/auth/login', [
        'email' => 'audit-failed@example.com',
        'password' => 'wrong-password',
        'device_name' => 'audit-device',
    ])->assertUnprocessable();

    Log::shouldHaveReceived('info')
        ->with('security.audit', \Mockery::on(function (array $context): bool {
            return ($context['event'] ?? null) === 'auth.login.failed'
                && ($context['email_hash'] ?? null) === SecurityAudit::hashEmail('audit-failed@example.com')
                && ($context['device_name'] ?? null) === 'audit-device'
                && ! isset($context['password'])
                && ! empty($context['request_id']);
        }))
        ->atLeast()
        ->once();
});

it('logs token revocation events on logout', function (): void {
    Log::spy();

    $user = User::factory()->create();
    $token = $user->createToken('audit-logout');

    $this->withToken($token->plainTextToken)
        ->postJson('/v1/auth/logout')
        ->assertNoContent();

    Log::shouldHaveReceived('info')
        ->with('security.audit', \Mockery::on(function (array $context) use ($user, $token): bool {
            return ($context['event'] ?? null) === 'auth.logout.succeeded'
                && ($context['user_id'] ?? null) === (string) $user->getKey()
                && ($context['token_id'] ?? null) === (string) $token->accessToken->getKey()
                && ! empty($context['request_id']);
        }))
        ->atLeast()
        ->once();
});

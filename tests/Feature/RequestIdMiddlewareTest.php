<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('adds a request id header when one is not provided', function (): void {
    $response = $this->getJson('/v1/auth/me')
        ->assertUnauthorized();

    $requestId = $response->headers->get('X-Request-Id');

    expect($requestId)->not->toBeNull();
    expect((string) $requestId)->toMatch('/^[A-Za-z0-9._-]{8,128}$/');
});

it('uses the incoming request id header when valid', function (): void {
    $provided = 'req-abc12345-XYZ';

    $this->withHeaders([
        'X-Request-Id' => $provided,
    ])->getJson('/v1/auth/me')
        ->assertUnauthorized()
        ->assertHeader('X-Request-Id', $provided);
});

it('replaces invalid incoming request id values', function (): void {
    $provided = '***invalid***';

    $response = $this->withHeaders([
        'X-Request-Id' => $provided,
    ])->getJson('/v1/auth/me')
        ->assertUnauthorized();

    $actual = $response->headers->get('X-Request-Id');

    expect($actual)->not->toBeNull();
    expect((string) $actual)->not->toBe($provided);
    expect((string) $actual)->toMatch('/^[A-Za-z0-9._-]{8,128}$/');
});

it('includes request id header on successful authenticated responses', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('request-id-success')->plainTextToken;

    $response = $this->withToken($token)
        ->getJson('/v1/auth/me')
        ->assertOk();

    expect($response->headers->get('X-Request-Id'))->not->toBeNull();
});

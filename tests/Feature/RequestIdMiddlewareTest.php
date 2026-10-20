<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('assigns a uuidv7 request id when one is not provided', function (): void {
    $response = $this->getJson('/v1/auth/me')
        ->assertUnauthorized()
        ->assertRequestId();

    $requestId = (string) $response->headers->get('X-Request-Id');

    expect(Str::isUuid($requestId))->toBeTrue();
    expect($requestId[14])->toBe('7');
});

it('uses the incoming request id header when valid', function (): void {
    $provided = 'req-abc12345-XYZ';

    $this->withHeaders([
        'X-Request-Id' => $provided,
    ])->getJson('/v1/auth/me')
        ->assertUnauthorized()
        ->assertRequestId($provided);
});

it('replaces incoming request ids that are too long', function (): void {
    $provided = str_repeat('a', 201);

    $response = $this->withHeaders([
        'X-Request-Id' => $provided,
    ])->getJson('/v1/auth/me')
        ->assertUnauthorized()
        ->assertRequestId();

    expect(Str::isUuid((string) $response->headers->get('X-Request-Id')))->toBeTrue();
});

it('identifies problems by request id', function (): void {
    $response = $this->getJson('/v1/auth/me')
        ->assertProblem(401);

    expect($response->json('instance'))->toBe('urn:uuid:' . $response->headers->get('X-Request-Id'));
});

it('includes request id header on successful authenticated responses', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('request-id-success')->plainTextToken;

    $this->withToken($token)
        ->getJson('/v1/auth/me')
        ->assertOk()
        ->assertRequestId();
});

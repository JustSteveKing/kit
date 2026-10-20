<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('cors.allowed_origins', ['https://app.example.com']);
});

it('lets a browser send conditional request headers', function (): void {
    $allowed = (string) $this->call('OPTIONS', '/v1/auth/me', [], [], [], [
        'HTTP_ORIGIN' => 'https://app.example.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PATCH',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'If-Match, If-None-Match',
    ])
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'https://app.example.com')
        ->headers->get('Access-Control-Allow-Headers');

    expect(array_map(mb_strtolower(...), array_map(mb_trim(...), explode(',', $allowed))))->toContain('if-match', 'if-none-match');
});

it('lets a browser read the ETag it has to send back', function (): void {
    $token = User::factory()->create()->createToken('test', ['auth:me'])->plainTextToken;

    $exposed = (string) $this->withToken($token)
        ->getJson('/v1/auth/me', ['Origin' => 'https://app.example.com'])
        ->assertOk()
        ->assertETag()
        ->headers->get('Access-Control-Expose-Headers');

    expect(array_map(mb_strtolower(...), array_map(mb_trim(...), explode(',', $exposed))))->toContain('etag', 'last-modified');
});

<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

uses(RefreshDatabase::class);

it('rejects non json payloads on write endpoints', function (): void {
    User::factory()->create([
        'email' => 'non-json@example.com',
        'password' => 'password123',
    ]);

    postForm($this, '/v1/auth/login', [
        'email' => 'non-json@example.com',
        'password' => 'password123',
        'device_name' => 'ios-app',
    ])
        ->assertProblemType('https://apiguide.dev/errors/unsupported-media-type', 415)
        ->assertHeader('Accept', 'application/json');
});

it('localizes unsupported media type problems', function (): void {
    User::factory()->create([
        'email' => 'non-json-es@example.com',
        'password' => 'password123',
    ]);

    postForm($this, '/v1/auth/login', [
        'email' => 'non-json-es@example.com',
        'password' => 'password123',
        'device_name' => 'ios-app',
    ], ['HTTP_ACCEPT_LANGUAGE' => 'es'])
        ->assertProblemType('https://apiguide.dev/errors/unsupported-media-type', 415)
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('detail', __('api-toolbox::problems.negotiation.unsupported_media_type', [
            'type' => 'application/x-www-form-urlencoded',
            'types' => 'application/json',
        ], 'es'));
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

it('renders problems for api requests that do not ask for json', function (): void {
    $this->get('/v1/auth/me')
        ->assertProblemType('https://apiguide.dev/errors/unauthorized', 401)
        ->assertHeader('WWW-Authenticate', 'Bearer');
});

/**
 * A form-encoded POST with a real body, as a browser or curl -d sends it. Laravel's post() helper
 * passes the fields as parsed parameters and leaves the body empty.
 *
 * @param  array<string, string>  $fields
 * @param  array<string, string>  $server
 */
function postForm(TestCase $test, string $uri, array $fields, array $server = []): TestResponse
{
    return $test->call('POST', $uri, [], [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ...$server,
    ], http_build_query($fields));
}

<?php

declare(strict_types=1);

use App\Models\User;
use App\Http\Middleware\SetRequestLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('translates success responses from accept language header', function (): void {
    $this->withHeaders([
        'Accept-Language' => 'es-ES,es;q=0.9',
    ])->postJson('/v1/auth/password/forgot', [
        'email' => 'unknown@example.com',
    ])
        ->assertOk()
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('message', __('api.auth.password_reset_link_sent', [], 'es'));
});

it('translates authentication errors from accept language header', function (): void {
    $this->withHeaders([
        'Accept-Language' => 'es-MX',
    ])->getJson('/v1/auth/me')
        ->assertUnauthorized()
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('message', __('api.errors.unauthenticated', [], 'es'));
});

it('translates validation failures from accept language header', function (): void {
    User::factory()->create([
        'email' => 'locale-login@example.com',
        'password' => 'password123',
    ]);

    $this->withHeaders([
        'Accept-Language' => 'es',
    ])->postJson('/v1/auth/login', [
        'email' => 'locale-login@example.com',
        'password' => 'wrong-password',
        'device_name' => 'locale-test',
    ])
        ->assertUnprocessable()
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('message', __('api.errors.validation_failed', [], 'es'))
        ->assertJsonPath('errors.email.0', __('api.auth.invalid_credentials', [], 'es'));
});

it('falls back to default locale for unsupported language values', function (): void {
    $this->withHeaders([
        'Accept-Language' => 'fr-FR,fr;q=0.9',
    ])->postJson('/v1/auth/password/forgot', [
        'email' => 'unknown@example.com',
    ])
        ->assertOk()
        ->assertHeader('Content-Language', 'en')
        ->assertJsonPath('message', __('api.auth.password_reset_link_sent', [], 'en'));
});

it('translates invalid signed url failures from accept language header', function (): void {
    $user = User::factory()->unverified()->create([
        'email' => 'locale-signed@example.com',
    ]);

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinute(),
        [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ],
    );

    $this->withHeaders([
        'Accept-Language' => 'es',
    ])->getJson($url)
        ->assertForbidden()
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('message', __('api.auth.invalid_verification_link', [], 'es'));
});

it('translates sunset enforcement responses from accept language header', function (): void {
    $path = '/v1/test/sunset-localized-'.Str::lower((string) Str::ulid());

    Route::middleware([SetRequestLocale::class, 'sunset:2000-01-01,,true'])
        ->get($path, fn () => new JsonResponse(['ok' => true]));

    $this->withHeaders([
        'Accept-Language' => 'es',
    ])->getJson($path)
        ->assertGone()
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('message', __('api.sunset.endpoint_unavailable', [], 'es'));
});

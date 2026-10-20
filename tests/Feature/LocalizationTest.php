<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;

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
        ->assertProblemType('https://apiguide.dev/errors/unauthorized', 401)
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('detail', __('api.errors.unauthenticated', [], 'es'));
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
        ->assertProblemType('https://apiguide.dev/errors/validation-failed', 422)
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('detail', __('api.auth.invalid_credentials', [], 'es'))
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
        ->assertProblem(403)
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('detail', __('api.auth.invalid_verification_link', [], 'es'));
});

it('translates sunset enforcement responses from accept language header', function (): void {
    $path = '/v1/test/sunset-localized-' . Str::lower((string) Str::ulid());

    Route::middleware('api')->get($path, LocalizedRetiredEndpoint::class);

    $this->withHeaders([
        'Accept-Language' => 'es',
    ])->getJson($path)
        ->assertProblem(410)
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('detail', __('api.sunset.endpoint_unavailable', [], 'es'));
});

it('has a spanish line for every english api message', function (): void {
    $english = require lang_path('en/api.php');
    $spanish = require lang_path('es/api.php');

    expect(array_keys(Arr::dot($spanish)))->toEqualCanonicalizing(array_keys(Arr::dot($english)));
});

it('translates every problem detail api-toolbox can raise', function (): void {
    $english = require base_path('vendor/juststeveking/api-toolbox/resources/lang/en/problems.php');
    $spanish = require lang_path('vendor/api-toolbox/es/problems.php');

    expect(array_keys(Arr::dot($spanish)))->toEqualCanonicalizing(array_keys(Arr::dot($english)));
});

#[Sunset(at: '2000-01-01T00:00:00Z')]
final class LocalizedRetiredEndpoint
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}

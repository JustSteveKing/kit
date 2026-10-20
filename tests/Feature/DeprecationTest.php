<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;

it('adds deprecation and sunset headers for endpoints that are still live', function (): void {
    $path = '/v1/test/deprecated-' . Str::lower((string) Str::ulid());

    Route::middleware('api')->get($path, DeprecatedEndpoint::class);

    $this->getJson($path)
        ->assertOk()
        ->assertDeprecated('2026-01-01T00:00:00Z', 'https://docs.example.com/deprecations/test')
        ->assertSunset('2099-01-01T00:00:00Z', 'https://docs.example.com/sunset-policy');
});

it('returns 410 gone once the sunset date has passed', function (): void {
    $path = '/v1/test/retired-' . Str::lower((string) Str::ulid());

    Route::middleware('api')->get($path, RetiredEndpoint::class);

    $this->getJson($path)
        ->assertProblemType('https://apiguide.dev/errors/gone', 410)
        ->assertJsonPath('detail', __('api.sunset.endpoint_unavailable'))
        ->assertDeprecated('2000-01-01T00:00:00Z')
        ->assertSunset('2001-01-01T00:00:00Z');
});

it('serves deprecated endpoints that have no sunset date', function (): void {
    $path = '/v1/test/deprecated-only-' . Str::lower((string) Str::ulid());

    Route::middleware('api')->get($path, DeprecatedWithoutSunsetEndpoint::class);

    $response = $this->getJson($path)
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertDeprecated('2000-01-01T00:00:00Z');

    expect($response->headers->get('Sunset'))->toBeNull();
});

it('leaves endpoints without attributes alone', function (): void {
    $this->getJson('/v1/auth/me')
        ->assertUnauthorized()
        ->assertNotDeprecated();
});

#[Deprecation(at: '2026-01-01T00:00:00Z', link: 'https://docs.example.com/deprecations/test')]
#[Sunset(at: '2099-01-01T00:00:00Z', link: 'https://docs.example.com/sunset-policy')]
final class DeprecatedEndpoint
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}

#[Deprecation(at: '2000-01-01T00:00:00Z')]
#[Sunset(at: '2001-01-01T00:00:00Z')]
final class RetiredEndpoint
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}

#[Deprecation(at: '2000-01-01T00:00:00Z')]
final class DeprecatedWithoutSunsetEndpoint
{
    public function __invoke(): JsonResponse
    {
        return new JsonResponse(['ok' => true]);
    }
}

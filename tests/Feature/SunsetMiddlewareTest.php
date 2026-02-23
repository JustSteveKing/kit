<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

it('adds deprecation and sunset headers for active endpoints', function (): void {
    $path = '/v1/test/sunset-active-'.Str::lower((string) Str::ulid());
    $successor = 'https://api.example.com/v2/replacement';

    Route::middleware(sprintf('sunset:2030-01-01,%s', $successor))
        ->get($path, fn () => new JsonResponse(['ok' => true]));

    $response = $this->getJson($path)
        ->assertOk();

    expect($response->headers->get('Deprecation'))->toStartWith('@');
    expect($response->headers->get('Sunset'))->not->toBeNull();
    expect((string) $response->headers->get('Link'))->toContain($successor);
    expect((string) $response->headers->get('Link'))->toContain('successor-version');
});

it('returns 410 after sunset when enforcement is enabled', function (): void {
    $path = '/v1/test/sunset-expired-'.Str::lower((string) Str::ulid());
    $successor = 'https://api.example.com/v2/replacement';

    Route::middleware(sprintf('sunset:2000-01-01,%s,true', $successor))
        ->get($path, fn () => new JsonResponse(['ok' => true]));

    $response = $this->getJson($path)
        ->assertGone()
        ->assertJsonPath('message', __('api.sunset.endpoint_unavailable'));

    expect($response->headers->get('Deprecation'))->toStartWith('@');
    expect($response->headers->get('Sunset'))->not->toBeNull();
    expect((string) $response->headers->get('Link'))->toContain($successor);
});

it('does not set a link header for invalid successor urls', function (): void {
    $path = '/v1/test/sunset-invalid-link-'.Str::lower((string) Str::ulid());

    Route::middleware('sunset:2030-01-01,not-a-url')
        ->get($path, fn () => new JsonResponse(['ok' => true]));

    $response = $this->getJson($path)->assertOk();

    expect($response->headers->get('Link'))->toBeNull();
});

it('does not block requests after sunset when enforcement is disabled', function (): void {
    $path = '/v1/test/sunset-soft-expired-'.Str::lower((string) Str::ulid());

    Route::middleware('sunset:2000-01-01')
        ->get($path, fn () => new JsonResponse(['ok' => true]));

    $response = $this->getJson($path)
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect($response->headers->get('Deprecation'))->toStartWith('@');
    expect($response->headers->get('Sunset'))->not->toBeNull();
});

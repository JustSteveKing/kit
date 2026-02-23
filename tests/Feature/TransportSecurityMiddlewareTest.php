<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejects insecure requests when https enforcement is enabled', function (): void {
    config()->set('security.force_https', true);

    $this->getJson('/v1/auth/me')
        ->assertStatus(400)
        ->assertJsonPath('message', __('api.errors.https_required'));
});

it('localizes https enforcement errors', function (): void {
    config()->set('security.force_https', true);

    $this->withHeaders([
        'Accept-Language' => 'es',
    ])->getJson('/v1/auth/me')
        ->assertStatus(400)
        ->assertHeader('Content-Language', 'es')
        ->assertJsonPath('message', __('api.errors.https_required', [], 'es'));
});

it('adds hsts header for secure requests when enabled', function (): void {
    config()->set('security.force_https', false);
    config()->set('security.hsts.enabled', true);
    config()->set('security.hsts.max_age', 31536000);
    config()->set('security.hsts.include_subdomains', true);
    config()->set('security.hsts.preload', false);

    $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
    ])->getJson('/v1/auth/me')
        ->assertUnauthorized()
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

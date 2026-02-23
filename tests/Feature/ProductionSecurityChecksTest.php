<?php

declare(strict_types=1);

use App\Support\ProductionSecurityChecks;

it('skips checks outside production environment', function (): void {
    config()->set('app.debug', true);

    expect(fn (): null => ProductionSecurityChecks::assertForEnvironment('testing'))->not->toThrow(RuntimeException::class);
});

it('fails in production when debug is enabled', function (): void {
    config()->set('app.debug', true);
    config()->set('security.force_https', true);
    config()->set('app.url', 'https://api.example.com');
    config()->set('cors.allowed_origins', ['https://app.example.com']);
    config()->set('security.trusted_hosts', ['api.example.com']);

    expect(fn (): null => ProductionSecurityChecks::assertForEnvironment('production'))
        ->toThrow(RuntimeException::class, 'APP_DEBUG must be false');
});

it('fails in production when https requirements are not secure enough', function (): void {
    config()->set('app.debug', false);
    config()->set('security.force_https', false);
    config()->set('app.url', 'http://api.example.com');
    config()->set('cors.allowed_origins', ['https://app.example.com']);
    config()->set('security.trusted_hosts', ['api.example.com']);

    expect(fn (): null => ProductionSecurityChecks::assertForEnvironment('production'))
        ->toThrow(RuntimeException::class, 'SECURITY_FORCE_HTTPS must be enabled');
});

it('fails in production when cors or trusted hosts are unsafe', function (): void {
    config()->set('app.debug', false);
    config()->set('security.force_https', true);
    config()->set('app.url', 'https://api.example.com');
    config()->set('cors.allowed_origins', ['*']);
    config()->set('security.trusted_hosts', []);

    expect(fn (): null => ProductionSecurityChecks::assertForEnvironment('production'))
        ->toThrow(RuntimeException::class, 'CORS allowed origins must not use wildcard');
});

it('passes with safe production security settings', function (): void {
    config()->set('app.debug', false);
    config()->set('security.force_https', true);
    config()->set('app.url', 'https://api.example.com');
    config()->set('cors.allowed_origins', ['https://app.example.com']);
    config()->set('security.trusted_hosts', ['api.example.com']);

    expect(fn (): null => ProductionSecurityChecks::assertForEnvironment('production'))
        ->not->toThrow(RuntimeException::class);
});

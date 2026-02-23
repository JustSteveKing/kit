<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\ProductionSecurityChecks;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductionSecurityChecks::assertForEnvironment((string) app()->environment());

        RateLimiter::for('auth-register', fn (Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
        ]);

        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(10)->by(sprintf('%s|%s', $request->ip(), (string) $request->input('email'))),
        ]);

        RateLimiter::for('auth-password', fn (Request $request) => [
            Limit::perMinute(5)->by(sprintf('%s|%s', $request->ip(), (string) $request->input('email'))),
        ]);

        RateLimiter::for('auth-protected', fn (Request $request) => [
            Limit::perMinute(60)->by(
                (string) ($request->user()?->getAuthIdentifier() ?? $request->ip())
            ),
        ]);
    }
}

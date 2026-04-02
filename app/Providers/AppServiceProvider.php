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

        RateLimiter::for('auth-login', function (Request $request) {
            $email = $request->input('email');
            $emailStr = is_scalar($email) ? (string) $email : '';

            return [
                Limit::perMinute(10)->by(sprintf('%s|%s', $request->ip(), $emailStr)),
            ];
        });

        RateLimiter::for('auth-password', function (Request $request) {
            $email = $request->input('email');
            $emailStr = is_scalar($email) ? (string) $email : '';

            return [
                Limit::perMinute(5)->by(sprintf('%s|%s', $request->ip(), $emailStr)),
            ];
        });

        RateLimiter::for('auth-protected', function (Request $request) {
            $idValue = $request->user()?->getAuthIdentifier() ?? $request->ip();
            $identifier = is_scalar($idValue) ? (string) $idValue : '';

            return [
                Limit::perMinute(60)->by($identifier),
            ];
        });
    }
}

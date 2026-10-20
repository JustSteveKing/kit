<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\ProductionSecurityChecks;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Scribe;

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

        // Document path parameters as the routes name them. Scribe otherwise renames {project}
        // to {id} when it guesses at route model binding, and the spec stops matching the routes.
        // Scribe is a dev dependency, so production skips this.
        if (class_exists(Scribe::class)) {
            Scribe::normalizeEndpointUrlUsing(static fn(string $uri): string => $uri);
        }

        RateLimiter::for('auth-register', fn(Request $request) => [
            Limit::perMinute(10)->by($request->ip()),
        ]);

        RateLimiter::for('auth-login', fn(Request $request) => [
            Limit::perMinute(10)->by(sprintf('%s|%s', $request->ip(), $request->string('email')->value())),
        ]);

        RateLimiter::for('auth-password', fn(Request $request) => [
            Limit::perMinute(5)->by(sprintf('%s|%s', $request->ip(), $request->string('email')->value())),
        ]);

        RateLimiter::for('auth-protected', fn(Request $request) => [
            Limit::perMinute(60)->by(
                $request->user() instanceof User ? $request->user()->id : (string) $request->ip(),
            ),
        ]);
    }
}

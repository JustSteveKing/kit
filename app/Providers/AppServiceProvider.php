<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\ProductionSecurityChecks;
use App\Webhooks\DnsHostResolver;
use App\Webhooks\HostResolver;
use App\Webhooks\SubscriptionSigningKeys;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Http\Kernel as KernelContract;
use Illuminate\Foundation\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use JustSteveKing\Toolbox\Idempotency\Http\Middleware\EnsureIdempotency;
use JustSteveKing\Toolbox\MessageSignatures\Contracts\KeyResolver;
use Knuckles\Scribe\Scribe;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HostResolver::class, DnsHostResolver::class);

        // Each webhook subscription signs with its own secret; other key IDs fall through to
        // config/api-toolbox.php.
        $this->app->extend(KeyResolver::class, static fn(KeyResolver $configured): KeyResolver => new SubscriptionSigningKeys($configured));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductionSecurityChecks::assertForEnvironment((string) app()->environment());

        // A token without the ability is refused before anything else looks at the request: before
        // idempotency, so the refusal is not stored and replayed to a retry with a better token, and
        // before route parameters are bound, so a bound route answers 403 and not 404. Done once
        // the kernel exists, because api-toolbox places its idempotency middleware then too.
        $this->callAfterResolving(KernelContract::class, static function (KernelContract $kernel): void {
            if ($kernel instanceof Kernel) {
                // Before whichever comes first, so it holds with idempotency switched off too.
                $first = [EnsureIdempotency::class, SubstituteBindings::class];

                $kernel->addToMiddlewarePriorityBefore($first, CheckAbilities::class);
                $kernel->addToMiddlewarePriorityBefore($first, CheckForAnyAbility::class);
            }
        });

        // Document path parameters as the routes name them. Scribe otherwise renames {project}
        // to {id} when it guesses at route model binding, and the spec stops matching the routes.
        // Scribe is a dev dependency, so production skips this.
        if (class_exists(Scribe::class)) {
            Scribe::normalizeEndpointUrlUsing(static fn(string $uri): string => $uri);
        }

        RateLimiter::for('auth-register', fn(Request $request): array => [
            Limit::perMinute(10)->by($request->ip()),
        ]);

        RateLimiter::for('auth-login', fn(Request $request): array => [
            Limit::perMinute(10)->by(sprintf('%s|%s', $request->ip(), $request->string('email')->value())),
        ]);

        RateLimiter::for('auth-password', fn(Request $request): array => [
            Limit::perMinute(5)->by(sprintf('%s|%s', $request->ip(), $request->string('email')->value())),
        ]);

        RateLimiter::for('auth-protected', fn(Request $request): array => [
            Limit::perMinute(60)->by(
                $request->user() instanceof User ? $request->user()->id : (string) $request->ip(),
            ),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Problems\EndpointSunset;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use JustSteveKing\Toolbox\Deprecation\Contracts\DeprecationSource;
use JustSteveKing\Toolbox\Deprecation\Notice;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers 410 Gone once a route's #[Sunset] date has passed.
 *
 * RFC 8594 section 1 says a resource is expected to become unresponsive at its sunset. api-toolbox
 * sends the Deprecation and Sunset headers; this enforces the date those headers announce. The
 * problem is apiguide.dev's endpoint-sunset rather than plain gone: the interface was retired on
 * purpose, so the fault is in the client's code, not its data, and the body says when and where to
 * go instead, as that entry asks.
 */
final readonly class RetireSunsetEndpoints
{
    public function __construct(
        private DeprecationSource $deprecations,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();

        if ($route instanceof Route) {
            $notice = $this->deprecations->forRoute($route);
            $sunsetAt = $notice?->sunsetAt;

            // Laravel's clock rather than the system's, so a test can travel past a sunset.
            if ($notice instanceof Notice && $sunsetAt instanceof DateTimeImmutable && $sunsetAt <= now()->toDateTimeImmutable()) {
                throw new EndpointSunset(
                    detail: __('api.sunset.endpoint_unavailable'),
                    extensions: array_filter([
                        'deprecatedAt' => $notice->deprecatedAt?->format(DATE_ATOM),
                        'sunsetAt' => $sunsetAt->format(DATE_ATOM),
                        'migrationGuide' => $notice->sunsetLink ?? $notice->deprecationLink,
                    ], static fn(?string $value): bool => null !== $value),
                );
            }
        }

        return $next($request);
    }
}

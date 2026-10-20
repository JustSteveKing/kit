<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use JustSteveKing\Toolbox\Deprecation\Contracts\DeprecationSource;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers 410 Gone once a route's #[Sunset] date has passed.
 *
 * RFC 8594 section 1 says a resource is expected to become unresponsive at its sunset. api-toolbox
 * sends the Deprecation and Sunset headers; this enforces the date those headers announce.
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
            $sunsetAt = $this->deprecations->forRoute($route)?->sunsetAt;

            if ($sunsetAt instanceof DateTimeImmutable && $sunsetAt <= new DateTimeImmutable('now')) {
                abort(Response::HTTP_GONE, __('api.sunset.endpoint_unavailable'));
            }
        }

        return $next($request);
    }
}

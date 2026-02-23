<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceTransportSecurity
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ((bool) config('security.force_https', false) && ! $request->isSecure()) {
            return new JsonResponse([
                'message' => __('api.errors.https_required'),
            ], 400);
        }

        $response = $next($request);

        if ((bool) config('security.hsts.enabled', true) && $request->isSecure()) {
            $maxAge = max((int) config('security.hsts.max_age', 31536000), 0);
            $directives = ["max-age={$maxAge}"];

            if ((bool) config('security.hsts.include_subdomains', true)) {
                $directives[] = 'includeSubDomains';
            }

            if ((bool) config('security.hsts.preload', false)) {
                $directives[] = 'preload';
            }

            $response->headers->set('Strict-Transport-Security', implode('; ', $directives));
        }

        return $response;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetRequestLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = array_values(array_filter(array_map(
            static fn (mixed $locale): string => trim((string) $locale),
            (array) config('app.supported_locales', [config('app.locale')]),
        )));

        $preferredLocale = $request->getPreferredLanguage($supportedLocales);
        $resolvedLocale = is_string($preferredLocale) && $preferredLocale !== ''
            ? $preferredLocale
            : (string) config('app.fallback_locale', 'en');

        App::setLocale($resolvedLocale);

        $response = $next($request);
        $response->headers->set('Content-Language', App::currentLocale());

        return $response;
    }
}

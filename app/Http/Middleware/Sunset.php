<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class Sunset
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(
        Request $request,
        Closure $next,
        string $sunsetAt,
        ?string $successorUrl = null,
        ?string $enforceAfterSunset = null,
    ): Response {
        $sunsetDate = CarbonImmutable::parse($sunsetAt)->utc();
        $isExpired = now()->greaterThanOrEqualTo($sunsetDate);
        $shouldEnforce = filter_var($enforceAfterSunset, FILTER_VALIDATE_BOOL);

        if ($isExpired && $shouldEnforce) {
            $response = new JsonResponse([
                'message' => __('api.sunset.endpoint_unavailable'),
                'sunset_at' => $sunsetDate->toAtomString(),
            ], Response::HTTP_GONE);

            return $this->attachHeaders($response, $sunsetDate, $successorUrl);
        }

        $response = $next($request);

        return $this->attachHeaders($response, $sunsetDate, $successorUrl);
    }

    private function attachHeaders(Response $response, CarbonImmutable $sunsetDate, ?string $successorUrl): Response
    {
        // RFC 9745 syntax for Deprecation supports epoch prefixed with @.
        $response->headers->set('Deprecation', '@'.$sunsetDate->timestamp);
        $response->headers->set('Sunset', $sunsetDate->format('D, d M Y H:i:s').' GMT');

        if ($successorUrl && filter_var($successorUrl, FILTER_VALIDATE_URL)) {
            $linkValue = sprintf('<%s>; rel="successor-version"', $successorUrl);
            $existingLink = $response->headers->get('Link');
            $response->headers->set('Link', $existingLink ? $existingLink.', '.$linkValue : $linkValue);
        }

        return $response;
    }
}

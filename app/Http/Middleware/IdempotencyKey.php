<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

final class IdempotencyKey
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = trim((string) $request->headers->get('Idempotency-Key', ''));

        if ($idempotencyKey === '') {
            return $next($request);
        }

        if (preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $idempotencyKey) !== 1) {
            return new JsonResponse([
                'message' => __('api.errors.idempotency_key_invalid'),
            ], 422);
        }

        $cacheKey = $this->cacheKey($request, $idempotencyKey);
        $requestHash = $this->requestHash($request);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            if (($cached['request_hash'] ?? null) !== $requestHash) {
                return new JsonResponse([
                    'message' => __('api.errors.idempotency_key_conflict'),
                ], 409);
            }

            $response = new Response(
                (string) ($cached['body'] ?? ''),
                (int) ($cached['status'] ?? 200),
            );

            $contentType = $cached['content_type'] ?? null;
            if (is_string($contentType) && $contentType !== '') {
                $response->headers->set('Content-Type', $contentType);
            }

            $response->headers->set('Idempotency-Key', $idempotencyKey);
            $response->headers->set('Idempotency-Replayed', 'true');

            return $response;
        }

        $response = $next($request);

        if ($this->shouldCacheResponse($response)) {
            $ttl = max((int) config('idempotency.ttl_minutes', 10), 1);

            Cache::put($cacheKey, [
                'request_hash' => $requestHash,
                'status' => $response->getStatusCode(),
                'body' => $response->getContent() ?: '',
                'content_type' => $response->headers->get('Content-Type'),
            ], now()->addMinutes($ttl));
        }

        $response->headers->set('Idempotency-Key', $idempotencyKey);

        return $response;
    }

    private function cacheKey(Request $request, string $idempotencyKey): string
    {
        $scope = (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
        $routeName = (string) ($request->route()?->getName() ?? $request->path());

        return 'idempotency:'.sha1(sprintf('%s|%s|%s', $scope, $routeName, $idempotencyKey));
    }

    private function requestHash(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->method(),
            $request->path(),
            $request->getContent(),
        ]));
    }

    private function shouldCacheResponse(Response $response): bool
    {
        return $response->getStatusCode() >= 200
            && $response->getStatusCode() < 300;
    }
}

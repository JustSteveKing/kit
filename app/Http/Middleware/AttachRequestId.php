<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AttachRequestId
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId((string) $request->headers->get('X-Request-Id', ''));

        $request->attributes->set('request_id', $requestId);
        $request->headers->set('X-Request-Id', $requestId);
        Log::withContext(['request_id' => $requestId]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }

    private function resolveRequestId(string $candidate): string
    {
        $candidate = trim($candidate);

        if ($candidate !== '' && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $candidate) === 1) {
            return $candidate;
        }

        return (string) Str::uuid();
    }
}

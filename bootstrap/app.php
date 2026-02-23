<?php

declare(strict_types=1);

use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\EnsureJsonApiRequest;
use App\Http\Middleware\EnforceTransportSecurity;
use App\Http\Middleware\IdempotencyKey;
use App\Http\Middleware\SetRequestLocale;
use App\Http\Middleware\Sunset;
use App\Support\SecurityAudit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api/routes.php',
        apiPrefix: '',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'idempotency' => IdempotencyKey::class,
            'sunset' => Sunset::class,
        ]);

        $trustedProxies = (string) env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies($trustedProxies !== '' ? $trustedProxies : null);

        $trustedHosts = array_values(array_filter(array_map(
            static fn (string $host): string => trim($host),
            explode(',', (string) env('TRUSTED_HOSTS', '')),
        )));
        if ($trustedHosts !== []) {
            $middleware->trustHosts(at: $trustedHosts, subdomains: false);
        }

        $middleware->prependToGroup('api', EnsureJsonApiRequest::class);
        $middleware->prependToGroup('api', EnforceTransportSecurity::class);
        $middleware->prependToGroup('api', SetRequestLocale::class);
        $middleware->prependToGroup('api', AttachRequestId::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            SecurityAudit::log('auth.unauthenticated', [
                'guard' => 'sanctum',
            ]);

            return new JsonResponse([
                'message' => __('api.errors.unauthenticated'),
            ], 401);
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            SecurityAudit::log('auth.forbidden', [
                'exception' => $exception::class,
            ]);

            return new JsonResponse([
                'message' => __('api.errors.forbidden'),
            ], 403);
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            SecurityAudit::log('auth.forbidden', [
                'exception' => $exception::class,
            ]);

            return new JsonResponse([
                'message' => __('api.errors.forbidden'),
            ], 403);
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            SecurityAudit::log('api.rate_limited');

            $response = new JsonResponse([
                'message' => __('api.errors.too_many_requests'),
            ], 429);

            $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;
            if ($retryAfter !== null) {
                $response->headers->set('Retry-After', (string) $retryAfter);
            }

            return $response;
        });

        $exceptions->render(function (ValidationException $exception, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            SecurityAudit::log('api.validation_failed', [
                'errors' => array_keys($exception->errors()),
            ]);

            return new JsonResponse([
                'message' => __('api.errors.validation_failed'),
                'errors' => $exception->errors(),
            ], $exception->status);
        });

        $exceptions->render(function (InvalidSignatureException $exception, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            SecurityAudit::log('auth.email_verification.invalid_signature');

            return new JsonResponse([
                'message' => __('api.auth.invalid_verification_link'),
            ], 403);
        });
    })->create();

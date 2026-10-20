<?php

declare(strict_types=1);

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnforceTransportSecurity;
use App\Http\Middleware\RetireSunsetEndpoints;
use App\Http\Middleware\SetRequestLocale;
use App\Support\SecurityAudit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api/routes.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: '',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // There is no login page to send a guest to; a 401 problem is the whole answer.
        $middleware->redirectGuestsTo(static fn(): ?string => null);

        $trustedProxies = (string) env('TRUSTED_PROXIES', '*');
        $middleware->trustProxies('' !== $trustedProxies ? $trustedProxies : null);

        $trustedHosts = array_values(array_filter(array_map(
            mb_trim(...),
            explode(',', (string) env('TRUSTED_HOSTS', '')),
        )));
        if ([] !== $trustedHosts) {
            $middleware->trustHosts(at: $trustedHosts, subdomains: false);
        }

        $middleware->prependToGroup('api', AddSecurityHeaders::class);
        $middleware->prependToGroup('api', EnforceTransportSecurity::class);
        $middleware->prependToGroup('api', SetRequestLocale::class);
        $middleware->appendToGroup('api', RetireSunsetEndpoints::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // api-toolbox renders every API error as RFC 9457 problem details. These maps only swap
        // in a translated message, which becomes the problem's "detail"; the type is unchanged.
        $exceptions->map(AuthenticationException::class, static fn(AuthenticationException $exception): AuthenticationException => new AuthenticationException(
            __('api.errors.unauthenticated'),
            $exception->guards(),
        ));

        $exceptions->map(AuthorizationException::class, static fn(AuthorizationException $exception): AccessDeniedHttpException => new AccessDeniedHttpException(
            __('api.errors.forbidden'),
            $exception,
        ));

        $exceptions->map(InvalidSignatureException::class, static fn(InvalidSignatureException $exception): AccessDeniedHttpException => new AccessDeniedHttpException(
            __('api.auth.invalid_verification_link'),
            $exception,
        ));

        // Laravel's message names the model class; a client only needs to know it was not found.
        $exceptions->map(ModelNotFoundException::class, static fn(ModelNotFoundException $exception): NotFoundHttpException => new NotFoundHttpException(
            __('api.errors.not_found'),
            $exception,
        ));

        $exceptions->map(ThrottleRequestsException::class, static fn(ThrottleRequestsException $exception): ThrottleRequestsException => new ThrottleRequestsException(
            __('api.errors.too_many_requests'),
            $exception,
            $exception->getHeaders(),
        ));

        // Audit callbacks run before api-toolbox's renderer and return null, so the problem
        // response is still the package's.
        $exceptions->render(function (AuthenticationException $exception, Request $request): null {
            if ($request->expectsJson()) {
                SecurityAudit::log('auth.unauthenticated', [
                    'guard' => 'sanctum',
                ]);
            }

            return null;
        });

        $exceptions->render(function (AccessDeniedHttpException $exception, Request $request): null {
            if ( ! $request->expectsJson()) {
                return null;
            }

            $cause = $exception->getPrevious() ?? $exception;

            if ($cause instanceof InvalidSignatureException) {
                SecurityAudit::log('auth.email_verification.invalid_signature');
            } else {
                SecurityAudit::log('auth.forbidden', [
                    'exception' => $cause::class,
                ]);
            }

            return null;
        });

        $exceptions->render(function (TooManyRequestsHttpException $exception, Request $request): null {
            if ($request->expectsJson()) {
                SecurityAudit::log('api.rate_limited');
            }

            return null;
        });

        $exceptions->render(function (ValidationException $exception, Request $request): null {
            if ($request->expectsJson()) {
                SecurityAudit::log('api.validation_failed', [
                    'errors' => array_keys($exception->errors()),
                ]);
            }

            return null;
        });
    })->create();

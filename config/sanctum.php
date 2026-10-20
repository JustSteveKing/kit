<?php

declare(strict_types=1);

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
        // Sanctum::currentRequestHost(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | Off: every token Kit issues carries its own expires_at instead, from
    | "lifetimes" below. A global expiration would cap every token, so an API
    | key could never outlive a sign-in session.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Lifetimes
    |--------------------------------------------------------------------------
    |
    | Session tokens are issued by register and login. API keys are created by
    | POST /v1/auth/tokens, for scripts, integrations and agents; a client picks
    | an expiry up to the maximum, or gets the default.
    |
    */

    'lifetimes' => [
        'session_minutes' => (int) env('SANCTUM_EXPIRATION', 120),
        'api_key_default_days' => (int) env('API_KEY_DEFAULT_DAYS', 90),
        'api_key_max_days' => (int) env('API_KEY_MAX_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Abilities
    |--------------------------------------------------------------------------
    |
    | "available" is every ability a route checks. "default" is what a session
    | token from register or login gets. An API key gets the abilities its
    | creator asks for, limited to ones the creating token holds itself.
    |
    */

    'abilities' => [
        'available' => [
            'auth:me',
            'auth:logout',
            'auth:verification:send',
            'auth:tokens:read',
            'auth:tokens:create',
            'auth:tokens:delete',
            'webhooks:read',
            'webhooks:write',
        ],
        'default' => [
            'auth:me',
            'auth:logout',
            'auth:verification:send',
            'auth:tokens:read',
            'auth:tokens:create',
            'auth:tokens:delete',
            'webhooks:read',
            'webhooks:write',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => PreventRequestForgery::class,
    ],

];

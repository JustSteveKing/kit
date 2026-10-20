<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Event Types
    |--------------------------------------------------------------------------
    |
    | Every event type a subscription can ask for. Add a type here when you
    | add a class implementing App\Webhooks\WebhookEvent. A subscription to
    | "*" receives all of them. Pings are always delivered.
    |
    */

    'events' => [
        // 'project.created',
    ],

    /*
    |--------------------------------------------------------------------------
    | CloudEvents
    |--------------------------------------------------------------------------
    |
    | Deliveries are CloudEvents 1.0 in structured mode. "source" identifies
    | this API in every event. "origin" is the DNS name sent as
    | WebHook-Request-Origin, from the CloudEvents webhook specification.
    |
    */

    'source' => env('WEBHOOKS_SOURCE', mb_rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/v1'),

    'origin' => env('WEBHOOKS_ORIGIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),

    /*
    |--------------------------------------------------------------------------
    | Endpoint Validation
    |--------------------------------------------------------------------------
    |
    | When on, a subscription receives nothing until its endpoint consents to
    | deliveries through the CloudEvents webhook handshake: an OPTIONS request
    | answered with WebHook-Allowed-Origin. It proves the URL's owner wants the
    | events, which the address checks below cannot.
    |
    */

    'validate_endpoints' => (bool) env('WEBHOOKS_VALIDATE_ENDPOINTS', false),

    // Deliveries a minute to ask each endpoint for, as WebHook-Request-Rate. Null asks for none,
    // and an endpoint that grants no rate is then not limited. A granted rate is always obeyed.
    'request_rate' => env('WEBHOOKS_REQUEST_RATE'),

    // Hours the WebHook-Request-Callback consent URL sent with each validation request stays valid.
    'callback_hours' => (int) env('WEBHOOKS_CALLBACK_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Retry Schedule
    |--------------------------------------------------------------------------
    |
    | Seconds to wait before each retry. A delivery is attempted once, then
    | once after each of these, and then marked failed; the last value is
    | never repeated. Publish this schedule to your consumers.
    |
    */

    'backoff' => [10, 60, 300, 1800, 7200, 21600],

    'timeout' => (int) env('WEBHOOKS_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Disabling Failing Subscriptions
    |--------------------------------------------------------------------------
    |
    | After this many deliveries in a row fail every attempt, a subscription
    | is disabled with a reason, until its owner re-enables it.
    |
    */

    'disable_after' => (int) env('WEBHOOKS_DISABLE_AFTER', 10),

    /*
    |--------------------------------------------------------------------------
    | Targets
    |--------------------------------------------------------------------------
    |
    | Subscription URLs are user input. They must be https, and must not
    | resolve to a private, loopback or reserved address, which would let a
    | consumer make this server call its own network. Loosen these locally.
    |
    */

    'require_https' => (bool) env('WEBHOOKS_REQUIRE_HTTPS', true),

    'allow_private_networks' => (bool) env('WEBHOOKS_ALLOW_PRIVATE_NETWORKS', false),

    // Days an event and its deliveries are kept; `model:prune` runs daily.
    'retention_days' => (int) env('WEBHOOKS_RETENTION_DAYS', 30),

];

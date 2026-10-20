<?php

declare(strict_types=1);

use JustSteveKing\Toolbox\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Each feature maps to one HTTP standard. A feature that is not listed here
    | registers nothing at all. Options are passed inline to the feature.
    |
    */

    'features' => [
        Features::problemDetails([
            // Where problem type slugs resolve to. Point this at your docs.
            'type_base_uri' => '/problems',
            // Paths that always get problem details, whatever the request's Accept header.
            'paths' => ['v1/*'],
            // Type plain HTTP errors with the apiguide.dev catalogue rather than about:blank.
            'apiguide' => true,
            // The challenge a 401 carries, which RFC 9110 section 15.5.2 says it MUST.
            'www_authenticate' => 'Bearer',
        ]),
        Features::deprecation([
            // Run on every request; turn off to add the "deprecation" middleware per route instead.
            'global' => true,
        ]),
        Features::conditionalRequests(),
        Features::pagination(),
        Features::idempotency([
            // Seconds a completed response is kept for replay, and so how long a key is valid.
            // The draft (section 2.5.2) says to publish this in your API documentation.
            'ttl' => (int) env('IDEMPOTENCY_TTL', 86400),
        ]),
        Features::negotiation([
            // No standard defines a request ID header; this is the common convention. false turns
            // request IDs off.
            'request_id_header' => 'X-Request-Id',
            // Keep a well-formed request ID a proxy or client sent, rather than replace it.
            'trust_request_id' => true,
            // W3C Trace Context on every request, and onto outgoing HTTP client requests.
            'trace_context' => true,
            'propagate_trace' => true,
        ]),
        Features::preferences(),
        // Off until an endpoint returns 202 Accepted; turning it on adds the status monitor route.
        // Features::asyncOperations([
        //     // Where the status monitor lives, and the middleware it runs behind.
        //     'path' => 'v1/operations',
        //     'middleware' => ['api', 'auth:sanctum'],
        //     // Seconds an operation's status is kept after its last change.
        //     'ttl' => 86400,
        //     // Retry-After, in seconds, while an operation is still running.
        //     'retry_after' => 5,
        // ]),
        Features::mergePatch(),
        Features::digests(),
        Features::messageSignatures([
            // Keys by keyid. The algorithm belongs to the key; the message never chooses it.
            'keys' => [
                // 'webhooks' => ['algorithm' => 'hmac-sha256', 'secret' => env('WEBHOOK_SECRET')],
                // 'partner' => ['algorithm' => 'ed25519', 'public_key' => storage_path('keys/partner.pem')],
            ],
            // What signature.verify requires, beyond RFC 9421 itself.
            'verify' => [
                'required' => ['@method', '@authority', '@path'],
                'max_age' => 300,
                'leeway' => 30,
                'cover_content' => true,
            ],
            // The key signature.sign uses when none is named.
            'sign' => ['key' => null],
        ]),
        Features::apiCatalog([
            // Each API: its URL as href, then any of service-desc (a machine-readable description,
            // such as OpenAPI), service-doc (human documentation), service-meta and status.
            'apis' => [
                [
                    'href' => '/v1',
                    'service-desc' => ['href' => '/docs/openapi.yaml', 'type' => 'application/vnd.oai.openapi'],
                    'service-doc' => ['href' => '/docs', 'type' => 'text/html'],
                ],
            ],
            // Other API catalogs this one links to.
            'catalogs' => [],
            'max_age' => 3600,
            // RFC 9727, section 8 recommends rate limiting the catalog, such as ['throttle:60,1'].
            'middleware' => ['throttle:60,1'],
        ]),
        // Experimental: built on an IETF draft that can still change.
        // Features::rateLimitHeaders(),
    ],

];

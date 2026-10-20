# API lifecycle: deprecation, catalog, negotiation, request IDs and rate limits

## Deprecation and Sunset (RFC 9745, RFC 8594)

Feature: `Features::deprecation()`. Mark a controller or a single action with attributes:

```php
use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;

#[Deprecation(at: '2026-06-30T23:59:59Z', link: 'https://docs.example.com/deprecations/orders-v1')]
#[Sunset(at: '2027-01-01T00:00:00Z', link: 'https://docs.example.com/sunset-policy')]
final class OrderController
{
    #[Deprecation(at: '2026-03-01')]
    public function export() { /* ... */ }
}
```

- These are not PHP's `#[\Deprecated]`, which would raise a PHP deprecation on every request. Import them from `JustSteveKing\Toolbox\Deprecation\Attributes`.
- Only controller routes are read. Closure routes are not, because `route:cache` breaks their attributes. Move a closure route to a controller to deprecate it.
- A date without an offset is UTC. A deprecation date can be in the future. A sunset earlier than the deprecation throws.
- `#[Deprecation(link: ...)]` with no date publishes a deprecation policy only.
- With the `global` option on (the default), nothing is added to routes. With it off, add the `deprecation` middleware.
- Reading deprecations from elsewhere, such as a manifest: bind `JustSteveKing\Toolbox\Deprecation\Contracts\DeprecationSource`, which returns a `JustSteveKing\Toolbox\Deprecation\Notice` for a route.

## API catalog (RFC 9727)

Feature: `Features::apiCatalog([...])`. It serves `/.well-known/api-catalog`, listing the APIs and where their OpenAPI descriptions and documentation live.

```php
Features::apiCatalog([
    'apis' => [
        [
            'href' => '/api/v1',
            'service-desc' => ['href' => '/openapi.json', 'type' => 'application/vnd.oai.openapi+json'],
            'service-doc' => ['href' => 'https://docs.example.com', 'type' => 'text/html'],
            'status' => '/api/v1/health',
        ],
    ],
    'middleware' => ['throttle:60,1'],
]),
```

- `href` is the API itself. `service-desc` is a machine-readable description, `service-doc` documentation for people, `service-meta` policies and other metadata, and `status` a health endpoint.
- The catalog is public. List only APIs meant to be found, never internal ones.
- Point to it from an API root with the `api-catalog.link` middleware.
- To build it from a database or gateway, bind `JustSteveKing\Toolbox\ApiCatalog\Contracts\CatalogSource`.

## Negotiation: 406 and 415

Feature: `Features::negotiation()`.

```php
Route::post('leads', CreateLead::class)->middleware(['produces:application/json', 'consumes:application/json']);
Route::patch('leads/{lead}', UpdateLead::class)->middleware('consumes:application/merge-patch+json');
```

- `produces` returns 406 when `Accept` rules out every listed type. A missing `Accept` accepts anything.
- `consumes` returns 415 when the request has content of another type, or content with no `Content-Type`. A request with no content always passes.
- `consumes:application/json` does not accept `application/merge-patch+json`. List each type a route takes.
- The last `consumes` declaration wins: a route's overrides its group's, and a controller's overrides the route's. A PATCH route in a group that consumes `application/json` declares `consumes:application/merge-patch+json` itself; do not lift the group's declaration with `withoutMiddleware()`.

## Request IDs and tracing

These are on with Negotiation and need no code.

- Every response carries `X-Request-Id`, which can be renamed with the `request_id_header` option. A well-formed incoming ID is kept; otherwise a UUIDv7 is assigned.
- The ID is in the log context as `request_id`, and problem `instance` is `urn:uuid:<id>`. To find the logs for a reported error, search for that ID.
- W3C `traceparent` is continued from the caller, added to the log context and to queued jobs, and sent on with Laravel HTTP client requests. Do not add these headers to outgoing requests by hand.
- Read the current ID by injecting `JustSteveKing\Toolbox\Contracts\RequestIdentifier` and calling `current()`. It returns null when request IDs are off.

## RateLimit headers (experimental)

Feature: `Features::rateLimitHeaders()`. It is off unless listed, and can change in a minor release.

- Keep using Laravel's `throttle` middleware and `RateLimiter::for()`. Responses gain `RateLimit-Policy` and `RateLimit`, and a 429 becomes a `rate-limit-exceeded` problem with `Retry-After` and `Cache-Control: no-store`.
- The policy name is the limiter name. `throttle:60,1` is `"default"`.
- Applications that use `throttleWithRedis()` get no RateLimit headers. This is a known gap.
- For a billing-period allowance rather than a short window, throw a problem typed `ApiGuide::QUOTA_EXCEEDED` instead.

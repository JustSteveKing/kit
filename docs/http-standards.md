# HTTP standards

Kit uses the published standard wherever one exists, rather than a header or parameter of its own.
Clients that already speak HTTP well then need very little documentation, and an agent calling
the API has seen these patterns before.

The standards come from [api-toolbox](https://github.com/JustSteveKing/api-toolbox), one feature
per standard, switched on in `config/api-toolbox.php`. A feature that is switched off registers
nothing: no middleware alias, no route and no test assertion.

| Need | Standard | How Kit does it |
| --- | --- | --- |
| Errors | RFC 9457 | Every error, see [Errors](errors.md) |
| Safe retries | Idempotency-Key draft | `idempotent` middleware |
| Lists | RFC 8288 | `CursorPage` with `Link` headers |
| Cheap re-fetches | RFC 9110 | `conditional` middleware, `ETag` and `304` |
| Lost updates | RFC 9110 | `HasValidators` and `preconditions.required` |
| Partial updates | RFC 7396 | `merge-patch` middleware |
| Retiring endpoints | RFC 9745, RFC 8594 | `#[Deprecation]` and `#[Sunset]` |
| Tracing | W3C Trace Context | On every request |
| Media types | RFC 9110 | `consumes:` on the `v1` group |
| Discovery | RFC 9727 | `/.well-known/api-catalog` |
| Signatures | RFC 9421 | Webhooks, see [Webhooks](webhooks.md) |

## Idempotency

A client that sends a POST and loses the connection cannot tell whether it worked. With an
`Idempotency-Key`, it can send the same request again and get the first response back, without the
write happening twice.

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"name": "Jane Doe", "email": "jane@example.com", "password": "Password123!"}'
```

| Situation | Response |
| --- | --- |
| Retry with the same key and body | The original response, replayed without running again |
| Same key, different body | `422`, `https://apiguide.dev/errors/idempotency-key-conflict` |
| Retry while the first is still running | `409` with `Retry-After` |
| Malformed key | `400` |

Keys are scoped to the user, or to the client address before sign-in, and kept for
`IDEMPOTENCY_TTL` seconds (a day). Register, forgot password, creating an API key and creating a
webhook subscription all honour them, and `make:endpoint` adds `idempotent` to every POST it
generates.

Add it to any write where a duplicate would hurt. Use `idempotent:required` to refuse requests
without a key:

```php
Route::post('/payments', CreatePaymentController::class)
    ->middleware(['abilities:payments:write', 'idempotent:required']);
```

## Pagination

Lists are cursor paginated. The body is the array of items, and navigation is in RFC 8288 `Link`
headers:

```http
HTTP/1.1 200 OK
Link: <http://127.0.0.1:8000/v1/auth/tokens?cursor=eyJpZCI6...>; rel="next", <http://127.0.0.1:8000/v1/auth/tokens>; rel="first"
```

There is a `rel="next"` when there is a later page, a `rel="prev"` when there is an earlier one,
and always a `rel="first"`. A cursor that cannot be decoded is a `400`.

Return a page from a controller with:

```php
return CursorPage::of(
    $user->tokens()->latest('id')->cursorPaginate(25),
    PersonalAccessTokenResource::class,
);
```

Cursors are opaque to clients, which is what makes this cheaper than offset pages: the database
seeks to the cursor rather than counting past every row before it. Sort on a unique, indexed
column.

## ETags and conditional requests

`GET /v1/auth/me` sends an `ETag`. Repeat the request with `If-None-Match` and, if the user has not
changed, the answer is `304 Not Modified` with no body:

```bash
curl -i http://127.0.0.1:8000/v1/auth/me \
  -H "Authorization: Bearer <TOKEN>" \
  -H 'If-None-Match: "a1b2c3"'
```

Add the `conditional` middleware to any GET whose response a client might fetch again.

To stop two clients overwriting each other's changes, have the model implement api-toolbox's
`HasValidators` contract (the `HasModelValidators` trait does the work) and add
`preconditions.required` to the write route. A write without `If-Match` is then a `428`, and one
whose ETag is stale is a `412`. This is the standard alternative to a
`version` field in the body.

## Partial updates

For a `PATCH` that takes a partial document, use JSON Merge Patch: the `merge-patch` middleware and
the `application/merge-patch+json` media type. Validate the merged document, not the patch, so a
field set to `null` is checked the same way as one sent in full.

Kit's own `PATCH /v1/webhooks/{webhook}` takes plain JSON with only the fields that change, which
is enough for a resource that small.

## Deprecation and sunset

Mark a controller with api-toolbox's attributes when you plan to retire it:

```php
use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;

#[Deprecation(at: '2030-01-01T00:00:00Z', link: 'https://docs.example.com/deprecations/projects-v1')]
#[Sunset(at: '2030-07-01T00:00:00Z', link: 'https://docs.example.com/sunset-policy')]
final class ListProjectsController
{
    // ...
}
```

Every response from it then carries `Deprecation` (RFC 9745) and `Sunset` (RFC 8594) headers, with
`Link` headers pointing at the pages you named. Clients and their tooling can warn on those
without reading your changelog.

Once the sunset date passes, `RetireSunsetEndpoints` answers `410 Gone` as a problem, still with
both headers, so you do not have to remember to delete the route on the day.

The attributes are read from controller classes, so they do not work on closure routes. They can
go on the class or on its `__invoke` method.

## Request IDs and tracing

Every response has an `X-Request-Id`. Kit keeps one a proxy or client sent when it is well formed,
and otherwise generates a UUIDv7. It is also the `instance` of any problem, so an error a client
reports leads straight to the log lines for that request.

Requests join a W3C trace when they arrive with a `traceparent` header, or start one when they do
not, and outgoing HTTP client requests carry it on. Both the request ID and the trace ID are in the
log context.

## Media types

Every route in `v1` sits behind `consumes:application/json`, so a write with any other
`Content-Type` is a `415` that names the type it accepts. [Building endpoints](building-endpoints.md#taking-a-type-other-than-json)
shows how to accept an upload.

## Preferences

The `prefer` middleware and the `Preferences` class read the RFC 7240 `Prefer` header. Use it for
options such as `return=minimal` rather than inventing a query parameter.

## Long-running work

Async operations, a `202 Accepted` with a status monitor, are switched off. Turning them on in
`config/api-toolbox.php` adds a route under `v1/operations`, which then needs documenting like any
other.

## API catalog

`GET /.well-known/api-catalog` returns an RFC 9727 catalog that points at the API, its OpenAPI spec
and its documentation, so tools can discover the API from its host alone. Edit `apis` in
`config/api-toolbox.php` when you add a version or move the docs.

## Testing

api-toolbox adds an assertion for each feature to every test response, and they check the exact
header syntax:

```php
$replay->assertIdempotentReplay($original);
$response->assertLinkHeader('next');
$response->assertETag();
$response->assertDeprecated();
$response->assertProblemType('https://apiguide.dev/errors/idempotency-key-conflict', 422);
```

The `api-toolbox-development` skill, installed through Laravel Boost, has the detail on each
feature.

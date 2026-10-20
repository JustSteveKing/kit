# HTTP standards

Wherever a published standard covers something, Kit uses it instead of a header or parameter of
its own. A client that already speaks HTTP well needs very little documentation as a result, and an
agent calling the API has seen these patterns many times before.

Each standard is one feature of [api-toolbox](https://github.com/JustSteveKing/api-toolbox),
switched on in `config/api-toolbox.php`. Switching one off removes everything it registers,
including its middleware alias, its routes and its test assertions.

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

When a client sends a POST and the connection drops, it has no way of knowing whether the write
happened. With an `Idempotency-Key` it can simply send the request again. If the first one worked,
it gets the original response back and nothing is written twice.

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

Keys belong to the user, or to the client's address before sign-in, and last for
`IDEMPOTENCY_TTL` seconds, a day by default. Register, forgot password, creating an API key and
creating a webhook subscription all honour them, and `make:endpoint` adds `idempotent` to every POST
it generates.

Put it on any write where a duplicate would hurt. Where a duplicate would hurt a lot, use
`idempotent:required` and refuse requests that come without a key:

```php
Route::post('/payments', CreatePaymentController::class)
    ->middleware(['abilities:payments:write', 'idempotent:required']);
```

## Pagination

Lists are cursor paginated. The body is just the array of items, and navigation lives in RFC 8288
`Link` headers:

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

On a large table this is far cheaper than offset pages. The database seeks straight to the cursor
instead of counting past every row in front of it. Sort on a unique, indexed column, and treat the
cursor as opaque on the client side.

## ETags and conditional requests

`GET /v1/auth/me` sends an `ETag`. Send it back in `If-None-Match` and, as long as the user has not
changed, the answer is a `304 Not Modified` with no body:

```bash
curl -i http://127.0.0.1:8000/v1/auth/me \
  -H "Authorization: Bearer <TOKEN>" \
  -H 'If-None-Match: "a1b2c3"'
```

Add the `conditional` middleware to any GET a client is likely to fetch more than once.

The same headers stop two clients overwriting each other's changes. Have the model implement
api-toolbox's `HasValidators` contract (the `HasModelValidators` trait does the work) and add
`preconditions.required` to the write route. A write without `If-Match` is then a `428`, and one
with a stale ETag is a `412`. Reach for this before you add a `version` field to the body.

## Partial updates

For a `PATCH` that takes a partial document, use JSON Merge Patch, with the `merge-patch`
middleware and the `application/merge-patch+json` media type. Validate the merged document instead
of the patch, so a field set to `null` goes through the same rules as one sent in full.

Kit's own `PATCH /v1/webhooks/{webhook}` takes plain JSON containing only the fields that change.
For a resource that small, that is enough.

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

From then on, every response carries `Deprecation` (RFC 9745) and `Sunset` (RFC 8594) headers,
plus `Link` headers pointing at the pages you named. Client tooling can warn on those, and nobody
has to read your changelog to find out.

When the sunset date passes, `RetireSunsetEndpoints` starts answering `410 Gone` as a problem,
still carrying both headers. You do not have to remember to delete the route on the day.

The attributes go on the controller class or its `__invoke` method. Closure routes have nowhere to
put them, so they cannot be deprecated this way.

## Request IDs and tracing

Every response has an `X-Request-Id`. If a proxy or client sent a well-formed one, Kit keeps it;
otherwise it generates a UUIDv7. The same ID is the `instance` of any problem, so an error a client
reports takes you straight to that request's log lines.

A request that arrives with a `traceparent` header joins that W3C trace, and one without starts a
new trace. Outgoing HTTP client requests carry it on. The request ID and the trace ID both go into
the log context.

## Media types

The whole `v1` group sits behind `consumes:application/json`. A write with any other
`Content-Type` gets a `415` that names the type it accepts.
[Building endpoints](building-endpoints.md#taking-a-type-other-than-json) shows how to accept an
upload.

## Preferences

The `prefer` middleware and the `Preferences` class read the RFC 7240 `Prefer` header. When an
endpoint needs an option such as `return=minimal`, use this before inventing a query parameter.

## Long-running work

Async operations (a `202 Accepted` with a status monitor) are switched off. Turning them on in
`config/api-toolbox.php` adds a route under `v1/operations`, and that route needs documenting like
any other.

## API catalog

`GET /.well-known/api-catalog` returns an RFC 9727 catalog pointing at the API, its OpenAPI spec and
its documentation, so a tool that only knows your host name can find the lot. Edit `apis` in
`config/api-toolbox.php` when you add a version or move the docs.

## Testing

api-toolbox adds an assertion for each feature to every test response, and each one checks the
exact header syntax:

```php
$replay->assertIdempotentReplay($original);
$response->assertLinkHeader('next');
$response->assertETag();
$response->assertDeprecated();
$response->assertProblemType('https://apiguide.dev/errors/idempotency-key-conflict', 422);
```

For the detail on any feature, the `api-toolbox-development` skill is installed through Laravel
Boost.

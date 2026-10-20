# Conditional requests, caching and pagination

## Conditional requests (RFC 9110 section 13, RFC 6585)

Feature: `Features::conditionalRequests()`. It is opt-in per route.

Use it to prevent lost updates, where two clients edit the same resource and the second write silently overwrites the first, and to answer re-fetches with 304.

### 1. Give the model validators

```php
use JustSteveKing\Toolbox\ConditionalRequests\Concerns\HasModelValidators;
use JustSteveKing\Toolbox\ConditionalRequests\Contracts\HasValidators;

final class Document extends Model implements HasValidators
{
    use HasModelValidators;
}
```

The strong ETag comes from the model's class, key and `updated_at`, and `Last-Modified` comes from `updated_at`. Two writes in the same second share a timestamp, so for real optimistic concurrency add an integer `version` column that every write increments, and return it:

```php
protected function entityTagVersion(): ?string
{
    return (string) $this->version;
}
```

The model is found through route model binding, so the route parameter must be bound to it.

### 2. Add the middleware

```php
Route::middleware('conditional')->group(function () {
    Route::get('documents/{document}', ShowDocument::class);
    Route::put('documents/{document}', ReplaceDocument::class)->middleware('preconditions.required');
    Route::patch('documents/{document}', UpdateDocument::class)->middleware('preconditions.required');
    Route::delete('documents/{document}', DeleteDocument::class)->middleware('preconditions.required');
});
```

- `conditional` = `preconditions` + `validators`.
- `preconditions.required` returns 428 when a PUT, PATCH or DELETE has no `If-Match` or `If-Unmodified-Since`. Name other methods with `preconditions.required:PUT,POST`.
- Laravel 13's `#[Middleware('conditional')]` controller attribute also works.

### What clients see

- `GET` returns `ETag` and `Last-Modified`. A repeat with `If-None-Match` returns 304 with no body.
- `PATCH` with a stale `If-Match` returns 412 `precondition-failed`, carrying the current `ETag` to re-fetch.
- A write without a precondition on a `preconditions.required` route returns 428 `precondition-required`.
- `PUT` with `If-None-Match: *` creates without overwriting.
- Without a bound model, a GET's ETag is a hash of the response content. Use `validators:weak` for a weak one.

Do not add a `version` field to request bodies for locking, and do not compare timestamps in controllers. Preconditions are checked before the controller runs.

## Caching directives

Use Laravel's own `cache.headers` middleware beside `conditional`, and leave its `etag` option off, because `conditional` already sends the ETag:

```php
Route::get('documents/{document}', ShowDocument::class)->middleware([
    'conditional',
    'cache.headers:public;max_age=60;stale_while_revalidate=30;stale_if_error=86400',
]);
```

## Cursor pagination with Link headers (RFC 8288)

Feature: `Features::pagination()`.

```php
use JustSteveKing\Toolbox\Pagination\CursorPage;

public function index(Request $request)
{
    return CursorPage::of(
        Post::query()->where('published', true)->orderBy('id')->cursorPaginate(20)->withQueryString(),
        PostResource::class,
    );
}
```

- The body is a plain JSON array of the items. Navigation is in `Link` headers: `next` and `prev` when they exist, and `first` always. There is no `last`.
- Call `withQueryString()` so filters survive into the links.
- The resource class is optional.
- Use `cursorPaginate()`, not `paginate()`. `CursorPage` takes a `CursorPaginator`.
- A cursor that cannot be decoded is a 400 `invalid-pagination-cursor` for every cursor paginator in the app, not a silent first page.

Clients read `Link: <...>; rel="next"` rather than `links.next` in the body. Do not also add `links` or `meta` to the body.

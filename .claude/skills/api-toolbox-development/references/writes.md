# Writes: Merge Patch, Prefer, idempotency and async operations

## JSON Merge Patch for PATCH (RFC 7396)

Feature: `Features::mergePatch()`. A member present in the patch changes, a member set to `null` is removed, and a member absent is left alone, all the way down nested objects.

```php
use JustSteveKing\Toolbox\MergePatch\MergePatch;

Route::patch('posts/{post}', function (Post $post, MergePatch $patch) {
    $fields = ['title', 'summary', 'tags'];
    $document = $patch->applyTo($post->only($fields));

    Validator::make($document, [
        'title' => ['required', 'string'],
        'summary' => ['nullable', 'string'],
        'tags' => ['array'],
    ])->validate();

    $post->update($patch->changesTo($post->only($fields)));

    return new PostResource($post);
})->middleware('merge-patch');
```

- **Validate the merged document from `applyTo()`, not the request input.** In a patch, `null` means "remove", so the resource's rules would misread it, and `required` would fail on every field the client left out.
- `changesTo()` returns only the touched members, with removed ones as `null`. That is what `update()` wants.
- Inject `MergePatch`. Do not use `$request->all()`: it cannot tell `{}` from `[]`.
- The `merge-patch` middleware answers other PATCH formats with 415 and `Accept-Patch`, and malformed JSON with 400 `malformed-request-body`.
- Clients send `Content-Type: application/merge-patch+json`.

## Prefer (RFC 7240)

Feature: `Features::preferences()`. Add the `prefer` middleware to write routes:

```php
Route::middleware('prefer')->group(function () {
    Route::post('items', CreateItem::class);
    Route::patch('items/{item}', UpdateItem::class);
});
```

- `Prefer: return=minimal` empties a successful write response: 200 becomes 204, while 201 stays 201 with its `Location`. The controller does nothing different.
- `Vary: Prefer` and `Preference-Applied` are added for you.
- Read preferences by injecting `JustSteveKing\Toolbox\Preferences\Preferences`: `respondAsync()`, `returnMinimal()`, `handlingStrict()` and `wait()`. Record one you honoured yourself with `$preferences->applied('wait', '10')`.
- Strict input: add `use HonoursHandlingPreference;` (`JustSteveKing\Toolbox\Preferences\Validation\HonoursHandlingPreference`) to a FormRequest. Under `Prefer: handling=strict`, fields with no rule then fail validation instead of being dropped.

Use `Prefer` instead of query parameters such as `?return=none` or `?async=1`.

## Idempotency-Key for POST and PATCH

Feature: `Features::idempotency()`.

```php
Route::post('charges', CreateCharge::class)->middleware('idempotent');            // honoured when sent
Route::post('payouts', CreatePayout::class)->middleware(['auth:sanctum', 'idempotent:required']); // 400 without a key
```

- A retry with the same key and payload gets the stored response without the action running again. The same key with a different payload is 422. A retry while the first request is still running is 409 with `Retry-After`.
- Keys are scoped to the authenticated user, or to the IP when there is none. The middleware is ordered after authentication automatically.
- Validation errors are stored and replayed. 5xx responses and 429s are not stored, so those can be retried.
- **The cache store must support atomic locks:** Redis, Memcached, database, file, DynamoDB or array. The package refuses APC, session, storage and null stores. Set `'store' => 'redis'` in the options if the default store lacks locks.
- Publish your `ttl` (default 86400 seconds) in your API documentation. The draft requires an API to state its idempotency policy.

Do not build a dedupe table or check for duplicate requests in the controller.

## Async operations: 202 Accepted and a status monitor

Feature: `Features::asyncOperations()`. Any queued job that uses Laravel's `Queueable` trait works.

```php
use JustSteveKing\Toolbox\AsyncOperations\Operations;
use JustSteveKing\Toolbox\Preferences\Preferences;

public function store(StoreReportRequest $request, Operations $operations, Preferences $preferences)
{
    if ($preferences->respondAsync()) {
        return $operations->dispatch(new GenerateReport($request->validated()));
    }

    return new ReportResource((new GenerateReport($request->validated()))->handle());
}
```

Or always dispatch: `return $operations->dispatch(new GenerateReport(...));`.

- `dispatch()` returns a 202 with `Location` pointing to the status monitor (`api/operations/{id}` by default), and `Retry-After`.
- In the job, say where the result lives:

```php
use JustSteveKing\Toolbox\AsyncOperations\CurrentOperation;

public function handle(CurrentOperation $operation): void
{
    $report = Report::create(/* ... */);

    $operation->resultAt(route('reports.show', $report));
}
```

- The monitor answers 200 with the status while the job is pending or running, 303 to the result when it has succeeded, 200 with an `error` problem when it has failed, and 404 for an unknown operation or one started by someone else.
- Status lives in the cache for `ttl` seconds (default 86400).
- The monitor route runs behind the `middleware` option (default `['api']`). Add your auth middleware there if operations must be private.

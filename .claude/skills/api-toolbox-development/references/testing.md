# Testing API Toolbox features

The package adds assertions to Laravel's `TestResponse`, and matching Pest expectations. They check the exact syntax each standard requires, not only that a header exists. Use them instead of `assertHeader()` or `assertJsonPath()` on these headers and problem members.

They are registered only while `APP_ENV=testing`, and only for enabled features. If an assertion is "not found", its feature is disabled in `config/api-toolbox.php`.

## Problem Details

```php
$this->postJson('/api/charges', ['amount' => 5000])
    ->assertProblemType('https://docs.example.com/problems/out-of-credit', 403)
    ->assertJsonPath('balance', 30);

$this->getJson('/api/orders/999')->assertProblem(404);

expect($this->getJson('/api/orders/999'))->toBeProblem(404);
```

For a type from the catalogue, compare with the constant: `->assertProblemType(ApiGuide::ACCOUNT_FROZEN)`. Validation errors are `->assertProblemType(ApiGuide::VALIDATION_FAILED, 422)->assertJsonValidationErrors(['email'])`.

## Conditional requests

```php
$etag = $this->getJson("/api/documents/{$document->id}")->assertETag(weak: false)->headers->get('ETag');

$this->getJson("/api/documents/{$document->id}", ['If-None-Match' => $etag])->assertNotModified();

$document->touch();

$this->patchJson("/api/documents/{$document->id}", ['title' => 'New'], ['If-Match' => $etag])->assertPreconditionFailed();

$this->patchJson("/api/documents/{$document->id}", ['title' => 'New'])->assertPreconditionRequired();
```

`assertCacheControl(['public', 'max-age' => '60'])` checks `Cache-Control` directives.

## Idempotency

```php
$first = $this->postJson('/api/charges', $payload, ['Idempotency-Key' => '"abc"']);
$retry = $this->postJson('/api/charges', $payload, ['Idempotency-Key' => '"abc"']);

$retry->assertIdempotentReplay($first);
expect(Charge::count())->toBe(1);
```

## Preferences and async operations

```php
// A 200 becomes 204; a 201 stays 201 with its Location, but with no body.
$this->patchJson("/api/items/{$item->id}", $data, ['Prefer' => 'return=minimal'])
    ->assertNoContent()
    ->assertPreferenceApplied('return', 'minimal');

Queue::fake();
$monitor = $this->postJson('/api/reports', $data, ['Prefer' => 'respond-async'])->assertOperationAccepted();
$this->getJson($monitor)->assertOk()->assertJsonPath('status', 'pending');
```

`assertOperationAccepted()` checks for a 202 with `Location` and `Retry-After`, and returns the monitor URL.

## Digests, signatures and Merge Patch

```php
$this->getJson('/api/ledger/1')->assertReprDigest();
$this->getJson('/api/files/1', ['Want-Content-Digest' => 'sha-256=10'])->assertContentDigest();
$this->patchJson('/api/posts/1', [], ['Content-Type' => 'application/json'])->assertStatus(415)->assertAcceptsPatch();
```

To test a `signature.verify` route, sign the request in the test with the package's `Signer`. The key must have a `secret` or `private_key` in the test configuration:

```php
use JustSteveKing\Toolbox\MessageSignatures\Messages\IncomingRequest;
use JustSteveKing\Toolbox\MessageSignatures\Signer;
use JustSteveKing\Toolbox\Support\ApiGuide;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

it('accepts a signed webhook', function () {
    $body = '{"event":"order.paid","id":42}';
    $digest = 'sha-256=:'.base64_encode(hash('sha256', $body, true)).':';

    $request = SymfonyRequest::create('http://localhost/webhooks/orders', 'POST', content: $body);
    $request->headers->set('Content-Type', 'application/json');
    $request->headers->set('Content-Digest', $digest);

    $fields = app(Signer::class)->sign(
        new IncomingRequest($request),
        'webhooks',
        ['@method', '@authority', '@path', 'content-digest', 'content-type'],
    )->fields();

    $this->call('POST', '/webhooks/orders', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_CONTENT_DIGEST' => $digest,
        'HTTP_SIGNATURE_INPUT' => $fields['Signature-Input'],
        'HTTP_SIGNATURE' => $fields['Signature'],
    ], content: $body)->assertOk();
});

it('refuses an unsigned webhook', function () {
    $this->postJson('/webhooks/orders', ['event' => 'order.paid'])
        ->assertProblemType(ApiGuide::UNAUTHORIZED, 401);
});
```

The URL signed must match the URL called, including the host (`localhost` in tests).

## Deprecation, links, catalog, negotiation and rate limits

```php
$this->getJson('/api/v1/orders')
    ->assertDeprecated('2026-06-30T23:59:59Z', 'https://docs.example.com/deprecations/orders-v1')
    ->assertSunset('2027-01-01');

$this->getJson('/api/v2/orders')->assertNotDeprecated();

$this->getJson('/api/posts')->assertLinkHeader('next')->assertNoLinkHeader('prev');

$this->get('/.well-known/api-catalog')->assertApiCatalog('https://example.com/api/v1');

$this->getJson('/api/orders', ['traceparent' => '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'])
    ->assertTraceparent('4bf92f3577b34da6a3ce929d0e0e4736')
    ->assertRequestId();

$this->getJson('/api/orders')->assertRateLimitHeaders('api', 59);
```

## Pest expectations

Each assertion has an expectation on a `TestResponse`: `toBeProblem`, `toHaveProblemType`, `toBeDeprecated`, `toHaveSunset`, `toHaveETag`, `toBeNotModified`, `toBePreconditionFailed`, `toBePreconditionRequired`, `toHaveCacheControl`, `toBeIdempotentReplayOf`, `toHaveRateLimitHeaders`, `toHaveTraceparent`, `toHaveRequestId`, `toHavePreferenceApplied`, `toHaveContentDigest`, `toHaveReprDigest`, `toHaveLink` and `toBeApiCatalog`.

# Message signatures and digests

## HTTP Message Signatures (RFC 9421)

Feature: `Features::messageSignatures([...])`. This is the standard way to sign webhooks and API requests. Use it instead of an HMAC in a custom header.

### Configure keys

Keys are configured by key ID. Keep secrets in `.env` and keys in files:

```php
Features::messageSignatures([
    'keys' => [
        'webhooks' => ['algorithm' => 'hmac-sha256', 'secret' => env('WEBHOOK_SECRET')],
        'partner' => ['algorithm' => 'ed25519', 'public_key' => storage_path('keys/partner.pem')],
        'server' => ['algorithm' => 'ecdsa-p256-sha256', 'private_key' => storage_path('keys/server.pem')],
    ],
    'verify' => [
        'required' => ['@method', '@authority', '@path'],
        'max_age' => 300,
    ],
    'sign' => ['key' => 'server'],
]),
```

- Algorithms: `hmac-sha256` (with `secret`; prefix with `base64:` for an encoded secret), `ed25519`, `ecdsa-p256-sha256`, `ecdsa-p384-sha384`, `rsa-v1_5-sha256` and `rsa-pss-sha512`.
- The algorithm belongs to the key. A request cannot choose it.
- Generate an Ed25519 key pair with `openssl genpkey -algorithm ed25519 -out private.pem` and `openssl pkey -in private.pem -pubout -out public.pem`.
- To load keys per client from a database or a JWKS endpoint, bind `JustSteveKing\Toolbox\MessageSignatures\Contracts\KeyResolver`.

### Sending a signed webhook

```php
use Illuminate\Support\Facades\Http;

Http::withSignature('webhooks')->post($endpoint, $payload);
```

This signs the method, the target URI, `Content-Type` and a `Content-Digest` of the body, and adds the digest for you. Deliver webhooks from a queued job, and put `$tries` and `$backoff` on the job for retries:

```php
final class SendWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public array $backoff = [10, 60, 300];

    public function __construct(public string $url, public array $payload) {}

    public function handle(): void
    {
        Http::withSignature('webhooks')->post($this->url, $this->payload)->throw();
    }
}
```

Other components, a label and parameters: `Http::withSignature('partner', ['@method', '@authority'], 'partner-sig', ['tag' => 'orders'])`.

### Verifying incoming requests

```php
use JustSteveKing\Toolbox\MessageSignatures\VerifiedSignature;

Route::post('webhooks/orders', HandleOrderWebhook::class)->middleware('signature.verify');

// In the handler:
$signer = VerifiedSignature::of($request)?->keyId;
```

- A request passes when one signature from a known key covers the required components, plus `content-digest` when there is a body, matches, matches the body, and is fresh: `created` no older than `max_age`.
- A failure is a 401 `unauthorized` problem carrying `Accept-Signature`. A malformed signature header is a 400.
- Nonce uniqueness is not tracked. A short `max_age` limits replay; track nonces yourself if a replay inside that window matters.
- Do not read the `Signature` header in the controller or verify an HMAC by hand.

### Signing responses

```php
Route::get('orders/{order}', ShowOrder::class)->middleware('signature.sign');        // the sign.key option
Route::get('reports/{report}', ShowReport::class)->middleware('signature.sign:server'); // a named key
```

### Moving from juststeveking/laravel-webhooks

Replace `Webhook::to($url)->with($payload)->send()` with `Http::withSignature('webhooks')->post($url, $payload)`, and queued sends with a queued job like the one above. Receivers that check the old `Signature` header must move to RFC 9421; `signature.verify` is that receiver.

## Digest Fields (RFC 9530)

Feature: `Features::digests()`. Integrity checks on bodies, with sha-256 or sha-512 only.

```php
Route::put('files/{file}', StoreFile::class)->middleware('digest.verify');             // check when sent
Route::put('ledger/{entry}', StoreEntry::class)->middleware('digest.verify:required');  // 400 without one
Route::get('files/{file}', ShowFile::class)->middleware('digest.respond');             // when the client asks
Route::get('ledger/{entry}', ShowEntry::class)->middleware('digest.respond:always');   // on every response
```

- A failure is a 400 typed `digest-mismatched-values`, `digest-invalid-values` or `digest-unsupported-algorithms` (`ApiGuide::DIGEST_MISMATCHED_VALUES` and the others).
- `digest.respond` answers `Want-Content-Digest` and `Want-Repr-Digest`. HEAD, 204, 304 and streamed responses get no digest.
- Message signatures already add and check `Content-Digest`. You do not need `digest.verify` on a `signature.verify` route.

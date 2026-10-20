# Problem Details (RFC 9457)

Feature: `Features::problemDetails()`. Errors are rendered as `application/problem+json` when the request expects JSON, or when its path matches the `paths` option (default `['api/*']`). Requests using `application/vnd.api+json` are left alone, because JSON:API has its own error format.

## What is rendered without any code

| Exception | Status | `type` |
|---|---|---|
| `ValidationException` | 422 | `https://apiguide.dev/errors/validation-failed`, with `errors` keyed by field |
| `AuthenticationException` | 401 | `https://apiguide.dev/errors/unauthorized`, plus a `WWW-Authenticate` challenge (`Bearer` by default) |
| `abort()`, `ModelNotFoundException`, authorization and any HTTP exception | its own | the apiguide.dev entry for the status (404 is `resource-not-found`), or `about:blank` |
| Anything else | 500 | `https://apiguide.dev/errors/internal-server-error`, with no message unless `app.debug` is on |

So `abort(404)`, `$request->validate()`, `Gate::authorize()` and `findOrFail()` need no extra work. Do not catch them to build a JSON error.

## Your own problem types

Generate the class. Do not write it by hand:

```bash
php artisan make:api-problem OutOfCredit --title="You do not have enough credit." --status=403 --no-interaction
php artisan make:api-problem Billing/CardDeclined --title="The card was declined." --status=402 --no-interaction
php artisan make:api-problem AccountFrozen --apiguide=account-frozen --no-interaction
```

- The class goes in `app/Problems`. `Billing/CardDeclined` makes `App\Problems\Billing\CardDeclined`.
- `--type` takes a slug or an absolute URI. It defaults to the class name in kebab case, resolved against the `type_base_uri` option.
- `--apiguide` sets the type, title and status from an apiguide.dev entry. It cannot be combined with `--title`, `--status` or `--type`.
- Under `--no-interaction` the command fails if the title or status is missing, because RFC 9457 section 4 requires both.

The result:

```php
use JustSteveKing\Toolbox\ProblemDetails\Attributes\ProblemType;
use JustSteveKing\Toolbox\ProblemDetails\ProblemException;

#[ProblemType('out-of-credit', title: 'You do not have enough credit.', status: 403)]
final class OutOfCredit extends ProblemException {}
```

Throw it with the details of this occurrence:

```php
throw new OutOfCredit(
    detail: 'Your current balance is 30, but that costs 50.',
    extensions: ['balance' => 30, 'accounts' => ['/account/12345']],
    instance: '/account/12345/msgs/abc',
);
```

- Type, title and status are fixed on the class; detail, extensions and instance vary per occurrence. Do not put changing text in the title.
- An extension key named `type`, `title`, `status`, `detail` or `instance` throws, because it would replace a standard member.
- `#[ProblemType]` can sit on an abstract parent and be inherited.
- With Negotiation on, `instance` defaults to the request ID as `urn:uuid:...`. A value you pass is kept.

## Using the apiguide.dev catalogue

`JustSteveKing\Toolbox\Support\ApiGuide` has a constant per catalogue entry, such as `ApiGuide::ACCOUNT_FROZEN`, `ApiGuide::QUOTA_EXCEEDED` and `ApiGuide::RESOURCE_NOT_FOUND`. `ApiGuide::types()` lists every type, and `ApiGuide::title($type)` and `ApiGuide::status($type)` give the documented title and status.

```php
use JustSteveKing\Toolbox\Support\ApiGuide;

#[ProblemType(ApiGuide::QUOTA_EXCEEDED, title: 'Quota Exceeded', status: 429)]
final class MonthlyQuotaExceeded extends ProblemException {}
```

Use a catalogue entry when it means what your error means. Otherwise give your own type a slug and point `type_base_uri` at your documentation.

## Mapping exceptions you do not own

Map them in `bootstrap/app.php` rather than catching them in controllers:

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->map(PaymentGatewayTimeout::class, fn (PaymentGatewayTimeout $e) => new UpstreamUnavailable(detail: 'The payment provider did not answer.'));
})
```

Your own `render` callbacks run before the package's, so the application can always override a response.

## Options

| Option | Default | Meaning |
|---|---|---|
| `type_base_uri` | `/problems` | Base for slug types |
| `paths` | `['api/*']` | Paths that always get problem details, whatever `Accept` says |
| `apiguide` | `true` | Type plain HTTP errors and validation from the catalogue; `false` uses `about:blank` |
| `www_authenticate` | `Bearer` | The challenge sent with a 401; `false` for none |
| `instance_from_request_id` | `true` | `instance` from the request ID |

To resolve types another way, bind `JustSteveKing\Toolbox\ProblemDetails\Contracts\TypeResolver`.

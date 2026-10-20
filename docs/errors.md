# Errors

Every error Kit returns is an [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) problem details
document, served as `application/problem+json`. api-toolbox renders them for every path under
`v1/`, whatever the request's `Accept` header asked for.

```json
{
  "type": "https://apiguide.dev/errors/not-found",
  "title": "Not Found",
  "status": 404,
  "detail": "The resource was not found.",
  "instance": "urn:uuid:0199e7f4-5d2a-7c3e-9b1a-4f2d8e6c1a07"
}
```

| Member | What it is | Who reads it |
| --- | --- | --- |
| `type` | A URI naming the kind of error, from the [apiguide.dev](https://apiguide.dev/errors) catalogue | Client code, to decide what to do |
| `title` | A short summary of the type. It never changes between occurrences or languages | Logs and dashboards |
| `status` | The HTTP status, repeated | Anything that lost the response line |
| `detail` | What went wrong this time, in the request's language | A person |
| `instance` | The request ID, the same value as the `X-Request-Id` header | Whoever is debugging it |

Tell your API's clients to branch on `type`, and never to match the text in `detail`. That text is
translated, and you will reword it one day. The type is the contract.

A validation failure is a `422` of type `https://apiguide.dev/errors/validation-failed`, with
Laravel's field messages kept under `errors`:

```json
{
  "type": "https://apiguide.dev/errors/validation-failed",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The email field is required.",
  "instance": "urn:uuid:...",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

## Raising errors

You never build error JSON yourself. Throw an exception and api-toolbox renders it.

For a plain HTTP error, the status picks the type:

```php
abort(404, __('api.auth.token_not_found'));
```

The message becomes `detail`. Pass it through `__()`, with a key in both `lang/en/api.php` and
`lang/es/api.php`.

Most validation belongs in the Form Request. When a rule needs something the Form Request cannot
see, throw from the controller instead:

```php
throw ValidationException::withMessages([
    'name' => __('api.projects.name_taken'),
]);
```

You do not need to do anything for authentication and authorisation failures, a missing model, a
bad signed URL or throttling. `bootstrap/app.php` maps each of them to a translated message from
`lang/*/api.php`, and api-toolbox renders the problem.

## Errors of your own

Sometimes a client has to tell one failure apart from others with the same status. Give that
failure its own type, and let api-toolbox generate the class:

```bash
php artisan make:api-problem OutOfCredit --title="You do not have enough credit." --status=403 --no-interaction
```

Or take the type from the apiguide.dev catalogue when one fits:

```bash
php artisan make:api-problem AccountFrozen --apiguide=account-frozen --no-interaction
```

Then throw it, with what happened this time in `detail` and any data the client needs in
`extensions`:

```php
throw new OutOfCredit(
    detail: __('api.billing.out_of_credit', ['balance' => 30, 'cost' => 50]),
    extensions: ['balance' => 30],
);
```

The title belongs to the class, so it reads the same every time. Extension keys cannot reuse a
standard member name such as `type` or `status`, and api-toolbox throws if you try.

Types you define resolve under `/problems`, set by `type_base_uri` in `config/api-toolbox.php`. Point
it at the part of your own documentation that describes them.

## Documenting errors

Each error status an endpoint can return needs a `#[Response]` attribute on its controller,
written as a problem. Kit documents those as `application/problem+json`, and legible fails the
build when an error status has no documented response. `make:endpoint` writes the common ones for
you, and [API documentation](api-documentation.md) has the rest.

## Testing errors

api-toolbox's assertions check the media type and the members as well as the status:

```php
$this->getJson('/v1/auth/me')
    ->assertProblem(401)
    ->assertJsonPath('detail', __('api.errors.unauthenticated'));

$response->assertProblemType('https://apiguide.dev/errors/idempotency-key-conflict', 422);
```

## Things not to do

Do not return `response()->json(['message' => ...], 400)`. It is not a problem document, and a
client has no reliable way to tell it from a success body.

Do not add render callbacks to `bootstrap/app.php` that return responses either. The callbacks
already there only write the security audit log and return `null`, which leaves the rendering to
api-toolbox. To change a framework exception's message, add a `map()` next to the existing ones.

And remember that `detail` goes to clients. Keep internals out of it, and think twice before
echoing user input back.

Translating `detail` is covered in [Localization](localization.md).

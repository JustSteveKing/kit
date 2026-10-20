# AGENTS.md

How to build on Kit without undoing what it sets up. Written for coding agents and for the people
working with them. Kit is a Laravel 13 API starter kit: Sanctum tokens, versioned routes, RFC 9457
errors and the other HTTP standards through [api-toolbox](https://github.com/JustSteveKing/api-toolbox),
Scribe docs checked by contract tests, and a dependency gate. The README describes what Kit does;
this file is how to change it.

## Before you call anything done

```bash
composer lint     # Pint, the PER preset plus the rules in pint.json
composer stan     # PHPStan with Larastan at level 10
php artisan test  # Pest, including the OpenAPI contract tests
```

CI runs all three. A change that fails any of them is not finished. Fix style with
`vendor/bin/pint --dirty`, never by loosening `pint.json` or `phpstan.neon`.

## Adding an endpoint

Every endpoint is the same six pieces. Copy the nearest existing one in
`app/Http/Controllers/Api/V1/Auth/` rather than starting from a blank file.

1. **Route** in `routes/api/v1.php`. URLs have no `/api` prefix: `routes/api/routes.php` adds `v1`.
   Name it `v1.{domain}.{action}`. Protected routes sit behind `auth:sanctum`, a named rate limiter
   and an ability:

   ```php
   Route::middleware(['auth:sanctum', 'throttle:projects'])->group(function (): void {
       Route::post('/projects', CreateProjectController::class)
           ->middleware(['abilities:projects:write', 'idempotent'])
           ->name('v1.projects.store');
   });
   ```

   Define the limiter in `AppServiceProvider::boot()` beside the existing ones. A new ability also
   goes into `sanctum.abilities.default` in `config/sanctum.php`, or no token issued at login can
   call the route.

2. **Form Request** in `app/Http/Requests/{Domain}/`: final, `authorize()` and `rules()` only.

3. **Payload** in `app/Http/Payloads/V1/`: a `final readonly` class with `fromRequest()` that reads
   the validated data with typed accessors (`Arr::string()`, `Arr::integer()`), never casts from
   `mixed`. Controllers work with the payload, not with `$request->input()`.

4. **Controller** in `app/Http/Controllers/Api/V1/{Domain}/`: final, invokable, no base class.
   Document it with Scribe attributes, not docblocks: `#[Group]`, `#[Endpoint]`, a `#[BodyParam]` or
   `#[QueryParam]` per input, `#[ResponseFromApiResource]` for success, and a `#[Response]` per error
   status written as problem details (see `LoginController` for the shape).

5. **Resource** in `app/Http/Resources/`: a plain `JsonResource`. Read the model through
   `/** @var Model $model */ $model = $this->resource;`, list every field explicitly, send IDs as
   strings and dates with `->toAtomString()`. No JSON:API: Kit removed it on purpose.

6. **Tests** in `tests/Feature/`, in Pest, using factories. Cover the success path, validation,
   authentication, the missing ability, and anything the endpoint does with the standards below. Add
   the endpoint to the runtime checks in `OpenApiContractTest` so its real responses are validated
   against the spec.

Then regenerate the docs and commit them with the change:

```bash
php artisan scribe:generate --no-interaction
```

`OpenApiContractTest` compares every `v1/` route with the generated spec, so a route Scribe cannot
see fails the build. Scribe documents a route even without attributes, though, so the attributes
are what make its documentation worth reading.

## Errors

Every API error is RFC 9457 problem details (`application/problem+json`), rendered by api-toolbox.
Do not build error JSON by hand, and do not add render callbacks to `bootstrap/app.php` that return
responses: the ones there only write the security audit log.

- A plain HTTP error: `abort(404, __('api.projects.not_found'))`. The message becomes `detail`; the
  status picks the apiguide.dev `type`.
- Validation: let the Form Request fail, or throw `ValidationException::withMessages()`.
- An error clients need to tell apart: `php artisan make:api-problem` creates a typed problem class
  (`--apiguide=<entry>` takes the type from the catalogue). Throw it.

In tests, assert problems with `assertProblem($status)` and `assertProblemType($type, $status)`, and
the message with `assertJsonPath('detail', __('api....'))`.

## HTTP standards: reach for the toolbox

api-toolbox is configured in `config/api-toolbox.php`. Before hand-rolling any of these, use it:

| Need | Use |
| --- | --- |
| A retry must not repeat a write | `idempotent` middleware, `idempotent:required` to insist on the key |
| A list endpoint | `CursorPage::of($query->cursorPaginate($perPage), Resource::class)`, never offset pages |
| Cheap re-fetches of a resource | `conditional` middleware (ETag, `304`) |
| Stop lost updates | the model implements `HasValidators`, the write route adds `preconditions.required` |
| A `PATCH` | `merge-patch` middleware and `application/merge-patch+json` |
| Retire an endpoint | `#[Deprecation]` and `#[Sunset]` on the controller class; Kit answers `410` after the sunset date |
| Work that outlives the request | Async operations; switched off in the config, and turning it on adds a route to document |
| Signed webhooks | Message signatures; keys go in the config, never in code |

Deprecation attributes are read from controller classes only, not closures. Write routes accept
`application/json` only, because `consumes:application/json` sits on the whole `v1` group. A route
that takes another type, such as an upload, has to lift the group's declaration as well as add its
own, or the group refuses the request first:

```php
Route::post('/imports', CreateImportController::class)
    ->withoutMiddleware('consumes:application/json')
    ->middleware('consumes:multipart/form-data');
```

The `api-toolbox-development` Boost skill has the detail for each of these.

## Localization

Responses follow `Accept-Language`, limited to `APP_SUPPORTED_LOCALES` (`en,es`). Every message a
client can read goes through `__()`:

- Kit's own messages: `lang/en/api.php` and `lang/es/api.php`. Add every key to both; a test fails
  when Spanish lacks one.
- Problems api-toolbox raises: `lang/vendor/api-toolbox/es/problems.php`, also checked by a test after
  each package upgrade.
- Problem `type` and `title` never change with the language. Clients branch on `type`.

## Security

- Call `SecurityAudit::log('domain.action.outcome', [...])` for anything security-relevant: sign-in,
  tokens, permission changes, destructive actions. Log IDs and `SecurityAudit::hashEmail()`, never
  raw emails, passwords or tokens.
- Grant each token the least it needs: one ability per endpoint group, added to the defaults only if
  every client needs it.
- `ProductionSecurityChecks` refuses to boot in production without HTTPS enforcement, a `https://`
  `APP_URL`, `APP_DEBUG=false`, explicit CORS origins and trusted hosts. Configure them; do not
  remove the check.

## Code

- `declare(strict_types=1)` and `final` everywhere; constructor promotion; explicit return types.
- PHPStan runs at level 10, so `mixed` must be narrowed rather than cast: `config()->string()`,
  `config()->integer()`, `$request->string('field')`, `Arr::string()`, typed model properties. A
  `@phpstan-ignore` needs the identifier and a comment saying why.
- Pint enforces Yoda comparisons, `! $x` with a space, and alphabetical imports. Run it rather than
  matching it by hand.
- User IDs are ULIDs, so treat them as strings.

## Dependencies

[Laravel Vet](https://github.com/laravel/vet) guards `composer install`: `vet.json` records every
package someone has read, and releases younger than 7 days are held back.

- Do not add or upgrade a package without being asked to.
- After `composer require` or `composer update`, run `./vendor/bin/vet` in a terminal, review each
  change (or have it handed to you to read), and commit `vet.json` with `composer.lock`.
- Never edit `vet.json` by hand, and never use `--no-plugins` to get past Vet. The one exception is
  the documented review of a bot PR, which installs the new lock without the plugin precisely so
  that Vet can audit it.

## Generated files

- `CLAUDE.md`: the text between `<laravel-boost-guidelines>` tags is rewritten by
  `php artisan boost:update`. Edit this file instead; `CLAUDE.md` imports it.
- `public/docs/` and `.scribe/`: written by `scribe:generate`, which the test suite also runs. Commit
  them when an endpoint or its documentation changed, not for timestamp churn after a test run.

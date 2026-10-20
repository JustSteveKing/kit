# Kit

Opinionated Laravel API starter kit for token-based authentication, with strong defaults around
API design, HTTP standards, documentation, testing and security.

## Highlights

- PHP `8.5` + Laravel `13`
- SQLite-first local development (`DB_CONNECTION=sqlite`)
- Sanctum personal access token authentication
- Versioned API routes with no `/api` prefix (`/v1/...`)
- Invokable controllers only
- Form Request validation + DTO-style request payload objects
- Standards-compliant HTTP through [api-toolbox](https://github.com/JustSteveKing/api-toolbox):
  - Every error is [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) problem details, typed from
    the [apiguide.dev](https://apiguide.dev/errors) error catalogue
  - `Idempotency-Key` replay and conflict handling on critical writes
  - `Deprecation` and `Sunset` headers from controller attributes, and `410 Gone` after sunset
  - Cursor pagination with RFC 8288 `Link` headers
  - ETags and `304 Not Modified`
  - Request IDs and W3C trace context
  - An RFC 9727 API catalog at `/.well-known/api-catalog`
- API localization via `Accept-Language` + `Content-Language`
- Scribe (attribute-based) API docs + OpenAPI generation
- OpenAPI contract tests to keep docs and runtime behaviour in sync
- GitHub Actions for style, static analysis, tests, security audits and weekly, Vet-audited dependency update PRs
- An [`AGENTS.md`](AGENTS.md) that tells coding agents, and you, how to build on Kit the way it is built

## Tech Stack

- Laravel Framework: `^13.30`
- PHP: `^8.5`
- Auth: `laravel/sanctum`
- HTTP standards: `juststeveking/api-toolbox`
- Docs/OpenAPI: `knuckleswtf/scribe` (attributes, not docblocks)
- Test Runner: Pest + Laravel test tooling
- Static Analysis / Quality: PHPStan (Larastan, level 10), Pint

## Quick Start

### 1) Start your project

Kit is a starting point rather than a package, so take a copy and give it its own history:

```bash
git clone https://github.com/JustSteveKing/kit.git my-api
cd my-api
rm -rf .git && git init
```

### 2) Install dependencies

```bash
composer install
```

### 3) Bootstrap environment

```bash
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

Or run the bundled setup script:

```bash
composer run setup
```

### 4) Run the API

```bash
php artisan serve
```

API base path is versioned and has no global `/api` prefix:

- `http://127.0.0.1:8000/v1/...`

## API Routing

Routing is intentionally split:

- `routes/api/routes.php` for top-level API version grouping
- `routes/api/v1.php` for V1 endpoint declarations

Framework routing is configured with `apiPrefix: ''` in `bootstrap/app.php`, so your URLs stay clean.

## Auth Endpoints (V1)

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| POST | `/v1/auth/register` | No | Register and issue token |
| POST | `/v1/auth/login` | No | Login and issue token |
| GET | `/v1/auth/me` | Bearer | Current authenticated user |
| POST | `/v1/auth/logout` | Bearer | Revoke current token |
| GET | `/v1/auth/tokens` | Bearer | List the user's tokens, cursor paginated |
| DELETE | `/v1/auth/tokens` | Bearer | Revoke every token |
| DELETE | `/v1/auth/tokens/{token_id}` | Bearer | Revoke one token |
| POST | `/v1/auth/email/verification-notification` | Bearer | Send/resend verification email |
| GET | `/v1/auth/email/verify/{id}/{hash}` | Signed URL | Verify email |
| POST | `/v1/auth/password/forgot` | No | Request reset email (anti-enumeration response) |
| GET | `/v1/auth/password/reset/{token}` | No | Return reset payload for API clients |
| POST | `/v1/auth/password/reset` | No | Reset password |

## First Requests (cURL)

Register:

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "Password123!",
    "device_name": "cli"
  }'
```

Login:

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "jane@example.com",
    "password": "Password123!",
    "device_name": "cli"
  }'
```

Use token on protected route:

```bash
curl http://127.0.0.1:8000/v1/auth/me \
  -H "Accept: application/json" \
  -H "Authorization: Bearer <TOKEN>"
```

Localized error (Spanish):

```bash
curl -i http://127.0.0.1:8000/v1/auth/me \
  -H "Accept-Language: es"
```

```http
HTTP/1.1 401 Unauthorized
Content-Type: application/problem+json
Content-Language: es
WWW-Authenticate: Bearer

{"type":"https://apiguide.dev/errors/unauthorized","title":"Unauthorized","status":401,"detail":"No autenticado.","instance":"urn:uuid:0199e7f4-5d2a-7c3e-9b1a-4f2d8e6c1a07"}
```

## Response Design

- Resources are plain Laravel API resources (`application/json`), wrapped in `data`
- Errors are RFC 9457 problem details (`application/problem+json`), rendered by api-toolbox:
  - `type` is an apiguide.dev URI that identifies the error, so clients branch on it rather than
    on message text
  - `detail` is the human-readable message, translated where Kit raised it
  - validation errors keep Laravel's field messages under `errors`
  - `instance` is the request ID, so a reported error leads straight to its log lines
- To raise a typed error of your own, see
  [api-toolbox's Problem Details docs](https://github.com/JustSteveKing/api-toolbox#problem-details)
  (`php artisan make:api-problem`)
- Write endpoints only accept `application/json` bodies (`consumes:application/json`); anything else
  is a `415` that names the supported type

## Localization

Locale resolution is API-first:

- Middleware reads `Accept-Language`
- Locale is resolved against `APP_SUPPORTED_LOCALES`
- Response includes `Content-Language`
- Unsupported locales fall back to `APP_FALLBACK_LOCALE`

Every problem's `detail` is translated; its `type` and `title` stay the same in every language,
so clients key on the `type`:

- Messages Kit raises itself, and Laravel's validation messages: `lang/{en,es}/api.php` and
  `lang/{en,es}/validation.php`
- Problems api-toolbox raises, such as an idempotency conflict or an unsupported media type:
  English ships with the package, Spanish is in `lang/vendor/api-toolbox/es/problems.php`. A test
  fails if a package upgrade adds a line the Spanish file does not have

To add a language, add it to `APP_SUPPORTED_LOCALES` and give it each of those files.

Relevant config/env:

- `APP_LOCALE`
- `APP_FALLBACK_LOCALE`
- `APP_SUPPORTED_LOCALES` (default: `en,es`)
- `SANCTUM_EXPIRATION` (default: `120` minutes)

## Architecture Conventions

- Controllers are invokable, final, and do not extend a base controller
  (`app/Http/Controllers/Api/V1/{Domain}`)
- Request validation lives in Form Requests (`app/Http/Requests/{Domain}`)
- DTO payload objects live in `app/Http/Payloads/V1` (final, readonly)
- Entity output lives in plain API resources (`app/Http/Resources`)
- HTTP standards are switched on in `config/api-toolbox.php`, one feature per standard

[AGENTS.md](AGENTS.md#adding-an-endpoint) walks through adding an endpoint end to end: route,
ability and rate limit, Form Request, payload, controller with its Scribe attributes, resource,
tests, and regenerated docs.

## Security Defaults

- ULID primary keys for users
- Password hashing via model casts
- Email verification required model contract (`MustVerifyEmail`)
- Rate limits configured in `AppServiceProvider`:
  - `auth-register`: 10/minute per IP
  - `auth-login`: 10/minute per IP + email
  - `auth-password`: 5/minute per IP + email
  - `auth-protected`: 60/minute per authenticated user
- Verification endpoints use signed URLs and throttling
- Write endpoints enforce JSON payloads (`application/json`)
- API responses include baseline hardening headers (`nosniff`, `DENY`, `no-referrer`)
- Every request gets an `X-Request-Id` (kept from a proxy when well-formed, otherwise a UUIDv7),
  and joins or starts a W3C trace (`traceparent`); both land in the log context
- Security-sensitive auth/token actions emit structured `security.audit` log events
- Configurable transport hardening for HTTPS enforcement, HSTS, trusted proxies/hosts, and strict CORS origins
- Dependencies are audited with [Laravel Vet](https://github.com/laravel/vet): nothing is installed
  until someone has read it, and no release younger than 7 days is installed at all

## Dependency Audit (Vet)

`composer audit` only knows about published advisories, so a freshly compromised release passes
it. [Laravel Vet](https://github.com/laravel/vet) (beta) covers that gap as a Composer plugin:

- `vet.json` records the version and file hash of every package someone has read. `composer
  install` fails on anything else, including the same version re-published with different files.
- `minimum-release-age: 7` keeps every release younger than a week out of `composer update`, so a
  bad release has time to be found and pulled. `juststeveking/*` and `roave/security-advisories`
  are excluded: the first are first-party, the second has to be current to be any use.

After `composer update`, review what changed and record what you trust:

```bash
./vendor/bin/vet        # in a terminal: read each change, or hand them to your coding agent
```

Commit `vet.json` with `composer.lock`. If you build your own project from Kit, `vet.json` is the
trust Kit recorded; run `./vendor/bin/vet --fresh` to take it over as your own, or remove
`laravel/vet` if you do not want the gate.

## Idempotency

`POST /v1/auth/register` and `POST /v1/auth/password/forgot` honour an `Idempotency-Key` header,
following [draft-ietf-httpapi-idempotency-key-header](https://datatracker.ietf.org/doc/draft-ietf-httpapi-idempotency-key-header/):

| Situation | Response |
| --- | --- |
| Retry with the same key and payload | The original response, replayed without running again |
| Same key, different payload | `422`, `https://apiguide.dev/errors/idempotency-key-conflict` |
| Retry while the first request is still running | `409` with `Retry-After` |
| Malformed key | `400` |

Keys are scoped to the user or client address and kept for `IDEMPOTENCY_TTL` seconds (default
`86400`). Add `->middleware('idempotent')` to other writes, or `idempotent:required` to make the key
mandatory.

## Deprecation and Sunset

Mark a controller, or one action, with api-toolbox's attributes:

```php
use JustSteveKing\Toolbox\Deprecation\Attributes\Deprecation;
use JustSteveKing\Toolbox\Deprecation\Attributes\Sunset;

#[Deprecation(at: '2030-01-01T00:00:00Z', link: 'https://docs.example.com/deprecations/login-v1')]
#[Sunset(at: '2030-07-01T00:00:00Z', link: 'https://docs.example.com/sunset-policy')]
final class LoginController
{
    // ...
}
```

Behaviour:

- Adds `Deprecation` ([RFC 9745](https://www.rfc-editor.org/rfc/rfc9745)) and `Sunset`
  ([RFC 8594](https://www.rfc-editor.org/rfc/rfc8594)) headers, with `rel="deprecation"` and
  `rel="sunset"` links
- Once the sunset date has passed, `RetireSunsetEndpoints` answers `410 Gone` as problem details,
  still carrying both headers

## Pagination and Caching

- `GET /v1/auth/tokens` returns a page of tokens as the body, with navigation in `Link` headers
  (`rel="next"`, `rel="prev"`, `rel="first"`). A cursor that cannot be decoded is a `400`
- `GET /v1/auth/me` sends an `ETag`; repeat the request with `If-None-Match` and an unchanged user
  gets `304 Not Modified`

## API Documentation (Scribe + OpenAPI)

Scribe is configured for this no-prefix API shape:

- Route matching uses `v1/*` prefixes (`config/scribe.php`)
- Endpoints are documented via PHP attributes
- Error responses are documented as `application/problem+json` (`App\Support\ProblemDetailsOpenApiGenerator`)
- OpenAPI output is generated to `public/docs/openapi.yaml`, and advertised from the API catalog at
  `/.well-known/api-catalog`

Generate docs/spec:

```bash
php artisan scribe:generate --no-interaction
```

Generated artifacts:

- `public/docs/index.html`
- `public/docs/openapi.yaml`
- `public/docs/collection.json`

## Testing & Quality

Run test suite:

```bash
php artisan test
```

Or use composer script:

```bash
composer test
```

Other quality commands:

```bash
composer lint
composer stan
```

Feature tests include:

- Token/auth flows
- Email verification and password reset workflows
- Security and unhappy-path scenarios
- Localization behaviour
- Idempotency, deprecation, pagination and conditional requests
- OpenAPI generation and contract verification: every `v1/` route must be in the spec, and each
  runtime check's status, body and media type must match it

api-toolbox's assertions (`assertProblem`, `assertIdempotentReplay`, `assertDeprecated`,
`assertLinkHeader` and others) are available on every test response.

## AI Agents

[`AGENTS.md`](AGENTS.md) is the guide to building on Kit: the checks that must pass, the recipe for
an endpoint, how errors, the HTTP standards, localization, security and dependencies work here, and
which files are generated. Coding agents that follow the AGENTS.md convention read it directly;
`CLAUDE.md` imports it for Claude Code.

Below that, `CLAUDE.md` carries the [Laravel Boost](https://laravel.com/docs/boost) guidelines, with
api-toolbox's guideline and `api-toolbox-development` skill opted in through `boost.json`. Run
`php artisan boost:update` after upgrading packages; it rewrites only its tagged block. To add Boost
for another agent, run `php artisan boost:install` and pick it: agents that read `AGENTS.md` get
Boost's block appended below Kit's guide, which it leaves alone.

## CI & Dependency Automation

GitHub Actions workflows:

- `.github/workflows/ci-tests.yml`
  - Runs Pint, PHPStan and the test suite on every push and pull request
- `.github/workflows/dependency-updates.yml`
  - Runs weekly, Mondays at `03:00 UTC`
  - Executes `composer update`, holding back releases younger than 7 days
  - Opens/updates PR titled `bot: dependency updates` with Vet's report of what needs review
  - The PR cannot pass CI until `vet.json` is updated on its branch: check it out, run
    `composer install --no-plugins`, then `./vendor/bin/vet`, and commit `vet.json`
- `.github/workflows/security-gate.yml`
  - Runs Composer security audit
  - Fails on high/critical advisories
  - Runs repository secret scan with Gitleaks

## Project Structure

```text
AGENTS.md          how to build on Kit, for agents and people
vet.json           packages trusted by Laravel Vet
app/
  Http/
    Controllers/Api/V1/Auth/
    Middleware/
    Payloads/V1/
    Requests/Auth/
    Resources/
  Support/
routes/
  api/
    routes.php
    v1.php
tests/
  Feature/
config/
  api-toolbox.php
  sanctum.php
  scribe.php
  security.php
lang/
  en/, es/                   Kit's messages and validation
  vendor/api-toolbox/es/     Spanish for api-toolbox's problems
.github/
  workflows/
```

## License

MIT

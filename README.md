# Kit

Opinionated Laravel API starter kit for token-based authentication with strong defaults around API design, documentation, testing, and security.

## Highlights

- PHP `8.5` + Laravel `12`
- SQLite-first local development (`DB_CONNECTION=sqlite`)
- Sanctum personal access token authentication
- Versioned API routes with no `/api` prefix (`/v1/...`)
- Invokable controllers only
- Form Request validation + DTO-style request payload objects
- JSON:API resources for entity responses
- API localization via `Accept-Language` + `Content-Language`
- Scribe (attribute-based) API docs + OpenAPI generation
- OpenAPI contract tests to keep docs and runtime behavior in sync
- Sunset middleware to deprecate and retire endpoints safely
- GitHub Actions for CI tests and daily dependency update PRs

## Tech Stack

- Laravel Framework: `^12.0`
- PHP: `^8.5`
- Auth: `laravel/sanctum`
- Docs/OpenAPI: `knuckleswtf/scribe` (attributes, not docblocks)
- Test Runner: Pest + Laravel test tooling
- Static Analysis / Quality: PHPStan (Larastan), Pint, Rector

## Quick Start

### 1) Install dependencies

```bash
composer install
```

### 2) Bootstrap environment

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

### 3) Run the API

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

Localized response (Spanish):

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/password/forgot \
  -H "Accept: application/json" \
  -H "Accept-Language: es" \
  -H "Content-Type: application/json" \
  -d '{"email":"unknown@example.com"}'
```

## Response Design

- `UserResource` uses Laravel JSON:API resource format (`application/vnd.api+json`)
- Message/error JSON responses use `new JsonResponse([...])` for explicitness
- Validation and auth errors are normalized in global exception renderers
- API message strings are translated via `lang/en/api.php` and `lang/es/api.php`

## Localization

Locale resolution is API-first:

- Middleware reads `Accept-Language`
- Locale is resolved against `APP_SUPPORTED_LOCALES`
- Response includes `Content-Language`
- Unsupported locales fall back to `APP_FALLBACK_LOCALE`

Relevant config/env:

- `APP_LOCALE`
- `APP_FALLBACK_LOCALE`
- `APP_SUPPORTED_LOCALES` (default: `en,es`)
- `SANCTUM_EXPIRATION` (default: `120` minutes)

## Architecture Conventions

- Controllers are invokable and do not extend a base controller
- Request validation lives in `app/Http/Requests/Auth`
- DTO payload objects live in `app/Http/Payloads/V1` (final, readonly)
- Entity output lives in API resources (`app/Http/Resources`)

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
- API responses include an `X-Request-Id` header (propagated or generated)
- Security-sensitive auth/token actions emit structured `security.audit` log events
- Critical write endpoints support `Idempotency-Key` replay/conflict handling
- Configurable transport hardening for HTTPS enforcement, HSTS, trusted proxies/hosts, and strict CORS origins

## Sunset Middleware (Endpoint Deprecation)

`App\Http\Middleware\Sunset` adds deprecation metadata and can enforce retirement.

Usage:

```php
Route::middleware('sunset:2030-01-01,https://api.example.com/v2/auth/login,true')
    ->post('/v1/auth/login', LoginController::class);
```

Behavior:

- Adds `Deprecation` and `Sunset` headers
- Adds `Link: <...>; rel="successor-version"` when successor URL is valid
- Can return `410 Gone` after sunset date when enforcement is enabled

## API Documentation (Scribe + OpenAPI)

Scribe is configured for this no-prefix API shape:

- Route matching uses `v1/*` prefixes (`config/scribe.php`)
- Endpoints are documented via PHP attributes
- OpenAPI output is generated to `public/docs/openapi.yaml`

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
- Localization behavior
- Sunset middleware behavior
- OpenAPI generation and contract verification

## CI & Dependency Automation

GitHub Actions workflows:

- `.github/workflows/ci-tests.yml`
  - Runs tests on every push and pull request
- `.github/workflows/dependency-updates.yml`
  - Runs daily at `03:00 UTC`
  - Executes `composer update`
  - Opens/updates PR titled `bot: dependency updates`
- `.github/workflows/security-gate.yml`
  - Runs Composer security audit
  - Fails on high/critical advisories
  - Runs repository secret scan with Gitleaks

## Project Structure

```text
app/
  Http/
    Controllers/Api/V1/Auth/
    Middleware/
    Payloads/V1/
    Requests/Auth/
    Resources/
routes/
  api/
    routes.php
    v1.php
tests/
  Feature/
config/
  sanctum.php
  scribe.php
.github/
  workflows/
```

## License

MIT

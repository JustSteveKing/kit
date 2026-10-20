# Kit

Opinionated Laravel API starter kit for token-based authentication, with strong defaults around
API design, HTTP standards, documentation, testing and security.

## Highlights

- PHP `8.5` + Laravel `13`
- SQLite-first local development (`DB_CONNECTION=sqlite`)
- Sanctum token authentication: session tokens on sign-in, and scoped, long-lived API keys for
  scripts, integrations and agents
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
- OpenAPI contract tests to keep docs and runtime behaviour in sync, and a
  [legible](https://github.com/JustSteveKing/legible) gate that keeps the spec agent-ready
- `php artisan make:endpoint` scaffolds an endpoint the way the rest of Kit is built
- A production image: Laravel Octane on FrankenPHP, with the same image as the queue worker
- Outbound webhooks: consumers subscribe their own endpoints; deliveries are CloudEvents, signed
  (RFC 9421), retried on a published schedule, recorded attempt by attempt, and can be redelivered
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
php artisan queue:work   # in a second terminal: verification and reset emails are queued
```

Mail goes to the log (`MAIL_MAILER=log`) until you configure a mailer.

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
| POST | `/v1/auth/tokens` | Bearer | Create an API key with chosen abilities |
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
- `SANCTUM_EXPIRATION`: session token lifetime (default: `120` minutes)
- `API_KEY_DEFAULT_DAYS` / `API_KEY_MAX_DAYS`: API key lifetime (defaults: `90` / `365` days)

## Architecture Conventions

- Controllers are invokable, final, and do not extend a base controller
  (`app/Http/Controllers/Api/V1/{Domain}`)
- Request validation lives in Form Requests (`app/Http/Requests/{Domain}`)
- DTO payload objects live in `app/Http/Payloads/V1` (final, readonly)
- Entity output lives in plain API resources (`app/Http/Resources`)
- HTTP standards are switched on in `config/api-toolbox.php`, one feature per standard

## Adding Endpoints

```bash
php artisan make:endpoint Projects/CreateProject --method=POST --uri=/projects
```

The generator writes the controller with its Scribe attributes and documented problems, the Form
Request, the payload, a Pest test whose authentication and ability cases pass straight away, and
the route with auth, rate limit, ability and name (plus `idempotent` on a POST). It leaves what it
cannot know as TODO: the controller answers `501`, and CI fails while the documentation still says
TODO, so an endpoint cannot ship half written. Change what it generates in `stubs/endpoint/`.

[AGENTS.md](AGENTS.md#adding-an-endpoint) covers each piece and the steps the generator prints for
you: the ability in `config/sanctum.php`, the contract test's runtime check, and the docs.

## API Keys

Sign-in gives a session token with the default abilities. For a script, an integration or an
agent, create an API key instead, from any token that holds `auth:tokens:create`:

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/tokens \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -d '{"name": "deploy-bot", "abilities": ["auth:me"], "expires_at": "2027-01-01T00:00:00Z"}'
```

- A key can only hold abilities the token creating it holds, out of those a route checks
  (`sanctum.abilities.available`), so a narrow key can never mint a broader one.
- It expires on `expires_at`, at most `API_KEY_MAX_DAYS` away, or after `API_KEY_DEFAULT_DAYS`.
- `meta.token` in the response is the only time its value is returned. Revoke it like any token.
- Send an `Idempotency-Key` and a retried request returns the same key rather than a second one.

## Security Defaults

- ULID primary keys for users
- Password hashing via model casts
- Email verification required model contract (`MustVerifyEmail`)
- Every token carries its own expiry: session tokens after `SANCTUM_EXPIRATION` minutes, API keys
  on the date they were given
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

Laravel's own packages (`laravel/*`) are trusted by policy: they skip the 7-day hold, and when
they are the only packages an update changed, `./vendor/bin/vet --fresh` records them without a
review. The weekly bot does this itself, so a Laravel-only update PR needs no review.

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

## Webhooks

API consumers subscribe their own https endpoints to events, behind the `webhooks:read` and
`webhooks:write` abilities:

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/v1/webhooks` | List subscriptions |
| POST | `/v1/webhooks` | Subscribe a URL to events; the secret is returned once |
| GET | `/v1/webhooks/{webhook}` | Show a subscription, and why it was disabled if it was |
| PATCH | `/v1/webhooks/{webhook}` | Change the URL, events or description, or enable and disable it |
| DELETE | `/v1/webhooks/{webhook}` | Delete a subscription and its history |
| POST | `/v1/webhooks/{webhook}/secret` | Rotate the signing secret |
| POST | `/v1/webhooks/{webhook}/ping` | Send a `webhook.ping` to test the endpoint |
| POST | `/v1/webhooks/{webhook}/validate` | Ask the endpoint for consent again (when validation is on) |
| GET, POST | `/v1/webhooks/{webhook}/consent` | The signed consent link sent to the endpoint; no token needed |
| GET | `/v1/webhooks/{webhook}/deliveries` | Deliveries, each with every attempt |
| POST | `/v1/webhooks/{webhook}/deliveries/{delivery}/redeliver` | Send an event again |

Every delivery is a [CloudEvents 1.0](https://github.com/cloudevents/spec) event in structured
content mode, POSTed as `application/cloudevents+json`:

```json
{
  "specversion": "1.0",
  "id": "01jb8x3a1b2c3d4e5f6g7h8j9k",
  "source": "https://api.example.com/v1",
  "type": "project.created",
  "subject": "01jb8x2m4k5n6p7q8r9s0t1v2w",
  "time": "2026-10-20T12:01:00+00:00",
  "datacontenttype": "application/json",
  "data": {}
}
```

`source` is `WEBHOOKS_SOURCE` (default `APP_URL/v1`), `subject` is what the event is about and is
left out when there is none, and any CloudEvents SDK can parse the body. Delivery follows the
[CloudEvents webhook specification](https://github.com/cloudevents/spec/blob/main/cloudevents/http-webhook.md):
https only, redirects never followed, and every request carries `WebHook-Request-Origin`
(`WEBHOOKS_ORIGIN`, default `APP_URL`'s host).

- **Signed** with [RFC 9421](https://www.rfc-editor.org/rfc/rfc9421) HTTP Message Signatures
  (`Signature`, `Signature-Input`, with `keyid` set to the subscription ID), using HMAC-SHA256 and
  the subscription's secret, over the method, URI, `Content-Type` and a `Content-Digest` of the
  body. A Laravel receiver with api-toolbox verifies it with the `signature.verify` middleware.
- **Deduplicable**: the event ID is sent as `Idempotency-Key`, and is the same on every retry and
  redelivery.
- **Retried** after 10s, 1m, 5m, 30m, 2h and 6h (`webhooks.backoff`), then marked failed. Any 2xx is
  success. A `429` is retried no sooner than its `Retry-After`; a `410 Gone` stops deliveries and
  disables the subscription; a `415` fails without retrying, since the format will not change. A
  subscription is disabled, with a reason, after 10 deliveries in a row fail
  (`WEBHOOKS_DISABLE_AFTER`); re-enable it with `PATCH {"enabled": true}`.
- **Consented to**, when `WEBHOOKS_VALIDATE_ENDPOINTS=true`: a new or moved subscription receives
  nothing until its endpoint answers an `OPTIONS` request carrying `WebHook-Request-Origin` with a
  matching `WebHook-Allowed-Origin` (or `*`), the CloudEvents handshake. It proves the URL's owner
  wants the events. Off by default, since every receiver then has to implement it. A receiver that
  cannot answer straight away, or whose administrator finds the request in a log, consents later
  through the `WebHook-Request-Callback` URL sent with it: a signed link, valid for
  `WEBHOOKS_CALLBACK_HOURS` (72), opened with GET or POSTed to, and refused if the subscription's URL
  has changed since.
- **Rate-limited as the endpoint asks**: an endpoint grants a rate with `WebHook-Allowed-Rate`
  (deliveries a minute, or `*` for none), in its handshake response or its consent callback, and
  kit stays within it; a delivery over the rate waits, without spending a retry. Set
  `WEBHOOKS_REQUEST_RATE` to ask for a rate with `WebHook-Request-Rate`; that rate applies when an
  endpoint consents without granting one. Limiting needs a queue that can delay, not `sync`.
- **Recorded**: each attempt keeps its status, a response excerpt, its duration and any error. A
  `null` status means no response arrived, as distinct from an error status.
- **Safe to point at**: URLs must be https and must not resolve to a private, loopback or reserved
  address, checked on subscribe and on every send. Loosen this locally with
  `WEBHOOKS_REQUIRE_HTTPS=false` and `WEBHOOKS_ALLOW_PRIVATE_NETWORKS=true`.
- **Pruned**: events and their deliveries are kept for 30 days (`WEBHOOKS_RETENTION_DAYS`), by a
  daily `model:prune`, so run the scheduler (`php artisan schedule:work`) as well as a queue worker.

Kit ships the machinery and the ping; [AGENTS.md](AGENTS.md#webhooks) shows how to add your own
events.

## Deployment

The `Dockerfile` builds a production image: Laravel Octane on FrankenPHP, PHP 8.5, running as a
non-root user on port 8000, with OPcache tuned for production and a health check on `/up`.

```bash
docker build -t my-api .
docker run -p 8000:8000 --env-file .env.production my-api                       # the API
docker run --env-file .env.production my-api php artisan queue:work             # a queue worker
docker run --env-file .env.production my-api php artisan schedule:work          # the scheduler
```

At start the container caches config, routes and events from its environment. Production refuses
to boot without `APP_KEY`, an `https://` `APP_URL`, `SECURITY_FORCE_HTTPS=true`, `TRUSTED_HOSTS`
and explicit `CORS_ALLOWED_ORIGINS`; put TLS in front of it and forward `X-Forwarded-Proto`. Run
migrations as a release step (`php artisan migrate --force`). CI builds the image and boots it on
every push.

## API Documentation (Scribe + OpenAPI)

Scribe is configured for this no-prefix API shape:

- Route matching uses `v1/*` prefixes (`config/scribe.php`)
- Endpoints are documented via PHP attributes
- Error responses are documented as `application/problem+json` (`App\Support\ProblemDetailsOpenApiGenerator`),
  and `204`s with no content (`App\Support\NoContentOpenApiGenerator`)
- Path parameters keep the names the routes give them, rather than Scribe's guessed `{id}`
- OpenAPI output is generated to `public/docs/openapi.yaml`, and advertised from the API catalog at
  `/.well-known/api-catalog`

Generate docs/spec:

```bash
php artisan scribe:generate --no-interaction
```

[legible](https://github.com/JustSteveKing/legible) scores the spec for agent readiness: what will
trip up an LLM calling the API as tools. `.legible.yaml` fails CI on any warning or a score under
95, and lists the findings accepted on purpose with the reason.

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
  - Runs Pint, PHPStan, the test suite and legible on every push and pull request
  - Builds the production image, boots it with production settings and makes requests to it
- `.github/workflows/dependency-updates.yml`
  - Runs weekly, Mondays at `03:00 UTC`
  - Executes `composer update`, holding back releases younger than 7 days
  - Opens/updates PR titled `bot: dependency updates` with Vet's report of what needs review
  - When only `laravel/*` packages changed, it records their trust itself and the PR needs no review
  - Otherwise the PR cannot pass CI until `vet.json` is updated on its branch: check it out, run
    `composer install --no-plugins`, then `./vendor/bin/vet`, and commit `vet.json`
- `.github/workflows/security-gate.yml`
  - Runs Composer security audit
  - Fails on high/critical advisories
  - Runs repository secret scan with Gitleaks

## Project Structure

```text
AGENTS.md          how to build on Kit, for agents and people
Dockerfile         the production image (Octane on FrankenPHP), with docker/
vet.json           packages trusted by Laravel Vet
.legible.yaml      the agent-readiness gate
app/
  Console/Commands/  make:endpoint
  Http/
    Controllers/Api/V1/Auth/
    Middleware/
    Payloads/V1/
    Requests/Auth/
    Resources/
  Notifications/     queued verification and reset emails
  Webhooks/          webhook events, delivery and signing
  Support/
routes/
  api/
    routes.php
    v1.php
tests/
  Feature/
stubs/endpoint/     templates for make:endpoint
config/
  api-toolbox.php
  octane.php
  webhooks.php
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

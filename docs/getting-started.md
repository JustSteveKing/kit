# Getting started

## Install

Kit is a starting point rather than a package you update, so it becomes your project's first
commit. The Laravel installer does that for you:

```bash
laravel new my-api --using=juststeveking/kit
cd my-api
```

That runs `composer create-project`, which copies `.env.example` to `.env`, generates `APP_KEY`,
creates `database/database.sqlite` and runs the migrations. If you cloned the repository instead,
`composer run setup` does the same.

Local development uses SQLite. Switch `DB_CONNECTION` when you want something else; nothing in Kit
depends on SQLite.

## Run it

Kit needs two processes locally, and a third once you use webhooks:

```bash
php artisan serve         # the API, on http://127.0.0.1:8000
php artisan queue:work    # emails and webhook deliveries are queued
php artisan schedule:work # prunes old webhook events daily
```

`QUEUE_CONNECTION` is `database`, so jobs wait in the `jobs` table until a worker picks them up.
Mail goes to `storage/logs/laravel.log` (`MAIL_MAILER=log`) until you configure a mailer, which is
where you will find the verification link after registering.

## Your first requests

Routes are versioned and have no `/api` prefix: everything lives under `/v1`.

Register, which signs you in and returns a session token in `meta.token`:

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/register \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "Password123!",
    "device_name": "cli"
  }'
```

Sign in again later with the same `device_name`:

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "jane@example.com", "password": "Password123!", "device_name": "cli"}'
```

Send the token as a bearer token:

```bash
curl http://127.0.0.1:8000/v1/auth/me \
  -H "Authorization: Bearer <TOKEN>"
```

Leave the token off and ask for Spanish, and you see how every error comes back:

```bash
curl -i http://127.0.0.1:8000/v1/auth/me -H "Accept-Language: es"
```

```http
HTTP/1.1 401 Unauthorized
Content-Type: application/problem+json
Content-Language: es
WWW-Authenticate: Bearer

{"type":"https://apiguide.dev/errors/unauthorized","title":"Unauthorized","status":401,"detail":"No autenticado.","instance":"urn:uuid:0199e7f4-5d2a-7c3e-9b1a-4f2d8e6c1a07"}
```

The `type` tells a client what went wrong, the `detail` is for a person and follows their language,
and the `instance` is the request ID, so a reported error leads straight to its log lines.
[Errors](errors.md) has the rest.

The generated API reference is at `http://127.0.0.1:8000/docs/index.html`, with the OpenAPI spec beside it at
`/docs/openapi.yaml`.

## Where things live

```text
AGENTS.md            how to build on Kit, for agents and people
Dockerfile           the production image, with docker/
vet.json             packages trusted by Laravel Vet
.legible.yaml        the agent-readiness gate for the spec
app/
  Console/Commands/  make:endpoint
  Http/
    Controllers/Api/V1/{Domain}/  invokable controllers
    Middleware/
    Payloads/V1/                  readonly request payloads
    Requests/{Domain}/            Form Requests
    Resources/                    JSON resources
  Notifications/     queued verification and reset emails
  Support/           tokens, audit logging, OpenAPI generators
  Webhooks/          events, delivery and signing
config/
  api-toolbox.php    which HTTP standards are switched on
  security.php       HTTPS, HSTS, trusted hosts and proxies
  webhooks.php
lang/
  en/, es/                  Kit's messages and validation
  vendor/api-toolbox/es/    Spanish for api-toolbox's problems
routes/api/
  routes.php         adds the v1 prefix
  v1.php             every v1 route
stubs/endpoint/      templates for make:endpoint
tests/Feature/
```

## Next

[Building endpoints](building-endpoints.md) walks through adding your first endpoint. Read
[`AGENTS.md`](../AGENTS.md) too if you work with a coding agent: it is the same rules, written as
instructions.

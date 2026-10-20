# Getting started

## Install

Kit is not a package you keep updating. It becomes your project's first commit, and the Laravel
installer sets that up for you:

```bash
laravel new my-api --using=juststeveking/kit
cd my-api
```

That runs `composer create-project`, which copies `.env.example` to `.env`, generates `APP_KEY`,
creates `database/database.sqlite` and runs the migrations. If you cloned the repository instead,
`composer run setup` does the same.

Local development uses SQLite, but nothing in Kit depends on it. Change `DB_CONNECTION` whenever
you like.

## Run it

Kit needs two processes locally, and a third once you use webhooks:

```bash
php artisan serve         # the API, on http://127.0.0.1:8000
php artisan queue:work    # emails and webhook deliveries are queued
php artisan schedule:work # prunes old webhook events daily
```

Jobs wait in the `jobs` table until a worker picks them up (`QUEUE_CONNECTION=database`). Until you
configure a mailer, mail is written to `storage/logs/laravel.log`, so that is where the verification
link turns up after you register.

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

Now leave the token off and ask for Spanish. This is the shape every error takes:

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

Clients branch on `type`. The `detail` is written for a person, in their language, and `instance`
is the request ID, so when someone reports an error you can go straight to its log lines.
[Errors](errors.md) covers the rest.

The generated API reference is at `http://127.0.0.1:8000/docs/index.html`, and the OpenAPI spec
sits beside it at `/docs/openapi.yaml`.

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

[Building endpoints](building-endpoints.md) takes you through adding your first endpoint. If you
work with a coding agent, point it at [`AGENTS.md`](../AGENTS.md).

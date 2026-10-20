# Kit

A Laravel API starter kit for token-authenticated APIs that follow the HTTP standards rather than
inventing their own. You get versioned routes, Sanctum session tokens and scoped API keys, RFC 9457
problem details for every error, idempotency, cursor pagination, ETags, signed CloudEvents
webhooks, Scribe documentation checked against the running API, and a production image on Octane
and FrankenPHP.

Most of the standards come from [api-toolbox](https://github.com/JustSteveKing/api-toolbox). Kit is
the application around it: the conventions, the tests that hold them in place, and the CI that
refuses to ship anything that breaks them.

## Install

```bash
laravel new my-api --using=juststeveking/kit
cd my-api
```

The installer copies `.env.example`, generates a key, creates the SQLite database and migrates it.
Then run the API and a queue worker, since verification and reset emails are queued:

```bash
php artisan serve
php artisan queue:work
```

The API lives at `http://127.0.0.1:8000/v1/...`, with no `/api` prefix. Mail goes to the log until
you configure a mailer. Register a user to check it works:

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name": "Jane Doe", "email": "jane@example.com", "password": "Password123!", "device_name": "cli"}'
```

[Getting started](docs/getting-started.md) goes from here to an authenticated request.

## What is in it

- PHP 8.5, Laravel 13, Sanctum, Pest, PHPStan at level 10, Pint with the PER preset, and Rector
- Invokable controllers, Form Requests, readonly payload objects and plain JSON resources
- `php artisan make:endpoint`, which scaffolds an endpoint the way the rest of Kit is built
- Every error as `application/problem+json`, typed from the [apiguide.dev](https://apiguide.dev/errors)
  catalogue, with a `detail` translated from `Accept-Language`
- `Idempotency-Key`, `Deprecation` and `Sunset`, `Link` header pagination, ETags, request IDs and
  W3C trace context, and an API catalog at `/.well-known/api-catalog`
- Outbound webhooks that consumers subscribe to themselves: CloudEvents, signed with RFC 9421,
  retried on a published schedule, and recorded attempt by attempt
- OpenAPI from Scribe attributes, contract tests that compare the spec with real responses, and a
  [legible](https://github.com/JustSteveKing/legible) gate that keeps it readable by agents
- A Dockerfile for Octane on FrankenPHP, built and booted in CI on every push
- [Laravel Vet](https://github.com/laravel/vet) auditing every dependency, and a weekly update PR
- An [`AGENTS.md`](AGENTS.md) that tells coding agents how to build on Kit without undoing it

## Documentation

| Page | What it covers |
| --- | --- |
| [Getting started](docs/getting-started.md) | Installing, running locally, your first requests, where things live |
| [Authentication](docs/authentication.md) | Session tokens, API keys, abilities, verification and password reset |
| [Building endpoints](docs/building-endpoints.md) | `make:endpoint` and each piece it writes |
| [Errors](docs/errors.md) | Problem details, and raising errors of your own |
| [HTTP standards](docs/http-standards.md) | Idempotency, pagination, ETags, deprecation, request IDs, media types |
| [Localization](docs/localization.md) | `Accept-Language`, translated errors, adding a language |
| [Webhooks](docs/webhooks.md) | What subscribers receive, and adding your own events |
| [API documentation](docs/api-documentation.md) | Scribe, the OpenAPI spec, contract tests and legible |
| [Security](docs/security.md) | Transport, CORS, trusted hosts, rate limits and audit logging |
| [Deployment](docs/deployment.md) | The Docker image, workers, the scheduler and Octane |
| [Quality and CI](docs/quality-and-ci.md) | The checks, the workflows, and reviewing dependencies with Vet |
| [Configuration](docs/configuration.md) | Every environment variable Kit adds |

## License

MIT

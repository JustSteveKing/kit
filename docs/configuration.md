# Configuration

These are the environment variables Kit adds, or gives a meaning of its own. Laravel's standard
ones (`DB_*`, `MAIL_*`, `CACHE_STORE` and the rest) behave as they do in any Laravel application.

"Default" means the value when the variable is not set at all. Where `.env.example` sets something
different for local development, the table says so.

## Application

| Variable | Default | Effect |
| --- | --- | --- |
| `APP_URL` | `http://localhost` | Must be `https://` in production. Webhook `source` and origin derive from it |
| `APP_LOCALE` | `en` | Laravel's default locale |
| `APP_FALLBACK_LOCALE` | `en` | Used for translation keys a locale is missing |
| `APP_SUPPORTED_LOCALES` | `en,es` | Languages a client can ask for; the first is used when none match. See [Localization](localization.md) |
| `QUEUE_CONNECTION` | `database` | Needs a worker. Webhook rate limits need a driver that can delay, so not `sync` |
| `MAIL_MAILER` | `log` | Verification and reset emails go to the log until you set a mailer |

## Tokens

| Variable | Default | Effect |
| --- | --- | --- |
| `SANCTUM_EXPIRATION` | `120` | Minutes a session token from register or login lasts |
| `API_KEY_DEFAULT_DAYS` | `90` | Days an API key lasts when it is created without `expires_at` |
| `API_KEY_MAX_DAYS` | `365` | The furthest away an API key's `expires_at` can be |
| `IDEMPOTENCY_TTL` | `86400` | Seconds an idempotent response is kept for replay |

## Security

| Variable | Default | Effect |
| --- | --- | --- |
| `SECURITY_FORCE_HTTPS` | `false` | Refuse plain HTTP requests with a `400`. Must be `true` in production |
| `SECURITY_HSTS_ENABLED` | `true` | Send `Strict-Transport-Security` on HTTPS responses |
| `SECURITY_HSTS_MAX_AGE` | `31536000` | HSTS `max-age`, in seconds |
| `SECURITY_HSTS_INCLUDE_SUBDOMAINS` | `true` | Add `includeSubDomains` |
| `SECURITY_HSTS_PRELOAD` | `false` | Add `preload` |
| `TRUSTED_PROXIES` | `*` | Proxies whose `X-Forwarded-*` headers are believed |
| `TRUSTED_HOSTS` | empty (`.env.example`: `localhost,127.0.0.1`) | Host names the API answers to. Required in production |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:3000` | Browser origins allowed to call the API. `*` is refused in production |

See [Security](security.md).

## Webhooks

| Variable | Default | Effect |
| --- | --- | --- |
| `WEBHOOKS_SOURCE` | `APP_URL` plus `/v1` | The CloudEvents `source` of every event |
| `WEBHOOKS_ORIGIN` | `APP_URL`'s host | Sent as `WebHook-Request-Origin` |
| `WEBHOOKS_REQUIRE_HTTPS` | `true` (`.env.example`: `false`) | Refuse subscription URLs that are not https |
| `WEBHOOKS_ALLOW_PRIVATE_NETWORKS` | `false` (`.env.example`: `true`) | Allow URLs that resolve to private, loopback or reserved addresses |
| `WEBHOOKS_VALIDATE_ENDPOINTS` | `false` | Require each endpoint to consent through the CloudEvents handshake |
| `WEBHOOKS_CALLBACK_HOURS` | `72` | Hours a consent callback link stays valid |
| `WEBHOOKS_REQUEST_RATE` | none | Deliveries a minute to ask each endpoint for |
| `WEBHOOKS_TIMEOUT` | `10` | Seconds to wait for an endpoint to answer |
| `WEBHOOKS_DISABLE_AFTER` | `10` | Failed deliveries in a row before a subscription is disabled |
| `WEBHOOKS_RETENTION_DAYS` | `30` | Days events and their deliveries are kept |

The retry schedule is `backoff` in `config/webhooks.php`, and the event types subscribers can ask
for are `events` in the same file. See [Webhooks](webhooks.md).

## The Docker image

| Variable | Default | Effect |
| --- | --- | --- |
| `PORT` | `8000` | The port Octane listens on |
| `OCTANE_WORKERS` | `auto` | PHP workers |
| `OCTANE_MAX_REQUESTS` | `500` | Requests a worker serves before it is replaced |

The image sets `APP_ENV=production`, `APP_DEBUG=false` and `LOG_CHANNEL=stderr` itself. See
[Deployment](deployment.md).

## Configuration files

Some settings deliberately have no variable, because changing them should go through code review:

| File | What it holds |
| --- | --- |
| `config/api-toolbox.php` | Which HTTP standards are on, and how each behaves |
| `config/sanctum.php` | The abilities, and which ones session tokens get |
| `config/webhooks.php` | Event types and the retry schedule |
| `config/security.php` | Transport settings read from the variables above |
| `config/cors.php` | Paths, methods and exposed headers |
| `config/scribe.php` | How the API documentation is generated |
| `app/Providers/AppServiceProvider.php` | Rate limiters |

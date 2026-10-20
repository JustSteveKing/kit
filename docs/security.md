# Security

Kit's defaults are safe for local development, and it will not run in production until you have
configured the parts that depend on where you deploy.

## What production requires

`ProductionSecurityChecks` runs on boot when `APP_ENV=production` and stops the application with a
message naming the problem unless:

| Setting | Required value |
| --- | --- |
| `APP_DEBUG` | `false` |
| `SECURITY_FORCE_HTTPS` | `true` |
| `APP_URL` | Starts with `https://` |
| `CORS_ALLOWED_ORIGINS` | Does not contain `*` |
| `TRUSTED_HOSTS` | At least one host |

A misconfigured deploy fails on boot, instead of running quietly without the protection. Fix the
settings, and leave the check alone. Set `APP_KEY` too: the check does not look for it, but Laravel
needs it for signed URLs and encryption.

## Transport

`EnforceTransportSecurity` runs on every API request.

With `SECURITY_FORCE_HTTPS=true`, a request that did not arrive over HTTPS gets a `400` problem.
It deliberately does not redirect. A client that sent a token over plain HTTP has already leaked
it, and a redirect would hide that from whoever wrote the client.

On HTTPS responses it sends `Strict-Transport-Security`, with a year's `max-age` and
`includeSubDomains`. `preload` stays off until you opt in with `SECURITY_HSTS_PRELOAD=true`. Only do
that once every subdomain serves HTTPS, because browsers hold on to the preload list for months.

TLS usually ends at a load balancer or proxy in front of Kit, which has to forward
`X-Forwarded-Proto`, and Kit has to believe it. `TRUSTED_PROXIES` is `*` by default, trusting
whatever connects directly. That is fine when your load balancer is the only thing that can reach
the application. If anything else can, list the proxies' addresses or ranges.

`TRUSTED_HOSTS` is a comma-separated list of the exact host names the API answers to, and a request
for any other `Host` is refused. Without it, a forged `Host` header could end up inside the reset
and verification links Kit generates.

## CORS

CORS applies to `v1/*`. The browser origins allowed to call the API come from
`CORS_ALLOWED_ORIGINS`, a comma-separated list that defaults to `http://localhost:3000` for a local
frontend. Credentials are not supported; Kit authenticates with bearer tokens, not cookies.

The headers a browser client needs are exposed, among them `Content-Language`, `Deprecation`,
`Idempotency-Key`, `Link`, `Retry-After`, `Sunset` and `X-Request-Id`.

If no browser calls your API, set `CORS_ALLOWED_ORIGINS` to nothing. An empty list allows no
origins, which production accepts; `*` is the one value it refuses.

## Response headers

Every API response carries:

```http
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: no-referrer
```

A JSON API has no business being sniffed as another type or framed in a page. Its URLs can carry
signed tokens, too, so they should never leak out in a `Referer`.

## Tokens and abilities

- Session tokens expire after `SANCTUM_EXPIRATION` minutes, and API keys on the date they were
  given, at most `API_KEY_MAX_DAYS` away. Every token carries its own `expires_at`.
- Every protected route checks an ability. An API key can only hold abilities its creator holds.
- Grant the least each client needs. A new ability goes into `abilities.available`, and into
  `abilities.default` only if every signed-in client needs it.

[Authentication](authentication.md) has the detail.

## Rate limiting

Sign-up, sign-in and password routes are limited per IP (and per email, where there is one), and
every authenticated route is limited per user. [Authentication](authentication.md#rate-limits) has
the numbers. Behind a proxy, the per-IP limits depend on `TRUSTED_PROXIES` being right. Get it
wrong and every request seems to come from the proxy, so one noisy client locks everybody out.

## Accounts

- User IDs are ULIDs, so they cannot be enumerated by counting.
- Passwords are hashed through the model's `hashed` cast.
- Forgot password answers the same whether or not an account exists, so it cannot be used to find
  accounts.
- Verification and consent links are signed, expire and are throttled.

## Audit logging

Security-relevant actions write a `security.audit` log entry through `App\Support\SecurityAudit`.
Each carries an `event` name, the time, the request ID, the IP, the method and path, and the event's
own context. Kit logs sign-ups, sign-ins and failures, sign-outs, token creation and revocation,
verification, password resets, authorisation failures, validation failures, rate limiting and
changes to webhook subscriptions.

Log your own actions the same way, for anything that changes who can do what or destroys data:

```php
SecurityAudit::log('projects.deleted', [
    'user_id' => $user->id,
    'project_id' => $project->id,
]);
```

Name events `domain.action.outcome`. Log IDs, and use `SecurityAudit::hashEmail()` where you need
to correlate by email. Raw emails, passwords and tokens never go in the log.

Send the log channel somewhere you can search it and set alerts on it.

## Webhook targets

Subscription URLs are checked so a consumer cannot make your server call into its own network.
[Webhooks](webhooks.md#where-subscriptions-can-point) explains the rules.

## Dependencies

In CI, `composer audit` fails on high and critical advisories and Gitleaks scans for committed
secrets. [Laravel Vet](https://github.com/laravel/vet) refuses to install any package nobody has
reviewed, which [Quality and CI](quality-and-ci.md#dependencies-and-vet) covers in full.

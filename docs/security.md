# Security

Kit's defaults are safe for local development and refuse to run in production until the parts that
depend on where you deploy are configured. This page covers what is on by default, what you must
set, and how to keep it that way as you build.

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

A misconfigured deploy fails at once, rather than running quietly without the protection. Configure
the settings; do not remove the check. Set `APP_KEY` as well, which Laravel needs for signed URLs
and encryption.

## Transport

`EnforceTransportSecurity` runs on every API request.

- With `SECURITY_FORCE_HTTPS=true`, a request that did not arrive over HTTPS is a `400` problem.
  It does not redirect: a client that sent a token over plain HTTP has already leaked it, and a
  redirect would hide that.
- On HTTPS responses it sends `Strict-Transport-Security`, with a year's `max-age` and
  `includeSubDomains`. `preload` is off until you opt in with `SECURITY_HSTS_PRELOAD=true`; only do
  that once every subdomain serves HTTPS, since browsers cache the preload list for months.

TLS usually ends at a load balancer or proxy in front of Kit. It has to forward
`X-Forwarded-Proto`, and Kit has to trust it:

- `TRUSTED_PROXIES` is `*` by default, which trusts whatever connects directly. That is right when
  only your load balancer can reach the application. When anything else can, list the proxies'
  addresses or ranges instead.
- `TRUSTED_HOSTS` is a comma-separated list of the host names the API answers to, exactly; a
  request for any other `Host` is refused. It stops host header injection in generated URLs such as
  reset and verification links.

## CORS

CORS applies to `v1/*`. Browser origins allowed to call the API come from `CORS_ALLOWED_ORIGINS`, a
comma-separated list, which defaults to `http://localhost:3000` for a local frontend. Credentials
are not supported, because Kit authenticates with bearer tokens rather than cookies.

The headers a browser client needs are exposed: `Content-Language`, `Deprecation`,
`Idempotency-Key`, `Link`, `Retry-After`, `Sunset` and `X-Request-Id` among them.

If no browser calls your API, set `CORS_ALLOWED_ORIGINS` to nothing. An empty list allows no
origins, which production accepts; `*` is the one value it refuses.

## Response headers

Every API response carries:

```http
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Referrer-Policy: no-referrer
```

A JSON API has no reason to be sniffed, framed or to leak its URLs, which can carry signed tokens,
in a `Referer`.

## Tokens and abilities

- Session tokens expire after `SANCTUM_EXPIRATION` minutes, and API keys on the date they were
  given, at most `API_KEY_MAX_DAYS` away. Every token carries its own `expires_at`.
- Every protected route checks an ability. An API key can only hold abilities its creator holds.
- Grant the least each client needs. A new ability goes into `abilities.available`, and into
  `abilities.default` only if every signed-in client needs it.

[Authentication](authentication.md) has the detail.

## Rate limiting

Sign-up, sign-in and password routes are limited per IP, and per IP and email where there is one;
every authenticated route is limited per user. The table is in
[Authentication](authentication.md#rate-limits). Behind a proxy, limits per IP only work when
`TRUSTED_PROXIES` is right, or every request appears to come from the proxy.

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

Log your own actions the same way, for anything that changes access or destroys data:

```php
SecurityAudit::log('projects.deleted', [
    'user_id' => $user->id,
    'project_id' => $project->id,
]);
```

Name events `domain.action.outcome`. Log IDs, and `SecurityAudit::hashEmail()` where you need to
correlate by email. Never log raw emails, passwords or tokens.

Send the log channel somewhere you can search and alert on; a `security.audit` entry nobody reads
protects nothing.

## Webhook targets

Subscription URLs are checked so a consumer cannot make your server call its own network: https
only, no private, loopback or reserved addresses, checked again on every send, and no redirects.
See [Webhooks](webhooks.md#where-subscriptions-can-point).

## Dependencies

`composer audit` runs in CI and fails on high and critical advisories, Gitleaks scans for committed
secrets, and [Laravel Vet](https://github.com/laravel/vet) refuses to install any package nobody has
reviewed. See [Quality and CI](quality-and-ci.md#dependencies-and-vet).

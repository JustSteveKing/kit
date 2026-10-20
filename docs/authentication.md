# Authentication

Kit authenticates with Sanctum bearer tokens, and there are two kinds. A session token comes from
registering or signing in. It carries the default abilities and expires after `SANCTUM_EXPIRATION`
minutes, two hours unless you change it. An API key is something a signed-in client creates for a
script, an integration or an agent: it holds only the abilities it was given, and lasts for days.

Under the hood both are ordinary personal access tokens, sent as `Authorization: Bearer <token>`,
and each one carries its own `expires_at`. Sanctum's global `expiration` is switched off on purpose.
It caps every token, so with it on no API key could outlive a session.

## Endpoints

| Method | Path | Auth | Purpose |
| --- | --- | --- | --- |
| POST | `/v1/auth/register` | None | Create an account and issue a session token |
| POST | `/v1/auth/login` | None | Sign in and issue a session token |
| GET | `/v1/auth/me` | `auth:me` | The current user, with an `ETag` |
| POST | `/v1/auth/logout` | `auth:logout` | Revoke the token making the request |
| GET | `/v1/auth/tokens` | `auth:tokens:read` | The user's tokens, newest first, cursor paginated |
| POST | `/v1/auth/tokens` | `auth:tokens:create` | Create an API key |
| DELETE | `/v1/auth/tokens` | `auth:tokens:delete` | Revoke every token, including this one |
| DELETE | `/v1/auth/tokens/{token_id}` | `auth:tokens:delete` | Revoke one token |
| POST | `/v1/auth/email/verification-notification` | `auth:verification:send` | Send the verification email again |
| GET | `/v1/auth/email/verify/{id}/{hash}` | Signed URL | Verify an email address |
| POST | `/v1/auth/password/forgot` | None | Email a reset link |
| GET | `/v1/auth/password/reset/{token}` | None | Read the token and email from a reset link |
| POST | `/v1/auth/password/reset` | None | Set a new password |

Register and login return the user, with the token in `meta.token` and its expiry in
`meta.expires_at`. Name the client in `device_name`. That is how a user tells their tokens apart in
`GET /v1/auth/tokens`, where `is_current` marks the one making the request.

## Abilities

Every protected route checks an ability, and `config/sanctum.php` keeps two lists of them:

- `abilities.available`: every ability a route checks. An API key can only be given these.
- `abilities.default`: what a session token gets from register or login.

Out of the box both lists hold the same eight: `auth:me`, `auth:logout`, `auth:verification:send`,
`auth:tokens:read`, `auth:tokens:create`, `auth:tokens:delete`, `webhooks:read` and
`webhooks:write`.

A new route's ability always goes in `available`. Only put it in `default` as well if every
signed-in client should have it, and leave the rest to API keys. When a new endpoint answers `403`
to everyone, check this first: an ability in neither list cannot be granted to anybody.

## API keys

Create a key from any token holding `auth:tokens:create`:

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/tokens \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"name": "deploy-bot", "abilities": ["auth:me"], "expires_at": "2027-01-01T00:00:00Z"}'
```

The `abilities` have to be a subset of what the calling token holds, out of
`abilities.available`. A narrow key can never mint a broader one, which is what makes it safe to
hand an agent a key that can only read webhooks.

`expires_at` is optional. Without it the key lasts `API_KEY_DEFAULT_DAYS`, 90 by default, and it
can never be more than `API_KEY_MAX_DAYS` (a year) away.

The key itself comes back in `meta.token`, once. Store it then, because no endpoint returns it
again. Send an `Idempotency-Key` and a retried request gets the same key back, so a dropped
connection does not leave a stray key behind.

Revoke a key like any other token, with `DELETE /v1/auth/tokens/{token_id}`.

Every token is issued through `App\Support\AccessTokens`, which is what sets its expiry. Any new
way of issuing tokens has to go through it as well, or those tokens will never expire.

## Email verification

The `User` model implements `MustVerifyEmail`, so registering queues a verification email. Its link
points at `GET /v1/auth/email/verify/{id}/{hash}`, is signed, and expires. A signed-in user can ask
for a fresh one with `POST /v1/auth/email/verification-notification`, up to six times a minute.

Kit records whether an address is verified, but nothing requires it. If a route should be closed to
unverified users, add Laravel's `verified` middleware to it.

## Password reset

1. `POST /v1/auth/password/forgot` with an `email` queues a reset email. The response is the same
   whether or not an account uses that address, so it cannot be used to find accounts.
2. The link in the email opens `GET /v1/auth/password/reset/{token}?email=...`, which returns the
   token and email as JSON. A client that shows its own reset form reads them from here. It does
   not check the token.
3. `POST /v1/auth/password/reset` with `token`, `email`, `password` and `password_confirmation`
   sets the new password. A bad or expired token is a `422` on `email`.

## Rate limits

The limiters are defined in `AppServiceProvider::boot()`:

| Limiter | Limit | Keyed by | Used on |
| --- | --- | --- | --- |
| `auth-register` | 10 a minute | IP | register |
| `auth-login` | 10 a minute | IP and email | login |
| `auth-password` | 5 a minute | IP and email | forgot and reset |
| `auth-protected` | 60 a minute | User | every authenticated route |

Verification, consent links and a few of the webhook actions add `throttle:6,1` on top. Going over
any limit is a `429` problem with `Retry-After`.

When you add a group of routes, give it its own named limiter here. Borrowing one sized for sign-in
traffic means guessing at two sets of numbers instead of one.

## Testing authentication

Give the test's token only the abilities the route needs. That way the test proves the ability
check works, as well as the happy path:

```php
$token = User::factory()->create()->createToken('test', ['auth:me'])->plainTextToken;

$this->withToken($token)->getJson('/v1/auth/me')->assertOk();
```

`make:endpoint` writes the unauthenticated and missing-ability tests for you.

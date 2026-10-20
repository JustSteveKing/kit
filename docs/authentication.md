# Authentication

Kit authenticates with Sanctum bearer tokens. There are two kinds, and they differ in who asks
for them and how long they live:

- **Session tokens** come from registering or signing in, carry the default abilities, and expire
  after `SANCTUM_EXPIRATION` minutes (120 by default).
- **API keys** are created by a signed-in client for a script, an integration or an agent. They
  hold only the abilities asked for, and live for days rather than minutes.

Both are ordinary personal access tokens, sent as `Authorization: Bearer <token>`, and every token
carries its own `expires_at`. Sanctum's global `expiration` is off on purpose: it would cap every
token, and no API key could outlive a session.

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
`meta.expires_at`. Name the client in `device_name`; it is how a user tells their tokens apart in
`GET /v1/auth/tokens`, where `is_current` marks the one making the request.

## Abilities

Every protected route checks an ability, and `config/sanctum.php` keeps two lists of them:

- `abilities.available`: every ability a route checks. An API key can only be given these.
- `abilities.default`: what a session token gets from register or login.

Out of the box both lists hold the same eight: `auth:me`, `auth:logout`, `auth:verification:send`,
`auth:tokens:read`, `auth:tokens:create`, `auth:tokens:delete`, `webhooks:read` and
`webhooks:write`.

When you add an ability for a new route, add it to `available`. Add it to `default` as well only if
every signed-in client should have it; otherwise clients reach it through an API key. A route with
an ability that is in neither list cannot be called by anyone, which is the first thing to check
when a new endpoint answers `403`.

## API keys

Create a key from any token holding `auth:tokens:create`:

```bash
curl -X POST http://127.0.0.1:8000/v1/auth/tokens \
  -H "Authorization: Bearer <TOKEN>" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"name": "deploy-bot", "abilities": ["auth:me"], "expires_at": "2027-01-01T00:00:00Z"}'
```

- `abilities` must be a subset of what the calling token holds, out of `abilities.available`. A
  narrow key can never mint a broader one, so handing an agent a key with `webhooks:read` is a
  real limit.
- `expires_at` is optional. Leave it out and the key lasts `API_KEY_DEFAULT_DAYS` (90); it cannot
  be later than `API_KEY_MAX_DAYS` (365) away.
- The key's value is in `meta.token`, and that response is the only time you will see it.
- With an `Idempotency-Key`, a retried request returns the same key rather than a second one.

Revoke a key like any other token, with `DELETE /v1/auth/tokens/{token_id}`.

Tokens are issued through `App\Support\AccessTokens`, which sets each one's expiry. If you add
another way to issue tokens, go through it too, or the new tokens will never expire.

## Email verification

The `User` model implements `MustVerifyEmail`, so registering queues a verification email. The link
in it is signed, expires, and points at `GET /v1/auth/email/verify/{id}/{hash}`; following it
verifies the address. A signed-in user can ask for another with
`POST /v1/auth/email/verification-notification`, limited to six a minute.

Kit records verification but does not require it anywhere. To keep unverified users out of a
route, add Laravel's `verified` middleware to it.

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

Verification, consent links and a few webhook actions add `throttle:6,1` on top. Going over a
limit is a `429` problem with `Retry-After`.

Give a new group of routes its own named limiter beside these, rather than reusing one that was
sized for something else.

## Testing authentication

Give the test's token only the abilities the route needs, so it proves the ability check as well
as the happy path:

```php
$token = User::factory()->create()->createToken('test', ['auth:me'])->plainTextToken;

$this->withToken($token)->getJson('/v1/auth/me')->assertOk();
```

`make:endpoint` writes the unauthenticated and missing-ability tests for you.

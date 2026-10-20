---
name: api-toolbox-development
description: Build Laravel APIs with juststeveking/api-toolbox. Use when returning API errors (RFC 9457 problem details, make:api-problem), adding ETags, preconditions or caching, cursor pagination, idempotency keys, Prefer or async 202 operations, JSON Merge Patch, Content-Digest, signing or verifying webhooks (HTTP Message Signatures), deprecating endpoints, the API catalog, 406/415 negotiation, request IDs, or testing any of these.
---

# API Toolbox Development

## When to use this skill

Use this skill when you write or change an API endpoint in an application that requires `juststeveking/api-toolbox`, or when you test one. Each feature implements a published HTTP standard. Use the feature rather than inventing a header, query parameter or JSON shape for something a standard already covers.

## First: which features are on

Read `config/api-toolbox.php`. Features are listed in `features` with options passed inline:

```php
use JustSteveKing\Toolbox\Features;

'features' => [
    Features::problemDetails(['type_base_uri' => 'https://docs.example.com/problems']),
    Features::conditionalRequests(),
    Features::idempotency(['ttl' => 86400]),
    // ...
],
```

- If the file does not exist, every stable feature is on with its defaults. `Features::rateLimitHeaders()` is experimental and is off unless listed.
- A disabled feature registers nothing: no middleware alias, route, container binding, Artisan command or test assertion. Using one of those for a disabled feature fails. To enable a feature, add it to the list; do not register its pieces by hand.
- Check at runtime with `JustSteveKing\Toolbox\Facades\ApiToolbox::enabled(Features::idempotency())`, or inject `JustSteveKing\Toolbox\Contracts\EnabledFeatures`.
- Features built on an IETF draft that is still changing are experimental and may break in a minor release. Today that is RateLimit headers only.

## Choose the reference for the task

| Task | Read |
|---|---|
| Returning an error, a custom error type, validation errors, `make:api-problem` | [references/problem-details.md](references/problem-details.md) |
| ETags, 304, 412, 428, `Cache-Control`, cursor pagination | [references/caching-and-pagination.md](references/caching-and-pagination.md) |
| PATCH, `Prefer`, idempotent POST, 202 Accepted and background jobs | [references/writes.md](references/writes.md) |
| Signing or verifying webhooks and requests, `Content-Digest` | [references/signatures-and-digests.md](references/signatures-and-digests.md) |
| Deprecating or sunsetting endpoints, the API catalog, 406/415, request IDs, tracing, rate limits | [references/api-lifecycle.md](references/api-lifecycle.md) |
| Writing tests for any of the above | [references/testing.md](references/testing.md) |

## Middleware aliases

| Alias | Feature | Does |
|---|---|---|
| `conditional` | Conditional Requests | `preconditions` then `validators` |
| `preconditions` | Conditional Requests | Evaluates `If-*` headers: 412 or 304 |
| `validators` | Conditional Requests | Adds `ETag` and `Last-Modified`; `validators:weak` for a weak hash |
| `preconditions.required` | Conditional Requests | 428 without a precondition; `preconditions.required:PUT,POST` |
| `idempotent` | Idempotency | Honours `Idempotency-Key`; `idempotent:required` for 400 without one |
| `prefer` | Preferences | `return=minimal` and `Preference-Applied`, with `Vary: Prefer` |
| `merge-patch` | Merge Patch | 415 with `Accept-Patch` for any other PATCH format |
| `digest.verify` | Digest Fields | Checks `Content-Digest`/`Repr-Digest`; `digest.verify:required` |
| `digest.respond` | Digest Fields | Adds digests a client asks for; `digest.respond:always` |
| `signature.verify` | Message Signatures | 401 without a valid signature |
| `signature.sign` | Message Signatures | Signs the response; `signature.sign:key-id` |
| `deprecation` | Deprecation | Only needed when the feature's `global` option is off |
| `api-catalog.link` | API Catalog | Adds `Link: <...>; rel="api-catalog"` |
| `produces` | Negotiation | 406; `produces:application/json` |
| `consumes` | Negotiation | 415; `consumes:application/json,application/merge-patch+json` |

Request IDs and trace context run globally, and RateLimit headers replace Laravel's `throttle` middleware. None of them needs adding to routes.

## Rules that apply everywhere

- Never build error JSON by hand in an API route. Throw; the package renders `application/problem+json`.
- Every problem the package raises is typed from the apiguide.dev catalogue. Reuse `JustSteveKing\Toolbox\Support\ApiGuide` constants for your own problems where an entry fits, rather than inventing a type that means the same thing.
- Do not add `X-` headers for things these standards cover: `Idempotency-Key`, `Prefer`/`Preference-Applied`, `Deprecation`/`Sunset`, `RateLimit`/`RateLimit-Policy`, `Content-Digest`, `Signature`/`Signature-Input`, `traceparent`.
- Order of route middleware does not matter for this package's middleware: it registers its own priority, so `preconditions.required` runs before `preconditions`, and `idempotent` runs after authentication.

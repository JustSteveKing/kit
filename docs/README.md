# Kit documentation

If you are new to Kit, read [Getting started](getting-started.md) first. After that, each page
stands on its own.

## Using Kit

- [Getting started](getting-started.md): install, run locally, make your first requests, and find
  your way around the project
- [Authentication](authentication.md): session tokens, API keys, abilities, email verification,
  password reset and rate limits
- [Building endpoints](building-endpoints.md): `make:endpoint` and the six pieces of every endpoint
- [Errors](errors.md): problem details, raising your own, documenting and testing them
- [HTTP standards](http-standards.md): idempotency, pagination, ETags, partial updates,
  deprecation, request IDs and media types
- [Localization](localization.md): how the language is chosen, what is translated, adding a
  language
- [Webhooks](webhooks.md): the contract subscribers get, and adding events of your own

## Shipping it

- [API documentation](api-documentation.md): Scribe, the contract tests, and legible
- [Security](security.md): what production requires, transport, CORS and audit logging
- [Deployment](deployment.md): the Docker image, the worker and scheduler, and Octane-safe code
- [Quality and CI](quality-and-ci.md): the checks, the workflows, and reviewing dependencies with
  Vet
- [Configuration](configuration.md): every environment variable Kit adds

## For coding agents

[`AGENTS.md`](../AGENTS.md) holds the same rules, written as instructions for an agent working in
the codebase. These pages explain why things are the way they are; that file tells the agent what
to do.

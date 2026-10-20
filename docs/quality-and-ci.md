# Quality and CI

Kit is held in shape by five checks, run locally and in CI on every push and pull request. A change
that fails any of them is not finished.

```bash
composer lint           # Pint, in check mode
composer rector:check   # Rector, in check mode
composer stan           # PHPStan with Larastan, level 10
composer test           # Pest, in parallel, including the OpenAPI contract tests
legible check public/docs/openapi.yaml
```

## Style and refactoring

**Pint** owns formatting, with the PER preset and the rules in `pint.json`: Yoda comparisons,
`! $x` with a space, strict types, final classes and alphabetical imports, among others.

**Rector** changes code: PHP and Laravel upgrades, dead code and type declarations, through
`driftingly/rector-laravel`. It never formats. Its coding-style set is off, and `rector.php` lists
the rules skipped on purpose, each with the reason.

The two would argue if they both formatted, so they run in a fixed order, and Pint has the last
word:

```bash
composer rector
composer pint
```

CI runs both in check mode, so code has to be committed as the pair leave it. If a Rector rule fights Pint, skip the
rule in `rector.php` with a reason; do not change `pint.json` to suit it.

Finish a generated endpoint before you run Rector. It removes the generator's placeholders as dead
code.

## Static analysis

PHPStan runs at level 10, the strictest, with Larastan. At that level `mixed` cannot be used as
anything until it is narrowed, which in Kit means:

- `config()->string()`, `config()->integer()` and `config()->array()` rather than `config()`
- `$request->string('field')`, or `Arr::string()` on validated data, rather than `$request->input()`
- typed model properties

A `@phpstan-ignore` needs the identifier and a comment saying why. Do not lower the level or add a
baseline to get a change through.

## Tests

Tests are Pest feature tests, run in parallel. They cover the auth flows, verification and reset,
security and unhappy paths, localization, the HTTP standards, webhooks, the generator, production
checks, and the OpenAPI contract. api-toolbox's assertions are available on every response; see
[HTTP standards](http-standards.md#testing).

Run one file while you work, and the whole suite before you push:

```bash
php artisan test tests/Feature/AuthTokenTest.php
composer test
```

## The workflows

### `ci-tests.yml`

On every push and pull request:

1. **Test.** Installs dependencies with Vet enabled, so an unreviewed package fails here. Then
   Pint, Rector, PHPStan and the test suite, and legible against the spec the tests generated.
   legible is downloaded at a pinned version and its checksum verified.
2. **Image.** Builds the production image, boots it with production settings, and checks that
   `/up` answers, that an unauthenticated request is a `401` problem, and that the problem's
   `instance` matches the response's `X-Request-Id`.

### `security-gate.yml`

On every push and pull request, `composer audit` fails the build on a high or critical advisory, and
Gitleaks scans the repository for committed secrets.

### `dependency-updates.yml`

Weekly, on Mondays at 03:00 UTC, and on demand. It runs `composer update` and opens or updates a
pull request titled `bot: dependency updates`, with Vet's report in the description. The next
section covers what to do with it.

For it to open pull requests, allow GitHub Actions to create them: **Settings, Actions, General,
Workflow permissions**.

## Dependencies and Vet

`composer audit` only knows about advisories that have been published. A release that was
compromised this morning passes it. [Laravel Vet](https://github.com/laravel/vet), a Composer
plugin, covers that gap two ways:

- **Review.** `vet.json` records the version and file hash of every package someone has read.
  `composer install` fails on anything else, including the same version republished with
  different files.
- **Time.** `minimum-release-age: 7` in `vet.json` keeps releases younger than a week out of `composer update`,
  so a bad release has time to be found and pulled before you install it.

`juststeveking/*` and `laravel/*` skip the hold, as first-party packages, and so does
`roave/security-advisories`, which is no use unless it is current.

### After you add or update a package

```bash
composer require vendor/package
./vendor/bin/vet
```

`vet` shows what changed, package by package, for you to read, or to hand to your coding agent to
read. Commit `vet.json` with `composer.lock`.

Laravel's own packages are trusted by policy. When `laravel/*` packages are the only ones an update
changed, record them without a review:

```bash
./vendor/bin/vet --fresh
```

### Reviewing the weekly pull request

When only `laravel/*` packages changed, the bot records them itself and the pull request needs no
review: merge it when CI passes.

Otherwise CI fails until `vet.json` is updated on the branch:

```bash
gh pr checkout <number>
composer install --no-plugins
./vendor/bin/vet
git commit -am "Vet dependency updates" && git push
```

`--no-plugins` installs the new lock without Vet blocking it, precisely so Vet can then audit it.
That is the only place to use it. Never use it to get past Vet anywhere else, and never edit
`vet.json` by hand.

### In your own project

`vet.json` arrives holding the trust Kit recorded. Take it over as your own with
`./vendor/bin/vet --fresh`, or remove `laravel/vet` if you do not want the gate.

## Coding agents

[`AGENTS.md`](../AGENTS.md) gives coding agents these rules as instructions: the checks, how to add
an endpoint, and what not to touch. Agents that follow the AGENTS.md convention read it directly,
and `CLAUDE.md` imports it for Claude Code.

Below the import, `CLAUDE.md` carries the [Laravel Boost](https://laravel.com/docs/boost)
guidelines, with api-toolbox's guideline and `api-toolbox-development` skill opted in through
`boost.json`. Run `php artisan boost:update` after upgrading packages; it rewrites only its own
tagged block. To set Boost up for another agent, run `php artisan boost:install` and pick it.

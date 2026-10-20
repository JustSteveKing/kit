# Quality and CI

Five checks keep Kit in shape. They run locally and in CI on every push and pull request, and a
change that fails any of them is not finished.

```bash
composer lint           # Pint, in check mode
composer rector:check   # Rector, in check mode
composer stan           # PHPStan with Larastan, level 10
composer test           # Pest, in parallel, including the OpenAPI contract tests
legible check public/docs/openapi.yaml
```

## Style and refactoring

Pint owns formatting, with the PER preset plus the rules in `pint.json`: Yoda comparisons,
`! $x` with a space, strict types, final classes and alphabetical imports, among others.

Rector changes code. It handles PHP and Laravel upgrades, dead code and type declarations, with
`driftingly/rector-laravel` for the Laravel side, and it never formats. Its coding-style set is
switched off, and `rector.php` lists each rule skipped on purpose with the reason.

The two would argue if they both formatted, so they run in a fixed order, and Pint has the last
word:

```bash
composer rector
composer pint
```

CI runs both in check mode, so code has to be committed the way the pair leave it. If a Rector
rule fights Pint, skip the rule in `rector.php` with a reason. `pint.json` does not bend to suit
Rector.

Finish a generated endpoint before you run Rector, or it deletes the generator's placeholders as
dead code.

## Static analysis

PHPStan runs with Larastan at level 10, the strictest there is. At that level `mixed` cannot be
used as anything until it has been narrowed. In practice that means reaching for
`config()->string()`, `config()->integer()` and `config()->array()` over plain `config()`,
`$request->string('field')` or `Arr::string()` on validated data over `$request->input()`, and typed
model properties.

A `@phpstan-ignore` needs the identifier and a comment saying why. Lowering the level or adding a
baseline to get a change through is not an option.

## Tests

The tests are Pest feature tests, run in parallel. Between them they cover the auth flows,
verification and reset, the unhappy paths, localization, the HTTP standards, webhooks, the
generator, the production checks and the OpenAPI contract. api-toolbox's assertions work on every
response, as [HTTP standards](http-standards.md#testing) shows.

Run one file while you work, and the whole suite before you push:

```bash
php artisan test tests/Feature/AuthTokenTest.php
composer test
```

## The workflows

### `ci-tests.yml`

On every push and pull request:

1. The `test` job installs dependencies with Vet enabled, so an unreviewed package fails here.
   Then it runs Pint, Rector, PHPStan and the test suite, and legible against the spec the tests
   generated. legible is downloaded at a pinned version and its checksum verified.
2. The `image` job builds the production image and boots it with production settings. It checks
   that `/up` answers, that an unauthenticated request is a `401` problem, and that the problem's
   `instance` matches the response's `X-Request-Id`.

### `security-gate.yml`

On every push and pull request, `composer audit` fails the build on a high or critical advisory, and
Gitleaks scans the repository for committed secrets.

### `dependency-updates.yml`

Every Monday at 03:00 UTC, or whenever you run it by hand, this runs `composer update` and opens
or updates a pull request titled `bot: dependency updates`, with Vet's report in the description.

It can only open pull requests if GitHub Actions is allowed to create them, under **Settings,
Actions, General, Workflow permissions**.

## Dependencies and Vet

`composer audit` only knows about advisories that have been published, so a release compromised
this morning sails through it. [Laravel Vet](https://github.com/laravel/vet), a Composer plugin,
covers that gap.

First, `vet.json` records the version and file hash of every package someone has read, and
`composer install` fails on anything else. That includes the same version republished with
different files. Second, `minimum-release-age: 7` in `vet.json` keeps any release younger than a
week out of `composer update`, which gives a bad release time to be found and pulled before it
reaches you.

`juststeveking/*` and `laravel/*` skip the hold as first-party packages. So does
`roave/security-advisories`, which is no use unless it is current.

### After you add or update a package

```bash
composer require vendor/package
./vendor/bin/vet
```

`vet` walks through what changed, package by package. Read it yourself or hand it to your coding
agent, then commit `vet.json` with `composer.lock`.

Laravel's own packages are trusted by policy. When `laravel/*` packages are the only ones an update
changed, record them without a review:

```bash
./vendor/bin/vet --fresh
```

### Reviewing the weekly pull request

When only `laravel/*` packages changed, the bot records them itself. There is nothing to review,
so merge it once CI passes.

Otherwise CI fails until `vet.json` is updated on the branch:

```bash
gh pr checkout <number>
composer install --no-plugins
./vendor/bin/vet
git commit -am "Vet dependency updates" && git push
```

`--no-plugins` installs the new lock without Vet blocking it, precisely so that Vet can audit it
next. This is the one place it belongs. Do not use it to get past Vet anywhere else, and do not
edit `vet.json` by hand.

### In your own project

`vet.json` arrives holding the trust Kit recorded. Take it over as your own with
`./vendor/bin/vet --fresh`, or remove `laravel/vet` if you do not want the gate.

## Coding agents

[`AGENTS.md`](../AGENTS.md) gives coding agents these rules as instructions. Agents that follow the
AGENTS.md convention read it directly, and `CLAUDE.md` imports it for Claude Code.

Below the import, `CLAUDE.md` carries the [Laravel Boost](https://laravel.com/docs/boost)
guidelines, with api-toolbox's guideline and `api-toolbox-development` skill opted in through
`boost.json`. Run `php artisan boost:update` after upgrading packages; it rewrites only its own
tagged block. To set Boost up for another agent, run `php artisan boost:install` and pick it.

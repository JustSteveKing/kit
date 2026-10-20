# Deployment

Kit ships a `Dockerfile` for a production image: Laravel Octane on
[FrankenPHP](https://frankenphp.dev), running as a non-root user on port 8000. CI builds it and
boots it with production settings on every push, so a change that breaks the image fails before it
reaches you.

You do not have to use it. Kit is a Laravel application and deploys anywhere Laravel does,
including Laravel Cloud and Forge. Most of this page applies either way, since you still need the
same processes, environment and release step.

## What runs

| Process | Command | Why |
| --- | --- | --- |
| Web | the image's default | Serves the API |
| Queue worker | `php artisan queue:work` | Sends emails and webhook deliveries |
| Scheduler | `php artisan schedule:work` | Prunes webhook events daily |

All three run from the same image:

```bash
docker build -t my-api .

docker run -p 8000:8000 --env-file .env.production my-api
docker run --env-file .env.production my-api php artisan queue:work
docker run --env-file .env.production my-api php artisan schedule:work
```

Forget the worker and nobody receives a verification email or a webhook. Nothing errors either,
which makes it an easy one to miss.

## The image

Dependencies are installed without dev packages, from the same `composer.lock` Vet audited in CI,
with a classmap-authoritative autoloader. `docker/php.ini` tunes OPcache for production and turns
off timestamp checks, since the code never changes inside a running container. Logs go to `stderr`
for your platform to collect, and the health check hits `/up` every 30 seconds.

On start, `docker/entrypoint.sh` runs `php artisan optimize`, which caches config, routes and
events from the environment the container was given, and then starts Octane. Pass a command, as
the worker does, and it runs that instead.

These variables tune Octane:

| Variable | Default | Effect |
| --- | --- | --- |
| `PORT` | `8000` | The port to listen on |
| `OCTANE_WORKERS` | `auto` | PHP workers; `auto` leaves the count to Octane |
| `OCTANE_MAX_REQUESTS` | `500` | Requests a worker serves before it is replaced |

## Environment

Production refuses to boot without the settings that depend on your deployment. At minimum:

```dotenv
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://api.example.com

SECURITY_FORCE_HTTPS=true
TRUSTED_HOSTS=api.example.com
CORS_ALLOWED_ORIGINS=https://app.example.com

DB_CONNECTION=pgsql
DB_HOST=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=database
MAIL_MAILER=smtp
```

The image has the MySQL and PostgreSQL drivers as well as SQLite.
[Security](security.md#what-production-requires) explains each check, and
[Configuration](configuration.md) lists every variable.

Put TLS in front of the container, at a load balancer or proxy, and forward `X-Forwarded-Proto`.
Check `TRUSTED_PROXIES` against your network too, because the default trusts whatever connects
directly.

## Releasing

Run migrations as a release step, once, before the new containers take traffic:

```bash
docker run --env-file .env.production my-api php artisan migrate --force
```

Then replace the web, worker and scheduler containers. Workers in particular have to be restarted
on every release, or they carry on running the old code.

## Writing code that is safe under Octane

Octane keeps the application in memory between requests. That is where its speed comes from, and
it is also how state leaks from one request into the next. Three rules keep that from happening:

- Keep request state out of singletons and static properties. Use `scoped()` bindings, which Octane
  resets for each request; api-toolbox binds its request ID and trace context that way.
- Resolve request state when the request is served, not in a closure that captured the application
  at boot. Octane serves each request from a sandboxed copy of the application, so use
  `Container::getInstance()` or the helpers inside such a closure, not a `$this->app` captured in a
  service provider.
- Dev packages are not in the image. Config that refers to one, as `config/scribe.php` does, must
  return early when the package is missing, or the image fails to boot. CI catches this.

`php artisan serve` does not run Octane, so a leak will not show up locally. When you change
anything that holds state, build and run the image.

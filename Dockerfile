# syntax=docker/dockerfile:1

# Production image: Laravel Octane on FrankenPHP, one process serving HTTP on port 8000.
# The same image runs a queue worker: `docker run <image> php artisan queue:work`.
FROM dunglas/frankenphp:1.13.1-php8.5-trixie AS base

RUN install-php-extensions intl opcache pcntl pdo_mysql pdo_pgsql zip

WORKDIR /app

# Dependencies without dev packages. Vet audited this lock in CI, where it is installed with dev
# packages and the plugin enabled; production installs a subset of the same trusted lock.
FROM base AS vendor

COPY --from=composer:2.9 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && APP_ENV=build php artisan package:discover --ansi \
    && cp vendor/laravel/octane/src/Commands/stubs/frankenphp-worker.php public/frankenphp-worker.php

FROM base

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    OCTANE_SERVER=frankenphp \
    PHP_INI_SCAN_DIR=":$PHP_INI_DIR/app.conf.d"

COPY docker/php.ini $PHP_INI_DIR/app.conf.d/app.ini
COPY --from=vendor /app /app

RUN useradd --create-home --uid 1000 app \
    && chown -R app:app /app/storage /app/bootstrap/cache /data/caddy /config/caddy

USER app

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s CMD ["php", "docker/healthcheck.php"]

ENTRYPOINT ["docker/entrypoint.sh"]

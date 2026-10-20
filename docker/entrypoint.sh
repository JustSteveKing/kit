#!/bin/sh
# Caches configuration, routes and events from the environment the container was started with, then
# runs the command: Octane on FrankenPHP by default, or what was passed, such as
# `php artisan queue:work` for a worker from the same image.
set -e

php artisan optimize --no-interaction

if [ "$#" -eq 0 ]; then
    exec php artisan octane:start \
        --server=frankenphp \
        --host=0.0.0.0 \
        --port="${PORT:-8000}" \
        --admin-port=2019 \
        --workers="${OCTANE_WORKERS:-auto}" \
        --max-requests="${OCTANE_MAX_REQUESTS:-500}"
fi

exec "$@"

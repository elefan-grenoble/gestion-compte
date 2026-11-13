#!/bin/sh
# Entrypoint of the prod image (php and cron services).
set -eu

cd /var/www/app

if [ "${1:-}" = php-fpm ] && ! command -v php-fpm >/dev/null; then
    echo "app-entrypoint: no php-fpm in this image: build the prod target with --build-arg PHP_BASE_IMAGE=php:7.4-fpm." >&2
    exit 1
fi

# The configuration is the instance's .env, mounted read-only. Without it the
# kernel would silently fall back to .env.dist (dev defaults).
# (Docker creates a directory when a bind-mounted file does not exist.)
if [ ! -f .env ] || [ ! -r .env ]; then
    echo "app-entrypoint: /var/www/app/.env is missing, not a file or not readable by $(id -un)." >&2
    echo "Mount the instance's .env file (see doc/install.serveur.docker.md)." >&2
    exit 1
fi

# var/cache lives in the container: rebuild it for this container's code and
# environment. No database connection is needed (serverVersion is set).
php bin/console cache:warmup --no-interaction >/dev/null

exec "$@"

#!/bin/sh
# Target platform: Railway (root "Dockerfile" of this repository).
# The root Dockerfile copies this script to /usr/local/bin/entrypoint.sh and
# starts it with ENTRYPOINT ["entrypoint.sh"]; nginx and php-fpm are then run
# by supervisord and the application is served on $PORT (Railway default: 8000).
set -eu

/usr/local/bin/generate-site-conf.sh

mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

touch .env

# storage/app/public is a persistent volume: add missing seed images, never overwrite.
if [ -d /opt/storage-public-seed ]; then
    cp -an /opt/storage-public-seed/. storage/app/public/ || true
fi

if [ -z "${APP_KEY:-}" ]; then
    APP_KEY="$(sed -n 's/^APP_KEY=//p' .env | tail -n 1)"

    if [ -z "$APP_KEY" ]; then
        printf '%s\n' "FATAL: APP_KEY is not set." >&2
        printf '%s\n' "Set a stable APP_KEY in the platform environment (Railway: Service > Variables)." >&2
        printf '%s\n' "Generate one with: php artisan key:generate --show" >&2
        printf '%s\n' "Refusing to start: a container-generated key changes on every redeploy and makes encrypted data undecryptable." >&2
        exit 1
    fi

    export APP_KEY
fi

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# The container environment is the source of truth: drop any configuration
# cached at build time, then rebuild it from the current environment.
rm -f bootstrap/cache/config.php bootstrap/cache/routes-*.php bootstrap/cache/events.php

if ! php artisan config:cache; then
    printf '%s\n' "WARNING: config:cache failed; the application starts without a cached configuration." >&2
fi

if ! php artisan route:cache; then
    printf '%s\n' "WARNING: route:cache failed; the application starts without cached routes." >&2
fi

php artisan storage:link --force

if [ "${SEED_THEME_SECTIONS:-}" = "true" ]; then
    php artisan db:seed --class="Webkul\\Installer\\Database\\Seeders\\Shop\\SectionTableSeeder" --force --no-interaction \
        || printf '%s\n' "WARNING: theme section seeding failed." >&2
fi

if ! php artisan migrate --force --no-interaction; then
    printf '%s\n' "WARNING: migrate failed; starting anyway." >&2
fi

php-fpm -t
nginx -t

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf

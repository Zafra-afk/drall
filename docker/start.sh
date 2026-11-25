
#!/bin/sh
set -eu

echo "=== Drall startup ==="

# Required environment variables
: "${APP_KEY:?APP_KEY must be configured in Railway}"
: "${DB_HOST:?DB_HOST must be configured in Railway}"
: "${DB_DATABASE:?DB_DATABASE must be configured in Railway}"
: "${DB_USERNAME:?DB_USERNAME must be configured in Railway}"
: "${DB_PASSWORD:?DB_PASSWORD must be configured in Railway}"

DB_PORT="${DB_PORT:-3306}"
PORT="${PORT:-8080}"

# Wait for MySQL
echo "Waiting for MySQL..."

attempt=0
until php -r '
$host = getenv("DB_HOST");
$port = (int) (getenv("DB_PORT") ?: 3306);
$s = @fsockopen($host, $port, $errno, $errstr, 3);
if ($s) {
    fclose($s);
    exit(0);
}
exit(1);
'; do
    attempt=$((attempt + 1))

    if [ "$attempt" -ge 30 ]; then
        echo "ERROR: MySQL unavailable after 30 attempts."
        exit 1
    fi

    sleep 2
done

echo "MySQL is reachable."

# Create Laravel directories
mkdir -p storage/framework/views \
         storage/framework/cache \
         storage/framework/sessions \
         storage/logs \
         bootstrap/cache

# Permissions
chmod -R 775 storage bootstrap/cache

# Check storage link without deleting existing files
if [ ! -L public/storage ] && [ ! -e public/storage ]; then
    php artisan storage:link
elif [ -L public/storage ] && [ -e public/storage ]; then
    echo "Storage symlink already exists."
else
    echo "ERROR: public/storage exists but is not a valid symlink."
    echo "Inspect it manually before proceeding."
    exit 1
fi

# Apply pending migrations
php artisan migrate --force --no-interaction

# ---------------------------------------------------------------------------
# Media repair. Every step is non-destructive and non-fatal: a failure here is
# logged and must never stop the app from starting.
# ---------------------------------------------------------------------------

# 1. Restore tracked files hidden by the volume mount (never overwrite).
if [ -d /opt/storage-seed ]; then
    cp -rn /opt/storage-seed/. storage/app/public/ 2>/dev/null || echo "WARN: storage seed copy incomplete"
fi

# 2. One-time reload of the default theme sections (+ their images) and the
#    idempotent Drall demo catalog + 30-product catalog. Guarded by a marker on the volume so it
#    runs once; the marker is only written when both succeed.
SEED_MARKER="storage/app/public/.media-seeded-v3"
if [ ! -f "$SEED_MARKER" ]; then
    echo "Reloading default theme sections and demo catalog..."
    if php artisan db:seed --class='Webkul\Installer\Database\Seeders\Shop\DatabaseSeeder' --force --no-interaction \
        && php artisan db:seed --class='Database\Seeders\DrallDemoProductsSeeder' --force --no-interaction \
        && php artisan db:seed --class='Database\Seeders\DrallCatalogSeeder' --force --no-interaction; then
        touch "$SEED_MARKER"
    else
        echo "WARN: seed reload failed; continuing startup (will retry next boot)"
    fi
fi

# 3. Restore missing demo product images and report other missing media.
php artisan drall:repair-media || echo "WARN: media repair failed; continuing"

chown -R www-data:www-data storage bootstrap/cache

# Render nginx config with the Railway port
export PORT
envsubst '${PORT}' < docker/nginx/default.conf.template > /etc/nginx/conf.d/default.conf
rm -f /etc/nginx/sites-enabled/default
mkdir -p /var/log/supervisor

echo "Starting Drall (nginx + php-fpm + queue worker) on port $PORT"

exec /usr/bin/supervisord -n -c /var/www/html/docker/nginx/supervisord.conf

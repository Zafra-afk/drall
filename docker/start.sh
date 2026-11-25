
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

# One-time repair: theme section images referenced in the database were missing
# from the storage volume. Re-seed the default sections (which copies their
# images onto the volume). Guarded by a marker file so it never runs twice.
SECTIONS_MARKER="storage/app/public/.sections-seeded-v1"
if [ ! -f "$SECTIONS_MARKER" ]; then
    echo "Re-seeding default theme sections and images..."
    php artisan db:seed --class='Webkul\Installer\Database\Seeders\Shop\DatabaseSeeder' --force --no-interaction
    touch "$SECTIONS_MARKER"
fi


chown -R www-data:www-data storage bootstrap/cache

# Render nginx config with the Railway port
export PORT
envsubst '${PORT}' < docker/nginx/default.conf.template > /etc/nginx/conf.d/default.conf
rm -f /etc/nginx/sites-enabled/default
mkdir -p /var/log/supervisor

echo "Starting Drall (nginx + php-fpm + queue worker) on port $PORT"

exec /usr/bin/supervisord -n -c /var/www/html/docker/nginx/supervisord.conf

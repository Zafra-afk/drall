web: php artisan serve --host=0.0.0.0 --port=$PORT
release: php artisan optimize:clear 2>/dev/null; touch .env && php artisan migrate --force && php artisan config:cache && php artisan route:cache

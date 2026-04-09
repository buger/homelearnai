#!/bin/sh
set -e

echo "=========================================="
echo " HomeLearnAI - Container Startup"
echo "=========================================="

# -------------------------------------------------------
# 1. Generate APP_KEY if missing
# -------------------------------------------------------
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "[init] No APP_KEY set — generating one..."
    php artisan key:generate --force --no-interaction
    # Export the generated key so config:cache picks it up
    export APP_KEY=$(grep '^APP_KEY=' /var/www/html/.env | cut -d '=' -f 2-)
    echo "[init] Generated APP_KEY: ${APP_KEY:0:20}..."
else
    echo "[init] APP_KEY is set."
    # Write APP_KEY to .env so artisan commands can find it
    sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" /var/www/html/.env
fi

# -------------------------------------------------------
# 2. Wait for database to be reachable (belt + suspenders)
# -------------------------------------------------------
echo "[init] Waiting for database at ${DB_HOST:-db}:${DB_PORT:-5432}..."
MAX_RETRIES=30
RETRY=0
until php -r "
    \$c = @pg_connect('host=${DB_HOST:-db} port=${DB_PORT:-5432} dbname=${DB_DATABASE:-homelearnai} user=${DB_USERNAME:-homelearnai} password=${DB_PASSWORD:-secret}');
    if (!\$c) { exit(1); }
    pg_close(\$c);
    exit(0);
" 2>/dev/null; do
    RETRY=$((RETRY + 1))
    if [ "$RETRY" -ge "$MAX_RETRIES" ]; then
        echo "[init] ERROR: Database not reachable after ${MAX_RETRIES} attempts. Exiting."
        exit 1
    fi
    echo "[init]   Attempt $RETRY/$MAX_RETRIES — retrying in 2s..."
    sleep 2
done
echo "[init] Database is reachable."

# -------------------------------------------------------
# 3. Run migrations
# -------------------------------------------------------
echo "[init] Running database migrations..."
php artisan migrate --force --no-interaction
echo "[init] Migrations complete."

# -------------------------------------------------------
# 4. Cache configuration for performance
# -------------------------------------------------------
echo "[init] Caching configuration..."
php artisan config:cache
php artisan route:cache || echo "[init] WARNING: route:cache failed (duplicate route names?), skipping..."
php artisan view:cache
echo "[init] Caching complete."

# -------------------------------------------------------
# 5. Fix storage permissions (in case volume was mounted)
# -------------------------------------------------------
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# -------------------------------------------------------
# 6. Create storage link if it doesn't exist
# -------------------------------------------------------
if [ ! -L /var/www/html/public/storage ]; then
    php artisan storage:link --no-interaction
    echo "[init] Storage symlink created."
fi

echo "=========================================="
echo " HomeLearnAI - Starting services..."
echo "=========================================="

# Hand off to supervisord (nginx + php-fpm)
exec "$@"

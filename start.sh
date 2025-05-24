#!/bin/sh

# Create storage directories
mkdir -p /app/storage/framework/cache
mkdir -p /app/storage/framework/sessions
mkdir -p /app/storage/framework/views
mkdir -p /app/storage/logs

# set permissions
chown -R www-data:www-data /app/storage
chown -R www-data:www-data /app/bootstrap/cache
chmod -R 775 /app/storage
chmod -R 775 /app/bootstrap/cache


if [ ! -f /app/.env ]; then
    cp /app/.env.example /app/.env
    php /app/artisan key:generate
fi


php /app/artisan config:clear
php /app/artisan config:cache
php /app/artisan route:clear
php /app/artisan route:cache
php /app/artisan view:clear
php /app/artisan view:cache

# Run migrations (opcional)
php /app/artisan migrate --force

# Iniciar supervisord
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
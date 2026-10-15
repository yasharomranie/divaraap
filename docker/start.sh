#!/bin/sh
set -eu
cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    printf '%s\n' 'APP_KEY must be configured before starting the application.' >&2
    exit 1
fi

mkdir -p storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link

php-fpm -D
exec nginx -g 'daemon off;'

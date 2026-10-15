#!/bin/sh
set -e

if [ ! -f /var/www/html/.env ]; then
    cp /var/www/html/.env.example /var/www/html/.env
fi

cd /var/www/html

composer install
npm ci --no-audit --no-fund

if ! grep -q '^APP_KEY=.' /var/www/html/.env; then
    php artisan key:generate --force
fi
php artisan migrate --force
php artisan storage:link

php-fpm -D
npm run dev &
nginx -g 'daemon off;'

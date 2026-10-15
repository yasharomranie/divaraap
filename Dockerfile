# syntax=docker/dockerfile:1
FROM node:24-bookworm-slim AS frontend
RUN --mount=type=bind,target=/release-source,readonly \
    --mount=type=secret,id=proxy_ca \
    if [ -f /run/secrets/proxy_ca ]; then export NODE_EXTRA_CA_CERTS=/run/secrets/proxy_ca; fi; \
    mkdir -p /build && cd /build && \
    cp /release-source/package.json /release-source/package-lock.json /release-source/tsconfig.json /release-source/vite.config.ts . && \
    cp -a /release-source/resources ./resources && \
    npm ci --no-audit --no-fund && npm run build && rm -rf node_modules /root/.npm

FROM composer:2 AS composer
FROM php:8.4-fpm-bookworm
ARG COMPOSER_INSTALL_PREFERENCE=dist
RUN --mount=type=bind,target=/release-source,readonly \
    --mount=type=bind,from=composer,source=/usr/bin/composer,target=/tmp/composer-binary,readonly \
    --mount=type=bind,from=frontend,source=/build/public/build,target=/release-build,readonly \
    --mount=type=secret,id=proxy_ca \
    if [ -f /run/secrets/proxy_ca ]; then export SSL_CERT_FILE=/run/secrets/proxy_ca CURL_CA_BUNDLE=/run/secrets/proxy_ca COMPOSER_CAFILE=/run/secrets/proxy_ca GIT_SSL_CAINFO=/run/secrets/proxy_ca; fi; \
    apt-get -o Acquire::https::CaInfo=${SSL_CERT_FILE:-/etc/ssl/certs/ca-certificates.crt} update && \
    apt-get -o Acquire::https::CaInfo=${SSL_CERT_FILE:-/etc/ssl/certs/ca-certificates.crt} install -y --no-install-recommends nginx ffmpeg git unzip libpq-dev libicu-dev libzip-dev libpng-dev libwebp-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libxml2-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j4 pdo_pgsql intl zip gd mbstring bcmath pcntl exif opcache && \
    cp /tmp/composer-binary /usr/local/bin/composer && chmod +x /usr/local/bin/composer && \
    mkdir -p /var/www/html && cp -a /release-source/. /var/www/html/ && cd /var/www/html && \
    mkdir -p public/build && cp -a /release-build/. public/build/ && \
    composer install --no-dev --optimize-autoloader --prefer-${COMPOSER_INSTALL_PREFERENCE} --no-interaction && \
    composer clear-cache && find vendor -type d -name .git -prune -exec rm -rf '{}' + && \
    cp docs/environment/php.ini /usr/local/etc/php/conf.d/uploads.ini && \
    cp docker/nginx.conf /etc/nginx/nginx.conf && cp docker/start.sh /start.sh && chmod +x /start.sh && \
    chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache && \
    rm -rf /var/lib/apt/lists/* /root/.composer/cache
WORKDIR /var/www/html
EXPOSE 80
CMD ["/start.sh"]

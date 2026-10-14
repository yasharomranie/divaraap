# syntax=docker/dockerfile:1
FROM composer:2 AS composer
FROM php:8.4-cli-bookworm
RUN --mount=type=secret,id=proxy_ca,required=true \
    apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca update && \
    apt-get -o Acquire::https::CaInfo=/run/secrets/proxy_ca install -y --no-install-recommends libpq-dev libicu-dev libzip-dev libpng-dev libwebp-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev libxml2-dev git unzip && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j4 pdo_pgsql intl zip gd mbstring bcmath pcntl exif && \
    rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app

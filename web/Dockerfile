FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        gmp-dev \
        libpng-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
        sqlite-dev \
        postgresql-dev \
    && docker-php-ext-install -j$(nproc) \
        gmp \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlite \
        gd \
        zip \
        intl \
        bcmath \
        opcache

WORKDIR /var/www/html
COPY --from=vendor /app ./

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]

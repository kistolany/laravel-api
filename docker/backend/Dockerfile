# syntax=docker/dockerfile:1.7

FROM composer:2 AS vendor-production
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

FROM composer:2 AS vendor-development
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

FROM php:8.2-fpm-alpine AS runtime
RUN apk add --no-cache \
        bash \
        fcgi \
        libzip-dev \
        oniguruma-dev \
        sqlite-dev \
        unzip \
        zip \
    && docker-php-ext-install \
        bcmath \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_sqlite \
        zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY docker/backend/entrypoint.sh /usr/local/bin/docker-entrypoint
RUN chmod +x /usr/local/bin/docker-entrypoint

COPY backend ./
RUN ln -sf /var/www/html/storage/app/public /var/www/html/public/storage \
    && chmod -R ug+rwx /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

FROM runtime AS development
COPY --from=vendor-development /app/vendor /var/www/html/vendor
ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

FROM runtime AS production
ENV APP_ENV=production \
    APP_DEBUG=false
COPY --from=vendor-production /app/vendor /var/www/html/vendor
ENTRYPOINT ["docker-entrypoint"]
CMD ["php-fpm"]

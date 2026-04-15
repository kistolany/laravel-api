FROM php:8.2-fpm-alpine

# 1. Install system dependencies & PHP extensions
RUN apk add --no-cache \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    oniguruma-dev \
    icu-dev

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    bcmath \
    fileinfo \
    exif \
    gd \
    intl \
    zip \
    opcache

# Runtime tuning for faster local API responses
COPY docker/php/performance.ini /usr/local/etc/php/conf.d/performance.ini

# 2. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Set working directory
WORKDIR /var/www
COPY . .

# 4. Install Laravel dependencies
RUN composer install --no-interaction --optimize-autoloader

# 5. Expose Port 8000
EXPOSE 9000

# 6. Start PHP-FPM for Nginx upstream
CMD ["php-fpm", "-F"]
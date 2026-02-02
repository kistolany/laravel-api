FROM php:8.2-cli-alpine

# 1. Install system dependencies & PHP extensions for MySQL
RUN apk add --no-cache \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    oniguruma-dev

RUN docker-php-ext-install pdo_mysql mbstring bcmath

# 2. Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. Set working directory
WORKDIR /var/www
COPY . .

# 4. Install Laravel dependencies
RUN composer install --no-interaction --optimize-autoloader

# 5. Expose Port 8000
EXPOSE 8000

# 6. Start the API server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
#!/bin/sh
set -e

cd /var/www/html

if [ ! -f .env ] && [ -f .env.example ]; then
  cp .env.example .env
fi

mkdir -p \
  bootstrap/cache \
  storage/app/public \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/views \
  storage/logs

if [ ! -f vendor/autoload.php ]; then
  COMPOSER_ARGS="--no-interaction --no-progress --prefer-dist --optimize-autoloader"

  if [ "${APP_ENV:-local}" = "production" ]; then
    COMPOSER_ARGS="$COMPOSER_ARGS --no-dev --classmap-authoritative"
  fi

  composer install $COMPOSER_ARGS
fi

if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
  php artisan key:generate --force >/dev/null 2>&1 || true
fi

if [ ! -L public/storage ]; then
  ln -sf /var/www/html/storage/app/public /var/www/html/public/storage
fi

chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rwx storage bootstrap/cache || true

exec "$@"

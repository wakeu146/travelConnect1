FROM composer:2 AS vendor
WORKDIR /app
COPY . .
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-scripts --ignore-platform-reqs

FROM node:22 AS assets
WORKDIR /app
COPY --from=vendor /app .
RUN npm ci && npm run build

FROM php:8.3-cli
RUN apt-get update && apt-get install -y libzip-dev libpq-dev libsqlite3-dev unzip \
 && docker-php-ext-install pdo_mysql pdo_pgsql pdo_sqlite zip
WORKDIR /var/www
COPY --from=vendor /app .
COPY --from=assets /app/public/build public/build
RUN chmod -R 775 storage bootstrap/cache
CMD ["sh", "-c", "php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=10000"]


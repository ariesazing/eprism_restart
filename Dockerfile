# syntax=docker/dockerfile:1

# ---------- Stage 1: build frontend assets (Vite) ----------
FROM node:22-alpine AS assets
WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# Vite inlines VITE_* variables at build time, so they must be present now.
# Override any of these from Dokploy -> Application -> Build -> "Build Args".
ARG VITE_APP_NAME=ePrism
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST=eprism.online
ARG VITE_REVERB_PORT=443
ARG VITE_REVERB_SCHEME=https
ENV VITE_APP_NAME=$VITE_APP_NAME \
    VITE_REVERB_APP_KEY=$VITE_REVERB_APP_KEY \
    VITE_REVERB_HOST=$VITE_REVERB_HOST \
    VITE_REVERB_PORT=$VITE_REVERB_PORT \
    VITE_REVERB_SCHEME=$VITE_REVERB_SCHEME

COPY . .
RUN npm run build


# ---------- Stage 2: install PHP dependencies ----------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Autoloader is generated in the runtime stage once the full source tree exists.
RUN composer install \
      --no-dev --no-scripts --no-autoloader \
      --prefer-dist --no-interaction --no-progress


# ---------- Stage 3: runtime ----------
FROM php:8.3-fpm-bookworm AS app

# PHP extensions + web server + process manager
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
      bcmath gd intl pcntl pdo_mysql sockets zip opcache redis \
 && apt-get update \
 && apt-get install -y --no-install-recommends nginx supervisor \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN composer dump-autoload --no-dev --optimize \
 && php artisan package:discover --ansi \
 && mkdir -p \
      storage/framework/cache \
      storage/framework/sessions \
      storage/framework/views \
      storage/logs \
      storage/app/public \
      bootstrap/cache \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php.ini          /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/nginx.conf       /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker/entrypoint.sh    /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint"]
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]

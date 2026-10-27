# Stage 1: Build Frontend Assets (Vue 3 + Tailwind + Vite)
FROM node:22-alpine AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
# Baked into the SPA; keep in sync with docker/entrypoint.sh defaults.
ENV VITE_REVERB_APP_KEY=omniseerr-key \
    VITE_REVERB_HOST=localhost \
    VITE_REVERB_PORT=8080 \
    VITE_REVERB_SCHEME=http
RUN npm run build

# Stage 2: Production Runtime (PHP 8.3-FPM + Nginx + SQLite + Supervisor)
FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    nginx \
    supervisor \
    sqlite \
    sqlite-dev \
    curl \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    && docker-php-ext-install pdo pdo_sqlite mbstring zip pcntl bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

COPY . .
COPY --from=frontend /build/public/build ./public/build

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/php.ini /usr/local/etc/php/conf.d/omniseerr.ini

RUN chmod +x /entrypoint.sh && \
    mkdir -p /config /var/www/html/storage /var/www/html/bootstrap/cache && \
    chown -R www-data:www-data /var/www/html /config

VOLUME ["/config"]
EXPOSE 5056

ENTRYPOINT ["/entrypoint.sh"]

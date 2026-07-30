# syntax=docker/dockerfile:1

###############################################################################
# FAMtastic Designs — Drupal 11 backend image (Phase 2 deployment scaffold).
#
#   Stage 1 (composer): resolves the locked vendor tree with
#       composer install --no-dev --optimize-autoloader
#   Stage 2 (runtime):  php:8.3-fpm-alpine + nginx, serving web/ on port 80.
#       nginx and PHP-FPM talk over a unix socket (/run/php-fpm/php-fpm.sock)
#       and both run as the non-root www-data user.
#
# DB connection comes from DB_* env vars (see docker-compose.yml and the
# bridge at the bottom of web/sites/default/settings.php). The local
# php -S + SQLite flow is unaffected — this image is additive.
###############################################################################

# ---------------------------------------------------------------------------
# Stage 1: composer — locked, production-only vendor tree.
# ---------------------------------------------------------------------------
FROM php:8.3-cli-alpine AS composer

# ext-gd is a hard platform requirement of drupal/core, so the CLI stage
# needs the same extensions composer will validate against.
RUN apk add --no-cache git unzip libpng libjpeg-turbo freetype libzip oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
       libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd mbstring zip \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Manifests first: this layer is cached until composer.json/lock changes.
# --no-scripts defers drupal/core-composer-scaffold until the source is copied.
COPY composer.json composer.lock ./
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-interaction \
      --no-progress \
      --prefer-dist \
    && composer clear-cache

# Full source (web/ docroot skeleton, config/, recipes/, custom theme...),
# then a second install that runs scaffold scripts and dumps the optimized
# autoloader. Package downloads are a no-op thanks to the layer above.
COPY . .
RUN composer install \
      --no-dev \
      --optimize-autoloader \
      --no-interaction \
      --no-progress

# ---------------------------------------------------------------------------
# Stage 2: runtime — PHP-FPM (unix socket) behind nginx on :80.
# ---------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS runtime

# nginx serves the docroot; libcap lets the non-root nginx bind port 80.
RUN apk add --no-cache nginx libcap libpng libjpeg-turbo freetype libzip oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
       libpng-dev libjpeg-turbo-dev freetype-dev libzip-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd mbstring zip opcache \
    && apk del .build-deps \
    && setcap 'cap_net_bind_service=+ep' /usr/sbin/nginx \
    && mkdir -p /run/php-fpm \
    && chown -R www-data:www-data /run/php-fpm

# Production opcache tuning (standard Drupal recommendations). Timestamps are
# never revalidated because every deploy ships a fresh image.
RUN { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=192'; \
      echo 'opcache.interned_strings_buffer=16'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini

# Replace the stock FPM pool (TCP :9000) with our unix-socket pool.
RUN rm -f /usr/local/etc/php-fpm.d/www.conf \
          /usr/local/etc/php-fpm.d/zz-docker.conf

WORKDIR /var/www/html

# App code: web/ docroot + vendor/ + config/ + recipes/ (see .dockerignore).
COPY --from=composer --chown=www-data:www-data /app /var/www/html

COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-famtastic.conf
COPY docker/entrypoint.sh /usr/local/bin/famtastic-entrypoint

# Writable paths: public files (compose mounts a volume here), private files
# (../private relative to the docroot, per settings.php), config sync target.
RUN chmod +x /usr/local/bin/famtastic-entrypoint \
    && mkdir -p /var/www/html/web/sites/default/files /var/www/html/private \
    && chown -R www-data:www-data \
       /var/www/html/web/sites/default/files \
       /var/www/html/private \
       /var/www/html/config

USER www-data

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/famtastic-entrypoint"]

ARG USERNAME
ARG RELEASE_VERSION

FROM ghcr.io/${USERNAME}/composer_runner:${RELEASE_VERSION} AS composer_runner
FROM ghcr.io/${USERNAME}/npm_runner:${RELEASE_VERSION} AS npm_runner
FROM php:8.1.0-fpm-alpine3.15 AS php_builder

WORKDIR /var/www/html/

# PHP extensions
RUN apk --no-cache add \
    postgresql-dev \
    supervisor \
    bash \
    $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo_pgsql

# Copy composer and node built layers
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
COPY ../src/ .
COPY --from=npm_runner /app .
COPY --from=composer_runner /app .

# Supervisor config
COPY ../prod/supervisor/supervisord.conf /etc/supervisord.conf
COPY ../prod/supervisor/conf.d/ /etc/supervisor/conf.d/

# Fix permissions
RUN chown -R www-data:www-data . \
    && chmod -R 775 .

# Entrypoint to run both php-fpm and supervisor
CMD ["/bin/sh", "-c", "php-fpm -D && supervisord -c /etc/supervisord.conf"]
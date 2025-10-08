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

# Create necessary directories first
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache

# Create startup script with comprehensive permission fixes
RUN echo '#!/bin/bash' > /startup.sh \
    && echo 'set -e' >> /startup.sh \
    && echo 'echo "Starting application initialization..."' >> /startup.sh \
    && echo '' >> /startup.sh \
    && echo '# Create directories if they dont exist' >> /startup.sh \
    && echo 'mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache' >> /startup.sh \
    && echo '' >> /startup.sh \
    && echo '# Remove any existing log files that might have wrong permissions' >> /startup.sh \
    && echo 'rm -f storage/logs/laravel.log storage/logs/*.log' >> /startup.sh \
    && echo '' >> /startup.sh \
    && echo '# Set ownership and permissions' >> /startup.sh \
    && echo 'chown -R www-data:www-data storage bootstrap/cache' >> /startup.sh \
    && echo 'chmod -R 777 storage' >> /startup.sh \
    && echo 'chmod -R 775 bootstrap/cache' >> /startup.sh \
    && echo '' >> /startup.sh \
    && echo '# Create initial log file with correct permissions' >> /startup.sh \
    && echo 'touch storage/logs/laravel.log' >> /startup.sh \
    && echo 'chown www-data:www-data storage/logs/laravel.log' >> /startup.sh \
    && echo 'chmod 666 storage/logs/laravel.log' >> /startup.sh \
    && echo '' >> /startup.sh \
    && echo 'echo "Starting PHP-FPM..."' >> /startup.sh \
    && echo 'php-fpm -D' >> /startup.sh \
    && echo '' >> /startup.sh \
    && echo 'echo "Starting Supervisor..."' >> /startup.sh \
    && echo 'exec supervisord -c /etc/supervisord.conf' >> /startup.sh \
    && chmod +x /startup.sh

# Set proper ownership and permissions during build
RUN chown -R www-data:www-data . \
    && chmod -R 777 storage \
    && chmod -R 775 bootstrap/cache

# Use startup script as entrypoint
CMD ["/startup.sh"]
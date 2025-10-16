FROM php:8.1.0-fpm-alpine3.15

# Install required packages and PHP extensions
RUN apk --update add \
    shadow \
    sudo \
    npm \
    make \
    g++ \
    autoconf \
    gcc \
    postgresql-dev \
    supervisor \
    bash \
    curl \
    && apk add --no-cache autoconf g++ make \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && docker-php-ext-install pdo_pgsql \
    && apk del --no-cache autoconf g++ make

# Create Supervisor log directory
RUN mkdir -p /var/log/supervisor

# Update www-data UID/GID to match host
RUN usermod -u 1000 www-data && groupmod -g 1000 www-data

# Allow www-data to use sudo
RUN echo "www-data ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers

# Create Laravel working directory
WORKDIR /var/www/html

# Copy Composer from official image
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Copy supervisor config
COPY ./dockerfiles/supervisor/supervisord.conf /etc/supervisord.conf
COPY ./dockerfiles/supervisor/laravel-worker.conf /etc/supervisor/conf.d/laravel-worker.conf

# Create startup script
RUN echo '#!/bin/bash' > /start.sh && \
    echo 'php-fpm -D' >> /start.sh && \
    echo '/usr/bin/supervisord -c /etc/supervisord.conf' >> /start.sh && \
    chmod +x /start.sh

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Set entrypoint to start both PHP-FPM and supervisor
CMD ["/start.sh"]
FROM php:8.3-fpm-alpine

# System deps
RUN apk add --no-cache \
    bash git unzip curl libpq-dev icu-dev oniguruma-dev linux-headers \
    freetype-dev libjpeg-turbo-dev libpng-dev

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) pdo pdo_pgsql intl mbstring gd

# Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_HOME=/tmp/composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Create non-root user and group
RUN addgroup -S app && adduser -S -G app app \
 && mkdir -p /var/www/html \
 && chown -R app:app /var/www/html \
 && chown -R app:app /usr/local/etc/php-fpm.d || true

WORKDIR /var/www/html

# Switch to non-root user
USER app

# Default command runs php-fpm
CMD ["php-fpm"]



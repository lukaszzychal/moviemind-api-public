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

WORKDIR /var/www/html

# Default command runs php-fpm
CMD ["php-fpm"]



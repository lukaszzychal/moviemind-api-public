FROM php:8.3-fpm-alpine

# System deps
RUN apk add --no-cache \
    bash git unzip curl libpq-dev icu-dev oniguruma-dev linux-headers \
    freetype-dev libjpeg-turbo-dev libpng-dev \
    nginx supervisor

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

# Configure Nginx directories and permissions
RUN mkdir -p /var/log/nginx /run/nginx /var/log/supervisor \
 && chown -R app:app /var/log/nginx /run/nginx /var/log/supervisor \
 && chmod -R 755 /var/log/nginx /run/nginx /var/log/supervisor

# Copy Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy Supervisor configuration
COPY docker/php/supervisord.conf /etc/supervisord.conf

# Copy start script
COPY docker/php/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Fix Nginx config to use 127.0.0.1:9000 instead of php:9000
RUN sed -i 's/fastcgi_pass php:9000/fastcgi_pass 127.0.0.1:9000/' /etc/nginx/http.d/default.conf || true

WORKDIR /var/www/html

# Copy composer files first (for better Docker layer caching)
COPY api/composer.json api/composer.lock* ./

# Install Composer dependencies
# Use --no-scripts to avoid running Laravel commands during build
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction || \
    composer install --optimize-autoloader --no-scripts --no-interaction

# Copy application files
COPY api/ ./

# Set proper permissions for Laravel
RUN chown -R app:app /var/www/html \
 && chmod -R 755 /var/www/html \
 && chmod -R 775 storage bootstrap/cache || true

# Run Laravel post-install scripts (now that we have full app context)
RUN composer dump-autoload --optimize --no-interaction || true

# Expose port (Railway will set PORT env var)
EXPOSE 80

# Start supervisor via start script (configures Nginx with PORT)
CMD ["/usr/local/bin/start.sh"]



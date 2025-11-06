# ============================================================================
# Multi-stage Dockerfile for MovieMind API
# ============================================================================
# Stages:
#   - base: Common base with PHP, extensions, Composer, and user setup
#   - builder: Install Composer dependencies
#   - local: For local/dev environments (PHP-FPM only, no Nginx/Supervisor)
#   - production: For staging/production/deploy (PHP-FPM + Nginx + Supervisor)
# ============================================================================

# ----------------------------------------------------------------------------
# Stage 1: Base - Common foundation
# ----------------------------------------------------------------------------
FROM php:8.3-fpm-alpine AS base

# System dependencies
RUN apk add --no-cache \
    bash git unzip curl libpq-dev icu-dev oniguruma-dev linux-headers \
    freetype-dev libjpeg-turbo-dev libpng-dev \
    wget

# Install PECL and Redis extension
# Note: PECL will handle Redis dependencies automatically
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del .build-deps

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) pdo pdo_pgsql intl mbstring gd opcache

# Composer
ENV COMPOSER_ALLOW_SUPERUSER=1
ENV COMPOSER_HOME=/tmp/composer
ENV COMPOSER_CACHE_DIR=/tmp/composer-cache
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
 && rm -rf /tmp/composer-cache

# Create non-root user and group
RUN addgroup -S app && adduser -S -G app app \
 && mkdir -p /var/www/html \
 && chown -R app:app /var/www/html \
 && chown -R app:app /usr/local/etc/php-fpm.d || true

WORKDIR /var/www/html

# ----------------------------------------------------------------------------
# Stage 2: Builder - Install dependencies
# ----------------------------------------------------------------------------
FROM base AS builder

# Copy composer files first (for better Docker layer caching)
COPY api/composer.json api/composer.lock* ./

# Install Composer dependencies
# Use --no-scripts to avoid running Laravel commands during build
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction || \
    composer install --optimize-autoloader --no-scripts --no-interaction \
 && rm -rf /tmp/composer-cache /tmp/composer \
 && composer clear-cache || true

# ----------------------------------------------------------------------------
# Stage 3: Local/Dev - PHP-FPM only (for docker-compose with separate Nginx)
# ----------------------------------------------------------------------------
FROM base AS local

# Copy dependencies from builder
COPY --from=builder --chown=app:app /var/www/html/vendor ./vendor

# Copy application files (excluding vendor, logs, cache - handled by .dockerignore)
COPY --chown=app:app api/ ./

# Ensure Laravel storage directories exist and set proper permissions
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
 && mkdir -p bootstrap/cache \
 && chown -R app:app /var/www/html \
 && chmod -R 755 /var/www/html \
 && chmod -R 775 storage bootstrap/cache

# Run Laravel post-install scripts (now that we have full app context)
RUN composer dump-autoload --optimize --no-interaction || true \
 && rm -rf /tmp/composer-cache /tmp/composer \
 && composer clear-cache || true

# Expose PHP-FPM port
EXPOSE 9000

# Start PHP-FPM directly (Nginx runs in separate container)
CMD ["php-fpm", "-F"]

# ----------------------------------------------------------------------------
# Stage 4: Production/Staging/Deploy - PHP-FPM + Nginx + Supervisor
# ----------------------------------------------------------------------------
FROM base AS production

# Install Nginx and Supervisor for production deployments
RUN apk add --no-cache nginx supervisor wget

# Configure Nginx directories and permissions
RUN mkdir -p /var/log/nginx /run/nginx /var/log/supervisor \
 && chown -R app:app /var/log/nginx /run/nginx /var/log/supervisor \
 && chmod -R 755 /var/log/nginx /run/nginx /var/log/supervisor

# Copy Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy Supervisor configuration
COPY docker/php/supervisord.conf /etc/supervisord.conf

# Copy entrypoint script (for migrations and setup)
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy start script
COPY docker/php/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Fix Nginx config to use 127.0.0.1:9000 instead of php:9000
RUN sed -i 's/fastcgi_pass php:9000/fastcgi_pass 127.0.0.1:9000/' /etc/nginx/http.d/default.conf || true

# Copy dependencies from builder
COPY --from=builder --chown=app:app /var/www/html/vendor ./vendor

# Copy application files (excluding vendor, logs, cache - handled by .dockerignore)
COPY --chown=app:app api/ ./

# Ensure Laravel storage directories exist and set proper permissions
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
 && mkdir -p bootstrap/cache \
 && chown -R app:app /var/www/html \
 && chmod -R 755 /var/www/html \
 && chmod -R 775 storage bootstrap/cache

# Run Laravel post-install scripts (now that we have full app context)
RUN composer dump-autoload --optimize --no-interaction || true \
 && rm -rf /tmp/composer-cache /tmp/composer \
 && composer clear-cache || true

# Clean up build artifacts and temporary files to reduce image size
RUN rm -rf /tmp/* /var/tmp/* \
 && rm -rf /root/.composer/cache \
 && rm -rf /var/cache/apk/* \
 && find /usr/local/lib/php -name "*.a" -delete || true \
 && find /usr/local/lib/php -name "*.la" -delete || true

# Configure Opcache for production
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.revalidate_freq=2" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# Healthcheck for production (uses PORT env var or defaults to 80)
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
  CMD sh -c 'PORT=${PORT:-80}; wget --quiet --tries=1 --spider http://localhost:${PORT}/ || exit 1'

# Expose port (Railway will set PORT env var)
EXPOSE 80

# Start supervisor via start script (configures Nginx with PORT)
CMD ["/usr/local/bin/start.sh"]

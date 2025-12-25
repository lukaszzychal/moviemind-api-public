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
 && docker-php-ext-install -j$(nproc) pdo pdo_pgsql intl mbstring gd opcache pcntl

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
# Stage 4: Production/Staging/Deploy - PHP-FPM + Nginx + Supervisor (Distroless)
# ----------------------------------------------------------------------------
# Note: Using distroless/python3 because Supervisor requires Python runtime
FROM gcr.io/distroless/python3-debian12:nonroot AS production

# Copy PHP runtime from builder (Alpine base)
COPY --from=base --chown=nonroot:nonroot /usr/local/bin/php* /usr/local/bin/
COPY --from=base --chown=nonroot:nonroot /usr/local/lib/php /usr/local/lib/php
COPY --from=base --chown=nonroot:nonroot /usr/local/etc/php /usr/local/etc/php
COPY --from=base --chown=nonroot:nonroot /usr/local/include/php /usr/local/include/php

# Copy PHP shared libraries dependencies
COPY --from=base --chown=nonroot:nonroot /lib/libc.musl-* /lib/ || true
COPY --from=base --chown=nonroot:nonroot /usr/lib/libpq.so* /usr/lib/ || true
COPY --from=base --chown=nonroot:nonroot /usr/lib/libssl.so* /usr/lib/ || true
COPY --from=base --chown=nonroot:nonroot /usr/lib/libcrypto.so* /usr/lib/ || true

# Copy Nginx from builder (Alpine base)
COPY --from=base --chown=nonroot:nonroot /usr/sbin/nginx /usr/sbin/nginx
COPY --from=base --chown=nonroot:nonroot /etc/nginx /etc/nginx
COPY --from=base --chown=nonroot:nonroot /var/log/nginx /var/log/nginx
COPY --from=base --chown=nonroot:nonroot /run/nginx /run/nginx

# Copy Nginx configuration
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# Copy Supervisor (installed in builder stage)
COPY --from=base --chown=nonroot:nonroot /usr/bin/supervisord /usr/bin/supervisord
COPY --from=base --chown=nonroot:nonroot /usr/lib/python3.* /usr/lib/python3.* || true
COPY --from=base --chown=nonroot:nonroot /usr/local/lib/python3.* /usr/local/lib/python3.* || true

# Copy Supervisor configuration
COPY docker/php/supervisord.conf /etc/supervisord.conf

# Copy entrypoint and start scripts (will be executed via Python)
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
COPY docker/php/start.sh /usr/local/bin/start.sh

# Fix Nginx config to use 127.0.0.1:9000 instead of php:9000
# Note: We can't use sed in Distroless, so we'll handle this in start.sh

# Copy dependencies from builder
COPY --from=builder --chown=nonroot:nonroot /var/www/html/vendor ./vendor

# Copy application files (excluding vendor, logs, cache - handled by .dockerignore)
COPY --chown=nonroot:nonroot api/ ./

# Create necessary directories (Distroless doesn't have mkdir, so we do it in entrypoint)
# But we need to create them here for proper ownership
WORKDIR /var/www/html

# Configure Opcache for production (copy from base)
COPY --from=base --chown=nonroot:nonroot /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# Healthcheck for production (uses PORT env var or defaults to 80)
# Note: Distroless doesn't have wget, so we use a simple HTTP check via Python
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
  CMD ["/usr/bin/python3", "-c", "import urllib.request; urllib.request.urlopen('http://localhost:80/up')"]

# Expose port (Railway will set PORT env var)
EXPOSE 80

# Start supervisor via Python (vector form, no shell)
# Note: Supervisor needs to run entrypoint.sh first, but Distroless doesn't have shell
# We'll use a Python wrapper script instead
ENTRYPOINT ["/usr/bin/python3", "/usr/local/bin/start.py"]

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

# Create Supervisor configuration
RUN echo '[supervisord]' > /etc/supervisord.conf \
 && echo 'nodaemon=true' >> /etc/supervisord.conf \
 && echo 'user=root' >> /etc/supervisord.conf \
 && echo 'logfile=/var/log/supervisor/supervisord.log' >> /etc/supervisord.conf \
 && echo 'pidfile=/var/run/supervisord.pid' >> /etc/supervisord.conf \
 && echo '' >> /etc/supervisord.conf \
 && echo '[program:php-fpm]' >> /etc/supervisord.conf \
 && echo 'command=php-fpm -F' >> /etc/supervisord.conf \
 && echo 'stdout_logfile=/dev/stdout' >> /etc/supervisord.conf \
 && echo 'stdout_logfile_maxbytes=0' >> /etc/supervisord.conf \
 && echo 'stderr_logfile=/dev/stderr' >> /etc/supervisord.conf \
 && echo 'stderr_logfile_maxbytes=0' >> /etc/supervisord.conf \
 && echo 'autorestart=true' >> /etc/supervisord.conf \
 && echo 'startretries=3' >> /etc/supervisord.conf \
 && echo '' >> /etc/supervisord.conf \
 && echo '[program:nginx]' >> /etc/supervisord.conf \
 && echo 'command=nginx -g "daemon off;"' >> /etc/supervisord.conf \
 && echo 'stdout_logfile=/dev/stdout' >> /etc/supervisord.conf \
 && echo 'stdout_logfile_maxbytes=0' >> /etc/supervisord.conf \
 && echo 'stderr_logfile=/dev/stderr' >> /etc/supervisord.conf \
 && echo 'stderr_logfile_maxbytes=0' >> /etc/supervisord.conf \
 && echo 'autorestart=true' >> /etc/supervisord.conf \
 && echo 'startretries=3' >> /etc/supervisord.conf

# Fix Nginx config to use 127.0.0.1:9000 instead of php:9000
RUN sed -i 's/fastcgi_pass php:9000/fastcgi_pass 127.0.0.1:9000/' /etc/nginx/http.d/default.conf || true

WORKDIR /var/www/html

# Expose port 80
EXPOSE 80

# Start supervisor (manages both PHP-FPM and Nginx)
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]



#!/bin/sh
set -e

echo "Starting MovieMind API..."
echo "PORT=${PORT:-80}"
echo "APP_ENV=${APP_ENV:-production}"

# Render Nginx config so one template works in both local Docker and production image
NGINX_PORT=${PORT:-${NGINX_PORT:-80}}
PHP_FPM_UPSTREAM=${PHP_FPM_UPSTREAM:-127.0.0.1:9000}

if [ -f /etc/nginx/http.d/default.conf ]; then
    sed -i "s|\${NGINX_PORT}|${NGINX_PORT}|g" /etc/nginx/http.d/default.conf || true
    sed -i "s|\${PHP_FPM_UPSTREAM}|${PHP_FPM_UPSTREAM}|g" /etc/nginx/http.d/default.conf || true
    echo "✅ Nginx config rendered with PORT=${NGINX_PORT} and PHP_FPM_UPSTREAM=${PHP_FPM_UPSTREAM}"
fi

# Verify Laravel public directory exists
if [ ! -d /var/www/html/public ]; then
    echo "❌ ERROR: /var/www/html/public directory not found!"
    exit 1
fi
echo "✅ Laravel public directory verified"

# Ensure storage and bootstrap/cache directories exist and have correct permissions
# Run as root to set permissions, then switch to app user
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
mkdir -p bootstrap/cache
chown -R app:app storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
echo "✅ Storage and bootstrap/cache permissions set"

# Run entrypoint script for setup (migrations, cache, etc.)
# Entrypoint runs setup tasks and then executes the command passed to it
if [ -f /usr/local/bin/entrypoint.sh ]; then
    echo "Running entrypoint setup..."
    # Entrypoint will run setup and then exec the supervisor command
    exec /usr/local/bin/entrypoint.sh /usr/bin/supervisord -c /etc/supervisord.conf
else
    # Fallback: start supervisor directly if entrypoint is not available
    echo "Starting Supervisor..."
    echo "Nginx will listen on port ${NGINX_PORT}"
    echo "PHP-FPM will listen on 127.0.0.1:9000"
    exec /usr/bin/supervisord -c /etc/supervisord.conf
fi


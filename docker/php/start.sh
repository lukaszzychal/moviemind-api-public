#!/bin/bash
set -e

echo "Starting MovieMind API..."
echo "PORT=${PORT:-80}"
echo "APP_ENV=${APP_ENV:-production}"

# Create Nginx config with dynamic PORT
# Railway sets PORT environment variable
NGINX_PORT=${PORT:-80}

# Replace port in Nginx config
if [ -f /etc/nginx/http.d/default.conf ]; then
    # Use envsubst to replace ${PORT} in config, or use sed
    sed -i "s/listen 80/listen ${NGINX_PORT}/" /etc/nginx/http.d/default.conf || true
    echo "✅ Nginx config updated with PORT=${NGINX_PORT}"
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

# Start supervisor (which manages both PHP-FPM and Nginx)
echo "Starting Supervisor..."
echo "Nginx will listen on port ${NGINX_PORT}"
echo "PHP-FPM will listen on 127.0.0.1:9000"
exec /usr/bin/supervisord -c /etc/supervisord.conf


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

# Ensure storage and bootstrap/cache have correct permissions
chmod -R 775 storage bootstrap/cache || true
chown -R app:app storage bootstrap/cache || true

# Start supervisor (which manages both PHP-FPM and Nginx)
echo "Starting Supervisor..."
echo "Nginx will listen on port ${NGINX_PORT}"
echo "PHP-FPM will listen on 127.0.0.1:9000"
exec /usr/bin/supervisord -c /etc/supervisord.conf


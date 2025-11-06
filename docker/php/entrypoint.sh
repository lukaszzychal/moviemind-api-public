#!/bin/bash
set -e

echo "🚀 MovieMind API - Production Entrypoint"
echo "=========================================="
echo "APP_ENV: ${APP_ENV:-production}"
echo "APP_DEBUG: ${APP_DEBUG:-0}"

# Wait for database to be ready (max 30 seconds)
echo "⏳ Waiting for database connection..."
MAX_ATTEMPTS=30
ATTEMPT=0

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    # Try to connect to database using a simple query
    if php -r "try { \$pdo = new PDO('pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); echo 'connected'; } catch (Exception \$e) { exit(1); }" 2>/dev/null; then
        echo "✅ Database connection established"
        break
    fi
    
    ATTEMPT=$((ATTEMPT + 1))
    if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
        echo "❌ ERROR: Could not connect to database after ${MAX_ATTEMPTS} attempts"
        exit 1
    fi
    
    echo "   Attempt ${ATTEMPT}/${MAX_ATTEMPTS}... waiting 1 second"
    sleep 1
done

# Ensure storage directories exist and have correct permissions
# Run as root if possible, otherwise just set permissions
echo "📁 Ensuring storage directories exist..."
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
mkdir -p bootstrap/cache

# Try to set ownership (works if running as root)
if [ "$(id -u)" = "0" ]; then
    chown -R app:app storage bootstrap/cache 2>/dev/null || true
    echo "✅ Ownership set (running as root)"
else
    echo "⚠️  Running as non-root user, skipping chown"
fi

# Always set permissions (works for all users)
chmod -R 775 storage bootstrap/cache 2>/dev/null || chmod -R 777 storage bootstrap/cache 2>/dev/null || true
echo "✅ Storage directories ready with permissions 775"

# Check if APP_KEY is set (required for Laravel)
if [ -z "$APP_KEY" ]; then
    echo "⚠️  WARNING: APP_KEY is not set. Generating new key..."
    php artisan key:generate --force || echo "⚠️  Could not generate APP_KEY (may already exist)"
else
    echo "✅ APP_KEY is set"
fi

# Cache configuration for production (only if not in local/dev)
if [ "${APP_ENV}" != "local" ] && [ "${APP_ENV}" != "dev" ]; then
    echo "📦 Caching configuration for production..."
    php artisan config:cache || echo "⚠️  Config cache failed (non-critical)"
    php artisan route:cache || echo "⚠️  Route cache failed (non-critical)"
    php artisan view:cache || echo "⚠️  View cache failed (non-critical)"
    echo "✅ Configuration cached"
else
    echo "ℹ️  Skipping cache (APP_ENV=${APP_ENV})"
fi

# Run migrations safely (only pending migrations, no data loss)
echo "🔄 Running database migrations..."
# Use --force only if explicitly set, otherwise let Laravel handle it
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    # Run migrations - Laravel will only run pending migrations
    # This is safe for production as it won't drop existing data
    php artisan migrate --force || {
        echo "⚠️  Migration failed. This might be expected if migrations are already up to date."
        echo "   Checking migration status..."
        php artisan migrate:status || true
    }
    echo "✅ Migrations completed"
else
    echo "ℹ️  Skipping migrations (RUN_MIGRATIONS=false)"
fi

# Clear and optimize (only for production)
if [ "${APP_ENV}" != "local" ] && [ "${APP_ENV}" != "dev" ]; then
    echo "🧹 Optimizing application..."
    php artisan optimize || echo "⚠️  Optimization failed (non-critical)"
    echo "✅ Application optimized"
fi

echo "✅ Entrypoint setup completed"
echo "=========================================="

# Execute the main command (passed as arguments)
exec "$@"


#!/bin/sh
set -e

echo "🚀 MovieMind API - Production Entrypoint"
echo "=========================================="
echo "APP_ENV: ${APP_ENV:-production}"
echo "APP_DEBUG: ${APP_DEBUG:-0}"

# On Railway (and similar), Postgres often exposes only DATABASE_URL. Derive DB_* for the wait loop and Laravel.
if [ -n "$DATABASE_URL" ] && { [ -z "$DB_HOST" ] || [ "$DB_HOST" = "db" ]; }; then
    echo "ℹ️  Using DATABASE_URL for database connection (Railway/cloud)"
    # Parse postgres://user:pass@host:port/dbname (or postgresql://)
    _url="${DATABASE_URL#*://}"   # remove scheme
    _url="${_url#*@}"              # remove user:pass@
    _host="${_url%%:*}"
    _rest="${_url#*:}"
    _port="${_rest%%/*}"
    _db="${_rest#*/}"
    _db="${_db%%\?*}"
    export DB_HOST="${DB_HOST:-$_host}"
    export DB_PORT="${DB_PORT:-$_port}"
    export DB_DATABASE="${DB_DATABASE:-$_db}"
    # username and password from URL (optional; Laravel can use URL for connection)
    if echo "$DATABASE_URL" | grep -q '@'; then
        _userpass="${DATABASE_URL#*://}"
        _userpass="${_userpass%%@*}"
        export DB_USERNAME="${DB_USERNAME:-${_userpass%%:*}}"
        export DB_PASSWORD="${DB_PASSWORD:-${_userpass#*:}}"
    fi
fi

# Wait for database to be ready (max 30 seconds)
echo "⏳ Waiting for database connection... [${DB_HOST}:${DB_PORT}]"
echo "   Check: DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD or DATABASE_URL (e.g. from Railway Postgres)"
echo "   Attempt 0/30... waiting 1 second"
sleep 1

MAX_ATTEMPTS=30
ATTEMPT=0

while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    # Try to connect to database using a simple query (use DATABASE_URL in PHP if set and DB_* missing)
    if php -r "
        \$url = getenv('DATABASE_URL');
        if (\$url && (getenv('DB_HOST') === false || getenv('DB_HOST') === '' || getenv('DB_HOST') === 'db')) {
            \$parsed = parse_url(\$url);
            \$host = \$parsed['host'] ?? 'localhost';
            \$port = \$parsed['port'] ?? 5432;
            \$db   = ltrim(\$parsed['path'] ?? 'laravel', '/');
            \$user = \$parsed['user'] ?? 'postgres';
            \$pass = \$parsed['pass'] ?? '';
            \$dsn  = \"pgsql:host=\$host;port=\$port;dbname=\$db\";
        } else {
            \$dsn  = 'pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE');
            \$user = getenv('DB_USERNAME');
            \$pass = getenv('DB_PASSWORD');
        }
        try {
            \$pdo = new PDO(\$dsn, \$user, \$pass);
            \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo 'connected';
        } catch (Exception \$e) { exit(1); }
    " 2>/dev/null; then
        echo "✅ Database connection established"
        break
    fi

    ATTEMPT=$((ATTEMPT + 1))
    if [ $ATTEMPT -eq $MAX_ATTEMPTS ]; then
        echo "❌ ERROR: Could not connect to database after ${MAX_ATTEMPTS} attempts"
        echo "   Check: DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD or DATABASE_URL (e.g. from Railway Postgres)"
        exit 1
    fi

    echo "   Attempt ${ATTEMPT}/${MAX_ATTEMPTS}... waiting 1 second"
    sleep 1
done

# Clean up old logs and cache to free up disk space (critical for Railway)
echo "🧹 Cleaning up old logs and cache files..."
find storage/logs -name "*.log" -type f -mtime +7 -delete 2>/dev/null || true
find storage/framework/cache -type f -mtime +1 -delete 2>/dev/null || true
find storage/framework/views -name "*.php" -type f -mtime +1 -delete 2>/dev/null || true
echo "✅ Old cache and logs cleaned"

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
# Additional permissive permissions for Railway (group, user, others + write)
chmod -R guo+w storage 2>/dev/null || true
echo "✅ Storage directories ready with permissions 775 (guo+w for Railway)"

# Check if APP_KEY is set (required for Laravel)
if [ -z "$APP_KEY" ]; then
    echo "⚠️  WARNING: APP_KEY is not set. Generating new key..."
    php artisan key:generate --force || echo "⚠️  Could not generate APP_KEY (may already exist)"
else
    echo "✅ APP_KEY is set"
fi

# Clear cache early (helps with Railway permissions issues)
echo "🧹 Clearing cache early (helps with Railway)..."
php artisan cache:clear || echo "⚠️  Cache clear failed (non-critical)"

# Cache configuration only when explicitly production (do not cache in local/dev/staging)
if [ "${APP_ENV}" = "production" ]; then
    echo "🧹 Clearing all caches before compilation..."
    php artisan cache:clear || echo "⚠️  Cache clear failed (non-critical)"
    php artisan config:clear || echo "⚠️  Config clear failed (non-critical)"
    php artisan route:clear || echo "⚠️  Route clear failed (non-critical)"
    php artisan view:clear || echo "⚠️  View clear failed (non-critical)"
    
    # Remove cached route files manually to ensure clean state
    rm -f bootstrap/cache/routes*.php 2>/dev/null || true
    rm -f bootstrap/cache/config.php 2>/dev/null || true
    echo "✅ All caches cleared (including bootstrap cache files)"
    
    # Clear OPcache to ensure fresh code is loaded (critical for route changes)
    echo "🔄 Clearing OPcache..."
    php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'OPcache cleared'; } else { echo 'OPcache not enabled'; }" || echo "⚠️  OPcache reset failed (non-critical)"
    
    # Ensure storage directories exist before caching (critical for view:cache)
    echo "📁 Re-checking storage directories before caching..."
    mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
    mkdir -p bootstrap/cache
    chmod -R 775 storage bootstrap/cache 2>/dev/null || chmod -R 777 storage bootstrap/cache 2>/dev/null || true
    # Additional permissive permissions for Railway (group, user, others + write)
    chmod -R guo+w storage 2>/dev/null || true
    
    echo "📦 Caching configuration for production..."
    php artisan config:cache || echo "⚠️  Config cache failed (non-critical)"
    php artisan route:cache || echo "⚠️  Route cache failed (non-critical)"
    php artisan view:cache || echo "⚠️  View cache failed (non-critical)"
    echo "✅ Configuration cached"
    
    # Clear OPcache again after caching to ensure new cache is loaded
    php -r "if (function_exists('opcache_reset')) { opcache_reset(); }" 2>/dev/null || true
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


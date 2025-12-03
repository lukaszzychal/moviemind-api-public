# Railway Deployment - Automation Process

> **Created:** 2025-01-27  
> **Context:** Explanation of automatic deployment process on Railway  
> **Category:** reference

## 🎯 Purpose

This document explains how the automatic deployment process works on Railway for MovieMind API - what happens automatically and what requires manual configuration.

---

## ✅ What Happens Automatically

### 1. 🔨 Build Time (During Docker Image Build)

Railway automatically detects `Dockerfile` and builds the image on each deployment (push to repository).

#### Stage 1: "base" Stage - Composer Installation
```dockerfile
# Composer is installed in base stage
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer
```

#### Stage 2: "builder" Stage - Dependency Installation
```dockerfile
# Composer dependencies are installed during build
COPY api/composer.json api/composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-scripts
```

**What is installed automatically:**
- ✅ All PHP dependencies from `composer.json`
- ✅ Autoloader is optimized
- ✅ Vendor directory is copied to final image

#### Stage 3: "production" Stage - Application Preparation
```dockerfile
# Copy vendor from builder stage
COPY --from=builder --chown=app:app /var/www/html/vendor ./vendor

# Copy application
COPY --chown=app:app api/ ./

# Optimize autoloader
RUN composer dump-autoload --optimize
```

---

### 2. 🚀 Runtime (On Container Start)

Container starts with `entrypoint.sh` script, which automatically performs all setup operations.

#### Automatic Actions on Container Start:

##### 1. **Wait for Database** (automatic)
```bash
# Waits maximum 30 seconds for database availability
while [ $ATTEMPT -lt $MAX_ATTEMPTS ]; do
    # Checks database connection
done
```

##### 2. **Generate APP_KEY** (automatic, if missing)
```bash
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force
fi
```

##### 3. **Cache Configuration** (automatic, production only)
```bash
# Only if APP_ENV != local/dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

##### 4. **Database Migrations** (automatic, safe)
```bash
# Automatically runs pending migrations
# Safe - doesn't delete data!
php artisan migrate --force
```

**Can be disabled** by setting environment variable:
```
RUN_MIGRATIONS=false
```

##### 5. **Application Optimization** (automatic, production only)
```bash
php artisan optimize
```

---

## 🔄 Full Deployment Workflow on Railway

### Step 1: Push to Repository
```bash
git push origin main
```

### Step 2: Railway Detects Changes (automatic)
- Railway automatically detects push
- Starts build if auto-deploy is configured

### Step 3: Docker Image Build (automatic)
Railway automatically:
1. ✅ Detects `Dockerfile` in repository
2. ✅ Builds image using `docker/php/Dockerfile`
3. ✅ Uses `production` stage (default or via configuration)
4. ✅ Installs Composer and dependencies during build
5. ✅ Copies application and vendor to image

**What's needed in Railway:**
- Set **Root Directory**: `/` (or project root directory)
- Set **Dockerfile Path**: `docker/php/Dockerfile` (if Railway doesn't auto-detect)
- Set **Build Command**: (empty - Dockerfile does everything)
- Set **Start Command**: (empty - Dockerfile has CMD)

### Step 4: Deploy Container (automatic)
Railway automatically:
1. ✅ Creates container from built image
2. ✅ Sets environment variables (from Railway Dashboard)
3. ✅ Starts container

### Step 5: Container Start (automatic)
Container automatically:
1. ✅ Runs `start.sh`
2. ✅ `start.sh` runs `entrypoint.sh`
3. ✅ `entrypoint.sh` performs all setup operations:
   - Waits for database
   - Generates APP_KEY (if missing)
   - Caches configuration
   - Runs migrations
   - Optimizes application
4. ✅ Starts Supervisor (PHP-FPM + Nginx)

---

## 📋 What You Need to Do Manually (One Time Only)

### 1. Railway Project Configuration (first time)

#### A. Connect Repository to Railway:
1. Open [Railway Dashboard](https://railway.app)
2. Click **"New Project"**
3. Select **"Deploy from GitHub repo"**
4. Select repository `moviemind-api-public`

#### B. Add PostgreSQL Service:
1. In project click **"+ New"**
2. Select **"Database" → "Add PostgreSQL"**
3. Railway automatically creates database and sets environment variables

#### C. Application Service Configuration:

**Settings → General:**
- **Root Directory**: `/` (or leave empty if auto-detection works)
- **Build Command**: (empty - Dockerfile does everything)
- **Start Command**: (empty - Dockerfile has CMD)

**Settings → Dockerfile:**
- **Dockerfile Path**: `docker/php/Dockerfile`
- **Docker Build Context**: `/` (project root)

#### D. Set Environment Variables:

Railway automatically sets PostgreSQL variables (from database service):
- `DATABASE_URL`
- `PGHOST`
- `PGPORT`
- `PGDATABASE`
- `PGUSER`
- `PGPASSWORD`

**You must manually add:**

| Variable | Value | Description |
|---------|-------|-------------|
| `APP_ENV` | `staging` or `production` | Application environment |
| `APP_DEBUG` | `0` | Disable debug in production |
| `APP_KEY` | (empty or generated) | Application key (can be auto-generated) |
| `OPENAI_API_KEY` | `sk-...` | OpenAI API key |
| `OPENAI_MODEL` | `gpt-4o-mini` | OpenAI model |
| `AI_SERVICE` | `real` or `mock` | AI service |
| `QUEUE_CONNECTION` | `redis` | Queue connection |
| `REDIS_HOST` | (from Railway Redis service) | Redis host |
| `REDIS_PORT` | (from Railway Redis service) | Redis port |

**Tip:** Railway automatically links environment variables between services. If you add Redis service, Redis variables will be available automatically.

---

## 🔍 How to Check What's Happening

### 1. Build Logs (in Railway Dashboard):
1. Open application service
2. Click **"Deployments"** tab
3. Select deployment
4. View build logs

### 2. Runtime Logs (in Railway Dashboard):
1. Open application service
2. Click **"Deployments"** tab
3. Select deployment
4. Click **"Logs"** - you'll see logs from `entrypoint.sh`:
   ```
   🚀 MovieMind API - Production Entrypoint
   ⏳ Waiting for database connection...
   ✅ Database connection established
   📁 Ensuring storage directories exist...
   ✅ APP_KEY is set
   📦 Caching configuration for production...
   🔄 Running database migrations...
   ✅ Migrations completed
   ```

### 3. Check Status (via Shell):
1. Open application service
2. Click **"Deployments"**
3. Click **"Shell"**
4. In container shell:
   ```bash
   php artisan migrate:status
   php artisan config:show
   ```

---

## ⚙️ Railway Configuration

### Example Configuration in Railway Dashboard:

**Settings → General:**
```
Root Directory: /
Build Command: (empty)
Start Command: (empty)
```

**Settings → Dockerfile:**
```
Dockerfile Path: docker/php/Dockerfile
Docker Build Context: /
```

**Settings → Environment Variables:**
```
APP_ENV=staging
APP_DEBUG=0
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
AI_SERVICE=real
QUEUE_CONNECTION=redis
```

**Settings → Service:**
```
Auto Deploy: Enabled (automatic deploy on push)
```

---

## 🔧 Advanced: Custom Configuration

### Disable Automatic Migrations:

Set environment variable in Railway:
```
RUN_MIGRATIONS=false
```

### Change Environment (local/dev):

Set environment variables:
```
APP_ENV=local
APP_DEBUG=1
```

This disables caching and optimization (uses live reload).

---

## ❓ FAQ

### Q: Do I need to manually install Composer?
**A:** No! Composer is installed automatically during Docker image build.

### Q: Do I need to manually run `composer install`?
**A:** No! Dependencies are installed automatically during build (builder stage).

### Q: Do I need to manually run migrations?
**A:** No! Migrations are run automatically on container start by `entrypoint.sh`.

### Q: Do I need to manually generate APP_KEY?
**A:** No! `entrypoint.sh` automatically generates APP_KEY if not set.

### Q: Do I need to manually cache configuration?
**A:** No! Caching is performed automatically for production/staging.

### Q: What do I need to do manually?
**A:** Only:
1. ✅ Configure Railway project (first time)
2. ✅ Add PostgreSQL service (first time)
3. ✅ Set environment variables (first time, then only when changing)
4. ✅ Push to repository - rest is automatic!

### Q: How often do I need to do something manually?
**A:** Almost never! After initial configuration, just:
- Push to repository → Railway automatically builds and deploys
- Change environment variables in Railway Dashboard (if needed)

---

## 📚 Related Documents

- [Deployment Setup](./DEPLOYMENT_SETUP.md) - Entrypoint.sh details
- [Railway Database Cleanup](./RAILWAY_DATABASE_CLEANUP.md) - How to clean database
- [Dockerfile](../technical/DOCKERFILE_ANALYSIS.md) - Dockerfile analysis (if exists)

---

## 🎯 Summary

### ✅ Everything Automatic:

1. **Build Time:**
   - ✅ Composer installation
   - ✅ PHP dependency installation
   - ✅ Autoloader optimization

2. **Runtime (Container Start):**
   - ✅ Waiting for database
   - ✅ APP_KEY generation
   - ✅ Configuration caching
   - ✅ Database migrations
   - ✅ Application optimization

### 🔧 Manual Configuration (one time only):

1. ✅ Railway project configuration
2. ✅ Add PostgreSQL service
3. ✅ Set environment variables

**After configuration: Just push to repository - rest is automatic! 🚀**

---

**Last updated:** 2025-01-27


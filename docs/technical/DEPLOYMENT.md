# MovieMind API - Deployment Guide

> **For:** DevOps, System Administrators, Developers  
> **Last Updated:** 2026-01-21  
> **Status:** Portfolio/Demo Project

---

## 🎯 Overview

This document provides deployment instructions for MovieMind API across different environments (local, staging, production).

**Note:** This is a portfolio/demo project. For production deployment, see [Production Deployment](#production-deployment).

---

## 🐳 Local Development (Docker)

### Prerequisites

- **Docker** 20.10+
- **Docker Compose** 2.0+
- **OpenAI API Key** (optional, can use mock mode)

### Quick Start

1. **Clone Repository**
   ```bash
   git clone https://github.com/lukaszzychal/moviemind-api-public.git
   cd moviemind-api-public
   ```

2. **Environment Setup**
   ```bash
   cp env/local.env.example api/.env
   # Edit api/.env and add your OpenAI API key (optional)
   ```

3. **Start Services**
   ```bash
   docker compose up -d --build
   ```

4. **Install Dependencies**
   ```bash
   docker compose exec php composer install
   ```

5. **Generate Application Key**
   ```bash
   docker compose exec php php artisan key:generate
   ```

6. **Run Migrations**
   ```bash
   docker compose exec php php artisan migrate --seed
   ```

7. **Access Application**
   - API: http://localhost:8000
   - Horizon: http://localhost:8001

---

## 🔧 Environment Configuration

### Required Environment Variables

**Application:**
```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_KEY=base64:... (generated via artisan key:generate)
```

**Database:**
```env
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=moviemind
DB_USERNAME=moviemind
DB_PASSWORD=moviemind
```

**Cache/Queue:**
```env
REDIS_HOST=redis
REDIS_PORT=6379
QUEUE_CONNECTION=redis
```

**AI Service:**
```env
AI_SERVICE=real  # or 'mock' for testing
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
OPENAI_URL=https://api.openai.com/v1/chat/completions
```

**External APIs:**
```env
TMDB_API_KEY=... (optional, for verification)
TVMAZE_API_KEY=... (not required, public API)
```

### Environment Files

- `env/local.env.example` - Local development template
- `env/staging.env.example` - Staging environment template
- `env/production.env.example` - Production environment template

---

## 🗄️ Database Setup

### PostgreSQL

**Version:** 15+

**Configuration:**
- Host: `db` (Docker) or `localhost` (native)
- Port: `5432`
- Database: `moviemind`
- User: `moviemind`
- Password: `moviemind` (change in production!)

**Migrations:**
```bash
docker compose exec php php artisan migrate
```

**Seeders:**
```bash
docker compose exec php php artisan db:seed
```

**Seeders Available:**
- `SubscriptionPlanSeeder` - Creates Free, Pro, Enterprise plans
- `ApiKeySeeder` - Creates demo API keys (non-production only)
- `AdminUserSeeder` - Creates default admin user
- `GenreSeeder` - Creates movie genres
- `MovieSeeder` - Creates sample movies
- `PeopleSeeder` - Creates sample people

---

## 🔴 Redis Setup

### Configuration

**Version:** 7+

**Purpose:**
- Application cache
- Session storage
- Queue backend

**Configuration:**
```env
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0
```

**Cache Configuration:**
- Cache driver: `redis`
- Session driver: `redis`
- Queue connection: `redis`

---

## ⚙️ Queue Setup (Laravel Horizon)

### Configuration

**Service:** Laravel Horizon

**Access:** http://localhost:8001 (admin only)

**Configuration:**
```env
QUEUE_CONNECTION=redis
HORIZON_BALANCE=simple
HORIZON_MAX_PROCESSES=10
```

### Starting Horizon

**Via Docker:**
```bash
docker compose up -d horizon
```

**Via Artisan:**
```bash
docker compose exec php php artisan horizon
```

### Monitoring

- Dashboard: http://localhost:8001
- Metrics: Job counts, processing times, failures
- Failed Jobs: Retry, delete, view details

---

## 🚀 Staging Deployment

### Requirements

- Docker & Docker Compose
- PostgreSQL 15+
- Redis 7+
- Domain name (optional)
- SSL certificate (for HTTPS)

### Steps

1. **Environment Setup**
   ```bash
   cp env/staging.env.example api/.env
   # Edit api/.env with staging values
   ```

2. **Build & Start**
   ```bash
   docker compose -f docker-compose.staging.yml up -d --build
   ```
   (Service name is `php`; migrations run automatically via entrypoint.)

3. **Run Migrations (if needed manually)**
   ```bash
   docker compose -f docker-compose.staging.yml exec php php artisan migrate --force
   ```

4. **Seed Data (if needed)**
   ```bash
   docker compose -f docker-compose.staging.yml exec php php artisan db:seed --class=SubscriptionPlanSeeder
   ```
   (Horizon runs inside the same container via Supervisor; no separate step.)

---

## 🏭 Production Deployment

### Requirements

- **Infrastructure:**
  - PostgreSQL 15+ (with read replicas for HA)
  - Redis 7+ (with cluster for HA)
  - Nginx (reverse proxy)
  - SSL/TLS certificates
  - Monitoring (APM, error tracking)

- **Security:**
  - HTTPS only (TLS 1.3)
  - API key rotation policies
  - Rate limiting per IP
  - DDoS protection
  - Security headers

- **Licenses:**
  - Commercial TMDB license (if monetizing)
  - OpenAI API key (production)

### Deployment Steps

1. **Environment Setup**
   ```bash
   cp env/production.env.example api/.env
   # Edit api/.env with production values
   # Set APP_ENV=production
   # Set APP_DEBUG=false
   ```

2. **Build & Deploy**
   ```bash
   docker compose -f docker-compose.production.yml up -d --build
   ```
   (Service name is `php`. Migrations and config/route/view cache run automatically in entrypoint when APP_ENV=production.)

3. **Run Migrations (if needed manually)**
   ```bash
   docker compose -f docker-compose.production.yml exec php php artisan migrate --force
   ```

4. **Optimize Application (optional; entrypoint does this when APP_ENV=production)**
   ```bash
   docker compose -f docker-compose.production.yml exec php php artisan config:cache
   docker compose -f docker-compose.production.yml exec php php artisan route:cache
   docker compose -f docker-compose.production.yml exec php php artisan view:cache
   ```
   (Horizon runs inside the same container via Supervisor; no separate step.)

### Production Checklist

- [ ] Environment variables configured
- [ ] Database migrations run
- [ ] SSL certificates installed
- [ ] Security headers (app middleware; HSTS when behind HTTPS proxy)
- [ ] Monitoring setup
- [ ] Backup strategy configured (see [BACKUP.md](../deployment/BACKUP.md))
- [ ] Horizon running
- [ ] Health checks passing
- [ ] Rate limiting configured
- [ ] API keys created (via Admin API)

---

## 📊 Monitoring

### Health Checks

**Endpoints:**
- `/api/v1/health/openai` - OpenAI API status
- `/api/v1/health/tmdb` - TMDB API status
- `/api/v1/health/tvmaze` - TVmaze API status
- `/api/v1/health/db` - Database status
- `/api/v1/health/instance` - Instance information

### Laravel Horizon

**Dashboard:** http://localhost:8001

**Metrics:**
- Job counts (pending, processing, completed, failed)
- Processing times
- Queue throughput
- Failed job details

### Logging

**Location:** `api/storage/logs/`

**Channels:**
- `daily` - Application logs (rotated daily)
- `horizon` - Queue job logs
- `single` - Single log file

**Log Levels:**
- `debug` - Development
- `info` - Production (default)
- `warning` - Warnings
- `error` - Errors
- `critical` - Critical issues

---

## 🔄 Backup & Recovery

**Detailed backup and restore steps:** [BACKUP.md](../deployment/BACKUP.md).

### Database Backup

**Manual Backup:**
```bash
docker compose exec db pg_dump -U moviemind moviemind > backup.sql
```

**Automated Backup:**
- Schedule daily backups
- Retain backups for 30 days
- Test restore procedures (see BACKUP.md)

### Cache Backup

**Redis Persistence:**
- RDB snapshots (periodic)
- AOF (append-only file) for durability

**Note:** Cache is not critical data - can be rebuilt

### Disaster Recovery

**RTO (Recovery Time Objective):** < 4 hours
**RPO (Recovery Point Objective):** < 24 hours

**Recovery Steps:**
1. Restore database from backup
2. Restart services
3. Verify health checks
4. Monitor for issues

---

## 🔒 Security Hardening

### Production Security

1. **HTTPS Only**
   - TLS 1.3
   - HSTS headers
   - Certificate auto-renewal

2. **API Key Security**
   - Keys stored as hashed values
   - Rotation policies
   - Revocation support

3. **Rate Limiting**
   - Per API key
   - Per IP (additional layer)
   - DDoS protection

4. **Security Headers** (set by `SecurityHeadersMiddleware` in the application)
   - X-Content-Type-Options: nosniff
   - X-Frame-Options: SAMEORIGIN
   - Referrer-Policy: strict-origin-when-cross-origin
   - Strict-Transport-Security (HSTS) when request is HTTPS
   - Add CSP at reverse proxy if you need a strict Content-Security-Policy

5. **Input Validation**
   - All inputs validated
   - SQL injection prevention
   - XSS prevention

---

## 📚 Related Documentation

- [Architecture](ARCHITECTURE.md) - System architecture
- [API Specification](API_SPECIFICATION.md) - API documentation
- [Integrations](INTEGRATIONS.md) - External API integrations
- [README](../../README.md) - Project overview

---

**Last Updated:** 2026-01-21  
**Status:** Portfolio/Demo Project

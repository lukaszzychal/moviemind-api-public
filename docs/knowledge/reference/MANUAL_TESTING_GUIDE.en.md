# Manual Testing Guide for Local Environment

> **Created:** 2025-11-21  
> **Context:** Detailed guide for manual testing of MovieMind API functionality in local environment  
> **Category:** reference

## 🎯 Goal

This document contains detailed instructions for manual testing of MovieMind API functionality in a local environment, with particular focus on testing the duplicate prevention mechanism.

---

## 📋 Use Cases Overview

This document contains instructions for testing the following use cases:

| #  | Use Case                                           | Description                                                                                    | Endpoint                      |
|----|----------------------------------------------------|------------------------------------------------------------------------------------------------|-------------------------------|
| 1  | **Concurrent Requests - Movie (GET)**               | Verify that parallel requests for the same movie slug return the same `job_id`                | `GET /api/v1/movies/{slug}`   |
| 2  | **Concurrent Requests - Movie (POST)**              | Verify slot management mechanism for `/generate` endpoint for movies                          | `POST /api/v1/generate`       |
| 3  | **Concurrent Requests - Person (GET)**              | Verify that parallel requests for the same person slug return the same `job_id`               | `GET /api/v1/people/{slug}`   |
| 4  | **Concurrent Requests - Person (POST)**             | Verify slot management mechanism for `/generate` endpoint for persons                         | `POST /api/v1/generate`       |
| 5  | **Log Verification - Single Dispatch**              | Confirm in logs that only one job is dispatched for concurrent requests                       | Application logs              |
| 6  | **Edge Case - Very Fast Concurrent Requests**       | Testing mechanism for 3+ parallel requests                                                     | `GET /api/v1/movies/{slug}`   |
| 7  | **Database Verification - No Duplicates**           | Check that there are no duplicates in the database (unique constraint)                        | PostgreSQL database           |
| 8  | **Job Status Test**                                 | Verify that `job_id` returned by API exists and its status can be checked                      | `GET /api/v1/jobs/{id}`       |
| 9  | **Generation with Default ContextTag**              | Verify that when `context_tag` is not provided, system uses default ContextTag                | `POST /api/v1/generate`       |
| 10 | **Generation with Specific ContextTag**             | Verify handling of specific ContextTag (e.g., "humorous") during description generation        | `POST /api/v1/generate`       |
| 11 | **Edge Case - Invalid ContextTag**                  | Verify handling of invalid ContextTag (fallback or validation error)                          | `POST /api/v1/generate`       |
| 12 | **Duplication - Different ContextTag (KEY)**        | Verify that concurrent requests with different ContextTag return different job_id and create different descriptions | `POST /api/v1/generate`       |
| 13 | **What happens when ContextTag is not in database** | Verify behavior when retrieving a movie without description with given ContextTag              | `GET /api/v1/movies/{slug}`   |

### Key Mechanisms Tested

- **Slot Management** - duplicate prevention mechanism through generation slots
- **Cache Operations** - cache operations for job and slot status
- **Event Handling** - verification that only one event is dispatched
- **Database Integrity** - checking record uniqueness in the database
- **Job Status Tracking** - tracking status of asynchronous jobs
- **ContextTag Management** - handling different ContextTags (DEFAULT, MODERN, CRITICAL, HUMOROUS) and preventing duplicates for different tags

---

## 🚀 Local Environment Setup (Docker)

### Step 1: Environment Preparation

#### 1.1. Copy the `.env` configuration file

```bash
# From the project root directory
cp env/local.env.example api/.env
```

#### 1.2. Edit the `api/.env` file (optional)

```bash
# Open the file in an editor to set environment variables (e.g., OPENAI_API_KEY)
# Not required for testing with mock AI
```

**Default values:**
- `AI_SERVICE=mock` - uses mock AI (no OpenAI key required)
- `OPENAI_API_KEY=` - optional, required only for `AI_SERVICE=real`

### Step 2: Starting Docker Containers

#### 2.1. Start all services

```bash
# From the project root directory
docker compose up -d --build
```

**What this does:**
- Builds Docker images (if needed)
- Starts all containers in the background (`-d`):
  - `moviemind-php` - PHP/Laravel application
  - `moviemind-nginx` - web server (port 8000)
  - `moviemind-db` - PostgreSQL (port 5433)
  - `moviemind-redis` - Redis (port 6379)
  - `moviemind-horizon` - Laravel Horizon (queue worker)

**Expected result:**
```bash
[+] Running 5/5
 ✔ Container moviemind-redis    Started
 ✔ Container moviemind-db        Started
 ✔ Container moviemind-php       Started
 ✔ Container moviemind-nginx     Started
 ✔ Container moviemind-horizon   Started
```

#### 2.2. Check container status

```bash
docker ps
```

**Expected result:** All containers should have status `Up`:
```
CONTAINER ID   IMAGE                    STATUS
xxx            moviemind-php            Up X seconds
xxx            moviemind-nginx          Up X seconds
xxx            moviemind-db             Up X seconds
xxx            moviemind-redis          Up X seconds
xxx            moviemind-horizon        Up X seconds
```

### Step 3: PHP Dependencies Installation

#### 3.1. Install Composer dependencies

```bash
docker compose exec php composer install
```

**Expected result:** PHP packages installed without errors.

### Step 4: Application Configuration

#### 4.1. Generate Laravel application key

```bash
docker compose exec php php artisan key:generate
```

**Expected result:** `Application key set successfully.`

#### 4.2. Run database migrations and seeders

```bash
docker compose exec php php artisan migrate --seed
```

**Expected result:**
```
Migration table created successfully.
Migrating: 2024_01_01_000001_create_movies_table
Migrated:  2024_01_01_000001_create_movies_table
...
Seeding: MovieSeeder
Seeding: ActorSeeder
...
Database seeded successfully.
```

### Step 5: Startup Verification

#### 5.1. Check if API is responding

```bash
curl -s http://localhost:8000/api/v1/health || echo "API not responding"
```

**Expected result:** Status `200 OK` or JSON response (if endpoint exists).

Alternatively:
```bash
curl -s -I http://localhost:8000 | head -1
```

**Expected result:** `HTTP/1.1 200 OK` or `HTTP/1.1 404 Not Found` (depending on routing configuration).

#### 5.2. Check Horizon logs (queue worker)

```bash
docker compose logs horizon | tail -20
```

**Expected result:** Horizon should be running:
```
Horizon started successfully.
Processing jobs from queue: default
```

#### 5.3. Check application logs

```bash
docker compose logs php | tail -20
```

**Expected result:** No critical errors.

### Step 6: Useful Commands

#### Stop containers

```bash
docker compose down
```

#### Stop and remove volumes (reset database)

```bash
docker compose down -v
```

**Warning:** This will delete all database data!

#### Restart containers

```bash
docker compose restart
```

#### Restart specific container

```bash
docker compose restart horizon
```

#### View live logs

```bash
# All containers
docker compose logs -f

# Specific container
docker compose logs -f horizon
docker compose logs -f php
```

#### Execute command in container

```bash
# Execute Artisan command
docker compose exec php php artisan route:list

# Open shell in container
docker compose exec php bash

# Check PHP version
docker compose exec php php -v
```

### Troubleshooting: Docker Startup Issues

#### Issue: Port 8000 already in use

**Symptoms:**
```
Error: Bind for 0.0.0.0:8000 failed: port is already allocated
```

**Solution:**
1. Find the process using port 8000:
   ```bash
   lsof -i :8000
   ```
2. Stop the process or change the port in `docker-compose.yml` (line 41: `"8000:80"` → `"8001:80"`)

#### Issue: Port 5433 already in use (PostgreSQL)

**Symptoms:**
```
Error: Bind for 0.0.0.0:5433 failed: port is already allocated
```

**Solution:**
1. Change the port in `docker-compose.yml` (line 91: `"5433:5432"` → `"5434:5432"`)
2. Update `DB_PORT` in `api/.env` if using external client

#### Issue: Containers won't start

**Symptoms:**
- Containers restart in a loop
- Errors in logs

**Solution:**
1. Check logs:
   ```bash
   docker compose logs
   ```
2. Check if `api/.env` file exists:
   ```bash
   ls -la api/.env
   ```
3. Check directory permissions:
   ```bash
   ls -la api/storage
   ls -la api/bootstrap/cache
   ```
4. Clean and restart:
   ```bash
   docker compose down -v
   docker compose up -d --build
   ```

#### Issue: Horizon is not running

**Symptoms:**
- No Horizon logs
- Jobs are not being processed

**Solution:**
1. Check if Horizon container is running:
   ```bash
   docker ps | grep horizon
   ```
2. Check logs:
   ```bash
   docker compose logs horizon
   ```
3. Restart Horizon:
   ```bash
   docker compose restart horizon
   ```

---

## 📋 Prerequisites

### Tools

1. **Docker and Docker Compose** - running
2. **API available** at `http://localhost:8000`
3. **Redis** - running (for cache)
4. **Horizon** - running (for queue jobs)
5. **PostgreSQL** - running (for database)
6. **CLI Tools:**
   - `curl` - for making HTTP requests
   - `jq` - optional, for parsing JSON (recommended)

### Status Check

```bash
# Check Docker containers status
docker ps

# Check Horizon status
docker logs moviemind-horizon | tail -20

# Check application logs
tail -50 api/storage/logs/laravel.log
```

**Expected result:** All containers running:
- `moviemind-php`
- `moviemind-nginx` (port 8000)
- `moviemind-redis` (port 6379)
- `moviemind-db` (PostgreSQL, port 5433)
- `moviemind-horizon`

---

## 🔧 Environment Setup

### Step 1: Activate Feature Flags

#### 1.1. Check flag status

```bash
curl -s -X GET "http://localhost:8000/api/v1/admin/flags" \
  -H "Accept: application/json" | jq '.data[] | select(.name | contains("ai_"))'
```

#### 1.2. Activate `ai_description_generation` (if inactive)

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/ai_description_generation" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"state":"on"}' | jq .
```

**Expected result:** `{"name": "ai_description_generation", "active": true}`

#### 1.3. Activate `ai_bio_generation` (if inactive)

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/ai_bio_generation" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"state":"on"}' | jq .
```

**Expected result:** `{"name": "ai_bio_generation", "active": true}`

---

## 🧪 Test 1: Concurrent Requests for Movie (GET /api/v1/movies/{slug})

### Goal

Verify that concurrent requests for the same slug return the same `job_id` (slot management mechanism).

### Steps

#### 1. Prepare unique slug

```bash
SLUG="test-movie-$(date +%s)"
echo "Testing slug: $SLUG"
```

#### 2. Execute first request

```bash
JOB1=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id // "ERROR"')
echo "Request 1 job_id: $JOB1"
```

**Expected result:**
- Status: `202 Accepted`
- Response contains: `job_id`, `status: "PENDING"`, `slug`
- Example: `"job_id": "7f8a7c8b-f6ac-442b-abf7-8418f0660dfc"`

#### 3. Execute second request (immediately after first)

```bash
sleep 0.1  # Short delay (100ms)
JOB2=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id // "ERROR"')
echo "Request 2 job_id: $JOB2"
```

**Expected result:**
- Status: `202 Accepted`
- `job_id` is **identical** to first request
- `JOB1 == JOB2`

#### 4. Verification

```bash
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ]; then
  echo "✅ SUCCESS: Both requests returned the same job_id"
else
  echo "❌ FAIL: Different job_ids or error"
fi
```

**Expected result:** `✅ SUCCESS: Both requests returned the same job_id`

#### 5. Check logs

```bash
docker logs moviemind-php 2>&1 | grep -E "QueueMovieGenerationAction|generation slot" | tail -5
```

**Expected result in logs:**
- Request 1: `"acquired generation slot"` → `"dispatched new job"`
- Request 2: `"reusing existing job"` (same job_id)

---

## 🧪 Test 2: Concurrent Requests for Movie (POST /api/v1/generate)

### Goal

Verify that concurrent requests through `/generate` endpoint return the same `job_id`.

### Steps

#### 1. Prepare unique slug

```bash
SLUG="test-generate-movie-$(date +%s)"
echo "Testing slug: $SLUG"
```

#### 2. Execute first request

```bash
JOB1=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"entity_type\":\"MOVIE\",\"entity_id\":\"$SLUG\"}" \
  | jq -r '.job_id // "ERROR"')
echo "Request 1 job_id: $JOB1"
```

**Expected result:**
- Status: `202 Accepted`
- Response contains: `job_id`, `status: "PENDING"`

#### 3. Execute second request

```bash
sleep 0.1
JOB2=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"entity_type\":\"MOVIE\",\"entity_id\":\"$SLUG\"}" \
  | jq -r '.job_id // "ERROR"')
echo "Request 2 job_id: $JOB2"
```

#### 4. Verification

```bash
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ]; then
  echo "✅ SUCCESS: Both requests returned the same job_id"
else
  echo "❌ FAIL: Different job_ids or error"
fi
```

---

## 🧪 Test 3: Concurrent Requests for Person (GET /api/v1/people/{slug})

### Goal

Verify that concurrent requests for Person return the same `job_id`.

### Note

Slug for Person must be in **2-4 words format** (e.g., `john-doe`, `mary-jane-watson`). Slug with single word or more than 4 words may be rejected by validator.

### Steps

#### 1. Activate feature flag (if inactive)

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/ai_bio_generation" \
  -H "Content-Type: application/json" \
  -d '{"state":"on"}' | jq .
```

#### 2. Prepare unique slug (format: 2-4 words)

```bash
SLUG="john-doe-$(date +%s | tail -c 4)"
echo "Testing slug: $SLUG"
```

#### 3. Execute first request

```bash
JOB1=$(curl -s -X GET "http://localhost:8000/api/v1/people/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id // .error // "ERROR"')
echo "Request 1: $JOB1"
```

**Expected result:**
- Status: `202 Accepted`
- Response contains: `job_id`

#### 4. Execute second request

```bash
sleep 0.1
JOB2=$(curl -s -X GET "http://localhost:8000/api/v1/people/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id // .error // "ERROR"')
echo "Request 2: $JOB2"
```

#### 5. Verification

```bash
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ] && [ "$JOB1" != "Person not found" ]; then
  echo "✅ SUCCESS: Both requests returned the same job_id"
else
  echo "❌ FAIL: Different job_ids or error"
fi
```

---

## 🧪 Test 4: Concurrent Requests for Person (POST /api/v1/generate)

### Goal

Verify that concurrent requests for Person through `/generate` endpoint return the same `job_id`.

### Steps

#### 1. Prepare unique slug (format: 2-4 words)

```bash
SLUG="jane-smith-$(date +%s | tail -c 4)"
echo "Testing slug: $SLUG"
```

#### 2. Execute first request

```bash
JOB1=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"entity_type\":\"PERSON\",\"entity_id\":\"$SLUG\"}" \
  | jq -r '.job_id // .error // "ERROR"')
echo "Request 1: $JOB1"
```

#### 3. Execute second request

```bash
sleep 0.1
JOB2=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"entity_type\":\"PERSON\",\"entity_id\":\"$SLUG\"}" \
  | jq -r '.job_id // .error // "ERROR"')
echo "Request 2: $JOB2"
```

#### 4. Verification

```bash
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ] && [ "$JOB1" != "Feature not available" ] && [ "$JOB1" != "Invalid slug format" ]; then
  echo "✅ SUCCESS: Both requests returned the same job_id"
else
  echo "❌ FAIL: Different job_ids or error"
fi
```

---

## 🧪 Test 5: Log Verification - Verify Only One Job is Dispatched

### Goal

Confirm in logs that only one job is dispatched for concurrent requests.

### Steps

#### 1. Check logs for Movie

```bash
docker logs moviemind-php 2>&1 | grep -E "QueueMovieGenerationAction.*dispatched|acquired generation slot|reusing existing job" | tail -10
```

**Expected result:**
- For each test: **one** `"dispatched new job"`
- Second request: `"reusing existing job"` (same job_id)

#### 2. Check logs for Person

```bash
docker logs moviemind-php 2>&1 | grep -E "QueuePersonGenerationAction.*dispatched|acquired generation slot|reusing existing job" | tail -10
```

**Expected result:** Analogous to Movie.

#### 3. Check logs directly in file

```bash
tail -50 api/storage/logs/laravel.log | grep -E "dispatched new job|reusing existing job|generation slot"
```

---

## 🧪 Test 6: Edge Case - Very Fast Concurrent Requests

### Goal

Verify that mechanism works for 3+ concurrent requests.

### Steps

#### 1. Execute 3 requests almost simultaneously

```bash
SLUG="rapid-test-$(date +%s)"
echo "Testing rapid concurrent requests: $SLUG"

# Request 1
JOB1=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id')

# Request 2 (immediately)
JOB2=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id')

# Request 3 (immediately)
JOB3=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id')

echo "Job 1: $JOB1"
echo "Job 2: $JOB2"
echo "Job 3: $JOB3"

# Verification
if [ "$JOB1" = "$JOB2" ] && [ "$JOB2" = "$JOB3" ] && [ "$JOB1" != "ERROR" ]; then
  echo "✅ SUCCESS: All 3 requests returned the same job_id"
else
  echo "❌ FAIL: Different job_ids"
fi
```

**Expected result:** All 3 requests return the same `job_id`.

---

## 🧪 Test 7: Database Verification - No Duplicates

### Goal

Verify that there are no duplicates in database (unique constraint works).

### Steps

#### 1. Check for duplicates in movies table

```bash
docker exec moviemind-db psql -U moviemind -d moviemind -c \
  "SELECT slug, COUNT(*) as count FROM movies GROUP BY slug HAVING COUNT(*) > 1;"
```

**Expected result:** No results (no duplicates).

#### 2. Check for duplicates in people table

```bash
docker exec moviemind-db psql -U moviemind -d moviemind -c \
  "SELECT slug, COUNT(*) as count FROM people GROUP BY slug HAVING COUNT(*) > 1;"
```

**Expected result:** No results (no duplicates).

---

## 🧪 Test 8: Job Status Test - Verify Job Exists

### Goal

Verify that job_id returned by API actually exists and its status can be checked.

### Steps

#### 1. Get job_id from previous test

```bash
SLUG="status-test-$(date +%s)"
JOB_ID=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id')
echo "Job ID: $JOB_ID"
```

#### 2. Check job status

```bash
curl -s -X GET "http://localhost:8000/api/v1/jobs/$JOB_ID" \
  -H "Accept: application/json" | jq .
```

**Expected result:**
- Status: `200 OK`
- Response contains: `job_id`, `status` (PENDING/IN_PROGRESS/DONE/FAILED), `entity`, `slug`

---

## 🧪 Test 9: Generation with Default ContextTag

### Goal

Verify that when `context_tag` is not provided in the request, the system uses the default ContextTag (DEFAULT, MODERN, CRITICAL, or HUMOROUS depending on existing descriptions).

### Steps

#### 1. Generate description without providing context_tag

```bash
SLUG="default-context-$(date +%s)"
curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\"
  }" | jq .
```

**Expected result:**
- Status: `202 Accepted`
- Response contains: `job_id`, `status: "PENDING"`, `slug`, `locale: "en-US"`
- `context_tag` may be null or default (DEFAULT) - depends on implementation

#### 2. Check job status and wait for completion

```bash
JOB_ID=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{\"entity_type\": \"MOVIE\", \"entity_id\": \"$SLUG\"}" | jq -r '.job_id')

# Wait for job completion (or check status)
sleep 5

curl -s -X GET "http://localhost:8000/api/v1/jobs/$JOB_ID" | jq .
```

#### 3. Check in database which ContextTag was used

```bash
docker exec moviemind-db psql -U moviemind -d moviemind -c "
  SELECT id, context_tag, locale 
  FROM movie_descriptions 
  WHERE movie_id = (SELECT id FROM movies WHERE slug = '$SLUG')
  ORDER BY created_at DESC 
  LIMIT 1;
"
```

**Expected result:**
- Should exist a description with context_tag = 'DEFAULT' (or first available from order: DEFAULT, MODERN, CRITICAL, HUMOROUS)

---

## 🧪 Test 10: Generation with Specific ContextTag (humorous)

### Goal

Verify that the system correctly handles a specific ContextTag during description generation.

### Steps

#### 1. Generate description with context_tag="humorous"

```bash
SLUG="humorous-context-$(date +%s)"
curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\",
    \"context_tag\": \"humorous\"
  }" | jq .
```

**Expected result:**
- Status: `202 Accepted`
- Response contains: `job_id`, `status: "PENDING"`, `context_tag: "humorous"`

#### 2. Check job status and wait for completion

```bash
JOB_ID=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{\"entity_type\": \"MOVIE\", \"entity_id\": \"$SLUG\", \"context_tag\": \"humorous\"}" | jq -r '.job_id')

sleep 5

curl -s -X GET "http://localhost:8000/api/v1/jobs/$JOB_ID" | jq .
```

#### 3. Check in database if ContextTag was saved

```bash
docker exec moviemind-db psql -U moviemind -d moviemind -c "
  SELECT id, context_tag, locale, LEFT(text, 50) as text_preview
  FROM movie_descriptions 
  WHERE movie_id = (SELECT id FROM movies WHERE slug = '$SLUG')
  ORDER BY created_at DESC 
  LIMIT 1;
"
```

**Expected result:**
- Should exist a description with context_tag = 'humorous'

---

## 🧪 Test 11: Edge Case - Invalid ContextTag

### Goal

Verify how the system handles invalid ContextTag (fallback to default or validation error).

### Steps

#### 1. Try to generate description with invalid context_tag

```bash
SLUG="invalid-context-$(date +%s)"
curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\",
    \"context_tag\": \"invalid-tag\"
  }" | jq .
```

**Expected result:**
- Status: `202 Accepted` (system may accept request but ignore invalid tag)
- OR Status: `422 Unprocessable Entity` (validation error if validation exists)

#### 2. Check which ContextTag was actually used

```bash
JOB_ID=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{\"entity_type\": \"MOVIE\", \"entity_id\": \"$SLUG\", \"context_tag\": \"invalid-tag\"}" | jq -r '.job_id')

sleep 5

# Check in database
docker exec moviemind-db psql -U moviemind -d moviemind -c "
  SELECT id, context_tag 
  FROM movie_descriptions 
  WHERE movie_id = (SELECT id FROM movies WHERE slug = '$SLUG')
  ORDER BY created_at DESC 
  LIMIT 1;
"
```

**Expected result:**
- ContextTag should be default (DEFAULT) or correct (if system normalizes/fixes)

---

## 🧪 Test 12: Duplication - Different ContextTag (KEY)

### Goal

Verify that concurrent requests with different ContextTag return different job_id and create different descriptions in the database. This is a key test of slot management mechanism for different ContextTag.

### Steps

#### 1. Generate parallel requests with different context_tag

```bash
SLUG="different-context-$(date +%s)"

# Request 1: modern
JOB_ID_1=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\",
    \"context_tag\": \"modern\"
  }" | jq -r '.job_id')

# Request 2: humorous (immediately after first)
JOB_ID_2=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\",
    \"context_tag\": \"humorous\"
  }" | jq -r '.job_id')

echo "Job ID 1 (modern): $JOB_ID_1"
echo "Job ID 2 (humorous): $JOB_ID_2"
```

**Expected result:**
- Both requests return status `202 Accepted`
- **Job ID 1 and Job ID 2 should be DIFFERENT** (different ContextTag = different slots)
- Both jobs should have status `PENDING`

#### 2. Check status of both jobs

```bash
echo "=== Job 1 (modern) ==="
curl -s -X GET "http://localhost:8000/api/v1/jobs/$JOB_ID_1" | jq .

echo "=== Job 2 (humorous) ==="
curl -s -X GET "http://localhost:8000/api/v1/jobs/$JOB_ID_2" | jq .
```

#### 3. Wait for job completion and check in database

```bash
sleep 10

docker exec moviemind-db psql -U moviemind -d moviemind -c "
  SELECT id, context_tag, locale, LEFT(text, 50) as text_preview
  FROM movie_descriptions 
  WHERE movie_id = (SELECT id FROM movies WHERE slug = '$SLUG')
  ORDER BY context_tag;
"
```

**Expected result:**
- Should exist **two descriptions** for the same movie:
  - One with `context_tag = 'modern'`
  - Second with `context_tag = 'humorous'`
- Both descriptions should have the same `locale` (en-US)

#### 4. Log verification - both jobs should be dispatched

```bash
docker logs moviemind-php 2>&1 | grep -E "generation slot|context_tag.*modern|context_tag.*humorous" | tail -10
```

**Expected result:**
- Logs should show two different slots (different ContextTag)
- Both jobs should be dispatched

---

## 🧪 Test 13: What happens when ContextTag is not in database

### Goal

Verify behavior when retrieving a movie that does not have a description with a given ContextTag.

### Steps

#### 1. Create a movie with description with one ContextTag (e.g., modern)

```bash
SLUG="single-context-$(date +%s)"

# Generate description with context_tag="modern"
curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\",
    \"context_tag\": \"modern\"
  }" | jq .

sleep 5
```

#### 2. Check what GET /api/v1/movies/{slug} returns (without parameters)

```bash
curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq .
```

**Expected result:**
- Status: `200 OK`
- Response contains movie with default description (default_description_id)
- Description should have context_tag="modern" (because it's the only description)

#### 3. Check what GET with description_id returns for non-existent ContextTag

First find movie_id:
```bash
MOVIE_ID=$(docker exec moviemind-db psql -U moviemind -d moviemind -t -c "
  SELECT id FROM movies WHERE slug = '$SLUG';
" | tr -d ' ')

echo "Movie ID: $MOVIE_ID"
```

Now check what happens when trying to use description_id that doesn't exist:
```bash
# Get existing description_id
DESC_ID=$(docker exec moviemind-db psql -U moviemind -d moviemind -t -c "
  SELECT id FROM movie_descriptions WHERE movie_id = $MOVIE_ID LIMIT 1;
" | tr -d ' ')

echo "Description ID: $DESC_ID"

# Try to use description_id for non-existent description
INVALID_DESC_ID=99999
curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG?description_id=$INVALID_DESC_ID" \
  -H "Accept: application/json" | jq .
```

**Expected result:**
- Status: `404 Not Found` OR `200 OK` with default description (depends on implementation)

---

## ✅ Final Checklist

- [ ] Test 1: Movie GET endpoint - concurrent requests return same job_id
- [ ] Test 2: Movie POST /generate - concurrent requests return same job_id
- [ ] Test 3: Person GET endpoint - concurrent requests return same job_id
- [ ] Test 4: Person POST /generate - concurrent requests return same job_id
- [ ] Test 5: Logs confirm only one "dispatched new job" per test
- [ ] Test 6: Logs show "reusing existing job" for second request
- [ ] Test 7: Edge case - 3 fast requests return same job_id
- [ ] Test 8: Database - no duplicates in movies and people tables
- [ ] Test 9: Job status - job exists and status can be checked
- [ ] Test 10: Generation with default ContextTag - system uses default
- [ ] Test 11: Generation with specific ContextTag (humorous) - correctly saved in database
- [ ] Test 12: Invalid ContextTag - fallback or validation error
- [ ] Test 13: Different ContextTag in concurrent requests - different job_id and descriptions (KEY)
- [ ] Test 14: ContextTag not in database - behavior when retrieving movie

---

## 🔧 Troubleshooting

### Problem: Feature flag inactive

**Symptoms:**
- Response: `{"error": "Feature not available"}` or `{"error": "Person not found"}`

**Solution:**
```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/ai_description_generation" \
  -H "Content-Type: application/json" \
  -d '{"state":"on"}' | jq .
```

### Problem: "Person not found" instead of 202

**Symptoms:**
- GET `/api/v1/people/{slug}` returns 404 instead of 202

**Solution:**
- Check if `ai_bio_generation` is active:
```bash
curl -s -X GET "http://localhost:8000/api/v1/admin/flags" | jq '.data[] | select(.name == "ai_bio_generation")'
```

### Problem: "Invalid slug format" for Person

**Symptoms:**
- Response: `{"error": "Invalid slug format", "message": "Slug does not match expected person slug format"}`

**Solution:**
- Use slug in **2-4 words format** (e.g., `john-doe`, `mary-jane-watson`)
- **Don't use:** `test-person-123` (contains numbers, may be rejected)
- **Use:** `john-doe`, `jane-smith`, `mary-jane-watson`

### Problem: Different job_id for concurrent requests

**Symptoms:**
- Request 1: `job_id: abc-123`
- Request 2: `job_id: def-456` (different!)

**Solution:**
1. Check logs:
```bash
docker logs moviemind-php 2>&1 | grep -E "generation slot|reusing existing job" | tail -10
```

2. Check Redis (if cache works):
```bash
docker exec moviemind-redis redis-cli KEYS "ai_job_inflight:*"
```

3. Check if Horizon is running:
```bash
docker logs moviemind-horizon | tail -20
```

### Problem: No logs

**Symptoms:**
- No logs in `docker logs moviemind-php`

**Solution:**
1. Check logs directly in file:
```bash
tail -100 api/storage/logs/laravel.log
```

2. Check file permissions:
```bash
ls -la api/storage/logs/
```

3. Check logging configuration:
```bash
docker exec moviemind-php php artisan tinker --execute="echo config('logging.default');"
```

---

## 📝 Example Test Script

You can save this as `test-duplicate-prevention.sh`:

```bash
#!/bin/bash

BASE_URL="http://localhost:8000"

echo "=== Test 1: Movie GET endpoint ==="
SLUG="test-movie-$(date +%s)"
JOB1=$(curl -s -X GET "$BASE_URL/api/v1/movies/$SLUG" -H "Accept: application/json" | jq -r '.job_id')
sleep 0.1
JOB2=$(curl -s -X GET "$BASE_URL/api/v1/movies/$SLUG" -H "Accept: application/json" | jq -r '.job_id')
if [ "$JOB1" = "$JOB2" ]; then echo "✅ PASS"; else echo "❌ FAIL"; fi

echo "=== Test 2: Movie POST /generate ==="
SLUG="test-gen-$(date +%s)"
JOB1=$(curl -s -X POST "$BASE_URL/api/v1/generate" -H "Content-Type: application/json" -d "{\"entity_type\":\"MOVIE\",\"entity_id\":\"$SLUG\"}" | jq -r '.job_id')
sleep 0.1
JOB2=$(curl -s -X POST "$BASE_URL/api/v1/generate" -H "Content-Type: application/json" -d "{\"entity_type\":\"MOVIE\",\"entity_id\":\"$SLUG\"}" | jq -r '.job_id')
if [ "$JOB1" = "$JOB2" ]; then echo "✅ PASS"; else echo "❌ FAIL"; fi

echo "=== Test 3: Person GET endpoint ==="
# Activate feature flag
curl -s -X POST "$BASE_URL/api/v1/admin/flags/ai_bio_generation" -H "Content-Type: application/json" -d '{"state":"on"}' > /dev/null
SLUG="john-doe-$(date +%s | tail -c 4)"
JOB1=$(curl -s -X GET "$BASE_URL/api/v1/people/$SLUG" -H "Accept: application/json" | jq -r '.job_id // .error')
sleep 0.1
JOB2=$(curl -s -X GET "$BASE_URL/api/v1/people/$SLUG" -H "Accept: application/json" | jq -r '.job_id // .error')
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ] && [ "$JOB1" != "Person not found" ]; then echo "✅ PASS"; else echo "❌ FAIL"; fi

echo "=== Test 4: Person POST /generate ==="
SLUG="jane-smith-$(date +%s | tail -c 4)"
JOB1=$(curl -s -X POST "$BASE_URL/api/v1/generate" -H "Content-Type: application/json" -d "{\"entity_type\":\"PERSON\",\"entity_id\":\"$SLUG\"}" | jq -r '.job_id // .error')
sleep 0.1
JOB2=$(curl -s -X POST "$BASE_URL/api/v1/generate" -H "Content-Type: application/json" -d "{\"entity_type\":\"PERSON\",\"entity_id\":\"$SLUG\"}" | jq -r '.job_id // .error')
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ] && [ "$JOB1" != "Feature not available" ] && [ "$JOB1" != "Invalid slug format" ]; then echo "✅ PASS"; else echo "❌ FAIL"; fi

echo "=== Tests completed ==="
```

**Usage:**
```bash
chmod +x test-duplicate-prevention.sh
./test-duplicate-prevention.sh
```

---

## 🔗 Related Documents

- [Locking Strategies for AI Generation](../technical/LOCKING_STRATEGIES_FOR_AI_GENERATION.en.md)
- [ADR-007: AI description generation locks](../../adr/README.md#adr-007-ai-description-generation-locks)
- [Horizon Setup](./HORIZON_SETUP.md)
- [OpenAI Setup and Testing](./OPENAI_SETUP_AND_TESTING.md)

---

## 📌 Notes

- **Document update:** This document should be updated whenever:
  - API endpoints change
  - Duplicate prevention mechanisms change
  - Feature flags change
  - API response format changes
  - Slug format requirements change
  - Log structure changes

- **Version:** This document is the English version. Polish version is located in `docs/knowledge/reference/MANUAL_TESTING_GUIDE.md`

---

**Last updated:** 2025-11-29


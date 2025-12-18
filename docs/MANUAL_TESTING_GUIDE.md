# Complete Manual Testing Guide - MovieMind API

> **Created:** 2025-12-18  
> **Context:** Complete manual testing instructions for QA - All MovieMind API features  
> **Category:** reference  
> **Target Audience:** QA Engineers, Testers

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Environment Setup](#environment-setup)
4. [Movies API](#movies-api)
5. [People API](#people-api)
6. [Movie Relationships](#movie-relationships)
7. [Generate API](#generate-api)
8. [Jobs API](#jobs-api)
9. [Health & Admin](#health--admin)
10. [Security Verification](#security-verification)
11. [Performance Testing](#performance-testing)
12. [Troubleshooting](#troubleshooting)
13. [Test Report Template](#test-report-template)

---

## 🎯 Overview

This document provides comprehensive manual testing instructions for all MovieMind API features. It covers:

- **Movies:** Search, retrieve, refresh, and relationships
- **People:** Search, retrieve, refresh
- **Generate:** AI description and bio generation
- **Jobs:** Asynchronous job status tracking
- **Health:** System health checks
- **Admin:** Feature flags and debug endpoints

---

## 📋 Prerequisites

### Required Tools
- **API Server:** Laravel application running (`php artisan serve` or Docker)
- **Database:** PostgreSQL (production) or SQLite (testing) with migrations applied
- **Queue Worker:** Laravel Horizon or `php artisan queue:work` running
- **API Testing Tool:** curl, Postman, Insomnia, or browser with developer tools
- **Database Access:** psql, SQLite CLI, or database GUI (optional, for verification)
- **Log Access:** Access to `storage/logs/laravel.log` or Horizon dashboard

### Required Knowledge
- Basic understanding of REST APIs
- Basic SQL queries (for database verification)
- Understanding of HTTP status codes
- Basic command line usage

### Environment Variables
Ensure these are configured in `.env`:
```bash
TMDB_API_KEY=your_tmdb_api_key_here
OPENAI_API_KEY=your_openai_api_key_here
QUEUE_CONNECTION=redis  # or database
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

---

## 🔧 Environment Setup

### Option A: Automated Setup (Recommended for Local Testing)

**For Docker-based local testing, use the automated setup script:**

```bash
# From project root directory
./scripts/setup-local-testing.sh
```

**What the script does:**
1. ✅ Checks Docker installation and status
2. ✅ Starts Docker containers (if not running)
3. ✅ Installs Composer dependencies
4. ✅ Configures Laravel application
5. ✅ Runs database migrations (`migrate:fresh`)
6. ✅ Enables required feature flags
7. ✅ Verifies API health

**Script options:**
```bash
# Use mock AI (default, no OpenAI key needed)
./scripts/setup-local-testing.sh

# Use real OpenAI API (requires OPENAI_API_KEY in .env)
./scripts/setup-local-testing.sh --ai-service real

# Load test fixtures (seeders) after migration
./scripts/setup-local-testing.sh --seed

# Combine options: real AI + test fixtures
./scripts/setup-local-testing.sh --ai-service real --seed

# Rebuild containers before starting
./scripts/setup-local-testing.sh --rebuild

# Rebuild + load fixtures
./scripts/setup-local-testing.sh --rebuild --seed

# Skip container startup (assumes containers already running)
./scripts/setup-local-testing.sh --no-start

# Custom API URL
./scripts/setup-local-testing.sh --api-url http://localhost:8000

# With admin authentication
ADMIN_AUTH="admin:password" ./scripts/setup-local-testing.sh
```

**Environment variables:**
```bash
export API_BASE_URL=http://localhost:8000
export ADMIN_AUTH="admin:password"
export AI_SERVICE=mock  # or 'real'
export LOAD_FIXTURES=true  # or 'false' (load test fixtures)
export DOCKER_COMPOSE_CMD="docker compose"
```

**After running the script:**
- ✅ All Docker containers are running
- ✅ Database is fresh and migrated
- ✅ Test fixtures loaded (if `--seed` option used)
- ✅ Feature flags are enabled
- ✅ API is ready for testing

**Test fixtures include:**
- Movies: The Matrix (1999), Inception (2010)
- People: Keanu Reeves, The Wachowskis, Christopher Nolan
- Genres: Action, Sci-Fi, Thriller

**For detailed script documentation, see:** `scripts/setup-local-testing.sh` (contains inline help)

---

### Option B: Manual Setup

**If you prefer manual setup or are not using Docker:**

#### Step 1: Start API Server

```bash
cd api
php artisan serve
# Server should start on http://localhost:8000
```

**Verify API health:**
```bash
# Check OpenAI health
curl http://localhost:8000/api/v1/health/openai
# Should return 200 OK

# Check TMDB connectivity (via test search)
curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix&year=1999" \
  -H "Accept: application/json" | jq
# Should return 200 OK or 202 Accepted (if movie needs to be created)
# If TMDB API is not accessible, you'll see errors in logs

# Alternative: Direct TMDB API test (requires TMDB_API_KEY in .env)
curl -X GET "https://api.themoviedb.org/3/movie/603?api_key=${TMDB_API_KEY}" \
  -H "Accept: application/json" | jq '.id'
# Should return: 603 (The Matrix movie ID)
```

#### Step 2: Run Migrations

```bash
cd api
php artisan migrate
# Should show all migrations completed
```

**Verify migration:**
```bash
# Check if tables exist (PostgreSQL)
psql -d moviemind -c "\dt"

# Or check via Laravel tinker
php artisan tinker
>>> Schema::hasTable('movies')
# Should return: true
```

#### Step 3: Start Queue Worker

**Option A: Laravel Horizon (Recommended)**
```bash
cd api
php artisan horizon
# Horizon dashboard available at http://localhost:8000/horizon
```

**Option B: Standard Queue Worker**
```bash
cd api
php artisan queue:work --tries=3 --timeout=120
```

**Verify queue is working:**
```bash
# Check Horizon dashboard or queue logs
tail -f storage/logs/laravel.log | grep "Queue"
```

#### Step 4: Verify API Health

```bash
# Check OpenAI health (this verifies API connectivity)
curl -X GET "http://localhost:8000/api/v1/health/openai"
# Should return status 200 with OpenAI status

# Check TMDB health (this verifies TMDB API connectivity)
curl -X GET "http://localhost:8000/api/v1/health/tmdb"
# Should return status 200 with TMDB status

# Note: Both endpoints verify API connectivity and configuration
```

---

## 🎬 Movies API

> **For detailed testing scenarios, see:** [Movies Testing Guide](./MANUAL_TESTING_MOVIES.md)

### Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/movies` | List all movies (with pagination) |
| `GET` | `/api/v1/movies/search` | Advanced movie search |
| `GET` | `/api/v1/movies/{slug}` | Get movie details |
| `GET` | `/api/v1/movies/{slug}/related` | Get related movies |
| `POST` | `/api/v1/movies/{slug}/refresh` | Refresh movie from TMDB |

### Quick Test Examples

**List movies:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies" | jq
```

**Search movies:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix&year=1999" | jq
```

**Get movie details:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" | jq
```

**Refresh movie (⚠️ requires POST method):**
```bash
curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/refresh" \
  -H "Accept: application/json" | jq
```
**Note:** This endpoint requires `POST` method. Using `GET` will return `405 Method Not Allowed`.

### Scenario 1: List All Movies

**Objective:** Verify that listing movies works with pagination.

**Steps:**

1. **Send GET request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**
   - [ ] Status code: `200 OK`
   - [ ] Response contains `data` array with movies
   - [ ] Each movie has: `id`, `slug`, `title`, `release_year`
   - [ ] Pagination metadata present (if applicable)
   - [ ] `_links` present for HATEOAS

3. **Test with query parameter:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies?q=matrix" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Only movies matching "matrix" are returned

---

### Scenario 2: Advanced Movie Search

**Objective:** Verify advanced search with multiple criteria.

**Steps:**

1. **Search by title:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
     -H "Accept: application/json" | jq
   ```

2. **Search by title and year:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix&year=1999" \
     -H "Accept: application/json" | jq
   ```

3. **Search by title and director:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix&director=Wachowski" \
     -H "Accept: application/json" | jq
   ```

4. **Search with pagination:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix&page=1&per_page=5" \
     -H "Accept: application/json" | jq
   ```

5. **Verify response:**
   - [ ] Status code: `200 OK` (or `300` for disambiguation, `404` for not found)
   - [ ] Results match search criteria
   - [ ] Pagination works correctly
   - [ ] Disambiguation handled properly (if multiple matches)

---

### Scenario 3: Get Movie Details

**Objective:** Verify retrieving a specific movie by slug.

**Steps:**

1. **Get movie by slug:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response structure:**
   ```json
   {
     "id": 1,
     "slug": "the-matrix-1999",
     "title": "The Matrix",
     "release_year": 1999,
     "director": "Wachowski Brothers",
     "genres": ["Action", "Sci-Fi"],
     "default_description_id": 1,
     "descriptions_count": 1,
     "default_description": {
       "id": 1,
       "locale": "en-US",
       "text": "...",
       "context_tag": "default",
       "origin": "GENERATED",
       "ai_model": "gpt-4o-mini"
     },
     "descriptions": [
       {
         "id": 1,
         "locale": "en-US",
         "text": "...",
         "context_tag": "default",
         "origin": "GENERATED"
       }
     ],
     "_links": {
       "self": {
         "href": "http://localhost:8000/api/v1/movies/the-matrix-1999"
       },
       "people": [],
       "generate": {
         "href": "http://localhost:8000/api/v1/generate",
         "method": "POST",
         "body": {
           "entity_type": "MOVIE",
           "entity_id": 1
         }
       }
     }
   }
   ```
   
   **Note:** The `people` array is **not included** in the response for `GET /api/v1/movies/{slug}` endpoint by default. The `people` relation is only loaded when explicitly requested (e.g., in list endpoints). To get people data, use the `people` link in `_links` or access individual person endpoints.

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] All movie fields present
   - [ ] `default_description` present (if exists)
   - [ ] `descriptions` array present with all descriptions
   - [ ] `people` array is **NOT** present in response (not loaded by default for single movie endpoint)
   - [ ] `_links.people` present (may be empty array if no people linked)
   - [ ] **Security:** `tmdb_id` is **NOT** present
   - [ ] `_links` present with `self` and `generate` links

4. **Test with description_id parameter:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999?description_id=2" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Specific description is returned in `default_description`

---

### Scenario 4: Movie Disambiguation

**Objective:** Verify handling of ambiguous movie slugs.

**Steps:**

1. **Search for ambiguous title (e.g., "Bad Boys"):**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=bad+boys" \
     -H "Accept: application/json" | jq
   ```

2. **If disambiguation occurs (300 status):**
   ```json
   {
     "error": "Multiple movies found",
     "message": "Multiple movies match your search criteria...",
     "match_type": "ambiguous",
     "count": 2,
     "results": [
       {
         "slug": "bad-boys-1995",
         "title": "Bad Boys",
         "release_year": 1995
       },
       {
         "slug": "bad-boys-ii-2003",
         "title": "Bad Boys II",
         "release_year": 2003
       }
     ],
     "hint": "Use the slug from options to access specific movie..."
   }
   ```

3. **Select specific movie:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/bad-boys?slug=bad-boys-ii-2003" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Selected movie is returned

---

### Scenario 5: Refresh Movie from TMDB

**Objective:** Verify refreshing movie metadata from TMDB.

**⚠️ Important:** This endpoint requires `POST` method. Using `GET` will return `405 Method Not Allowed`.

**Steps:**

1. **Refresh movie (must use POST):**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/refresh" \
     -H "Accept: application/json" | jq
   ```
   
   **Common mistake:** Opening this URL in a browser (which uses GET) will fail. Always use `curl -X POST` or a tool like Postman/Insomnia.

2. **Verify response:**
   ```json
   {
     "message": "Movie data refreshed from TMDb",
     "slug": "the-matrix-1999",
     "movie_id": 1,
     "refreshed_at": "2025-12-18T13:24:00+00:00"
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] Movie metadata updated in database
   - [ ] **Important:** Actors/crew are **NOT** re-synced (only core metadata)
   - [ ] `SyncMovieMetadataJob` is **NOT** dispatched

4. **Verify movie was updated:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Updated fields reflect latest TMDB data

---

### Scenario 6: Movie Relationships

**See detailed section:** [Movie Relationships](#movie-relationships)

**Quick test:**
```bash
# Get related movies
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" | jq

# Filter by type
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL" | jq
```

---

## 👥 People API

> **For detailed testing scenarios, see:** [People Testing Guide](./MANUAL_TESTING_PEOPLE.md)

### Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/people` | List all people (with pagination) |
| `GET` | `/api/v1/people/{slug}` | Get person details |
| `POST` | `/api/v1/people/{slug}/refresh` | Refresh person from TMDB |

### Quick Test Examples

**List people:**
```bash
curl -X GET "http://localhost:8000/api/v1/people" | jq
```

**Get person details:**
```bash
curl -X GET "http://localhost:8000/api/v1/people/keanu-reeves" | jq
```

**Refresh person (⚠️ requires POST method):**
```bash
curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves/refresh" \
  -H "Accept: application/json" | jq
```
**Note:** This endpoint requires `POST` method. Using `GET` will return `405 Method Not Allowed`.

### Scenario 1: List All People

**Objective:** Verify listing people works.

**Steps:**

1. **Send GET request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/people" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**
   - [ ] Status code: `200 OK`
   - [ ] Response contains `data` array with people
   - [ ] Each person has: `id`, `slug`, `name`
   - [ ] Pagination metadata present (if applicable)

3. **Test with query parameter:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/people?q=keanu" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Only people matching "keanu" are returned

---

### Scenario 2: Get Person Details

**Objective:** Verify retrieving a specific person by slug.

**Steps:**

1. **Get person by slug:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/people/keanu-reeves" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response structure:**
   ```json
   {
     "id": 1,
     "slug": "keanu-reeves",
     "name": "Keanu Reeves",
     "birth_date": "1964-09-02",
     "birthplace": "Beirut, Lebanon",
     "bios_count": 1,
     "default_bio": {
       "id": 1,
       "locale": "en-US",
       "text": "...",
       "context_tag": "default",
       "origin": "GENERATED"
     },
     "movies": [
       {
         "id": 1,
         "slug": "the-matrix-1999",
         "title": "The Matrix",
         "role": "ACTOR",
         "character_name": "Neo"
       }
     ],
     "_links": {
       "self": {
         "href": "http://localhost:8000/api/v1/people/keanu-reeves"
       }
     }
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] All person fields present
   - [ ] `default_bio` present (if exists)
   - [ ] `movies` array present with person's filmography
   - [ ] **Security:** `tmdb_id` is **NOT** present
   - [ ] `_links` present

---

### Scenario 3: Refresh Person from TMDB

**Objective:** Verify refreshing person metadata from TMDB.

**⚠️ Important:** This endpoint requires `POST` method. Using `GET` will return `405 Method Not Allowed`.

**Steps:**

1. **Refresh person (must use POST):**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves/refresh" \
     -H "Accept: application/json" | jq
   ```
   
   **Common mistake:** Opening this URL in a browser (which uses GET) will fail. Always use `curl -X POST` or a tool like Postman/Insomnia.

2. **Verify response:**
   ```json
   {
     "message": "Person data refreshed from TMDb",
     "slug": "keanu-reeves",
     "person_id": 1,
     "refreshed_at": "2025-12-18T13:24:00+00:00"
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] Person metadata updated
   - [ ] Bios are **NOT** re-generated

---

## 🔗 Movie Relationships

> **For detailed testing scenarios, see:** [Relationships Testing Guide](./MANUAL_TESTING_RELATIONSHIPS.md)

### How It Works

**Important:** Movie relationships are stored **locally in the database** (table `movie_relationships`), but they are **synchronized from TMDB** asynchronously.

**Synchronization Flow:**
1. When a movie is created from TMDB (via search or refresh), `SyncMovieRelationshipsJob` is dispatched
2. The job fetches relationship data from TMDB:
   - **Collections** (sequels, prequels) → Creates `SEQUEL`/`PREQUEL` relationships
   - **Similar movies** → Creates `SAME_UNIVERSE` relationships
3. Related movies are created in the database if they don't exist
4. Relationships are stored in `movie_relationships` table
5. The `/related` endpoint reads from the **local database** (not TMDB directly)

**Why empty `related_movies`?**
- Movie doesn't have `tmdb_id` or TMDB snapshot
- `SyncMovieRelationshipsJob` hasn't run yet (check queue)
- Movie has no relationships in TMDB (no collection, no similar movies)
- Queue worker is not running

**To check:**
```bash
# Check if movie has TMDB snapshot
docker compose exec php php artisan tinker
>>> \App\Models\Movie::where('slug', 'the-matrix-1999')->first()->tmdbSnapshot

# Check if relationships exist
>>> \App\Models\MovieRelationship::where('movie_id', 1)->count()

# Manually trigger sync (if snapshot exists)
>>> \App\Jobs\SyncMovieRelationshipsJob::dispatch(1);
```

---

## 📺 TV Series API

> **Status:** ⏸️ **Not yet implemented**  
> **Planned:** Future feature

**Note:** TV Series endpoints are planned but not yet implemented. This section will be updated when the feature is available.

**Planned Endpoints:**
- `GET /api/v1/series` - List all TV series
- `GET /api/v1/series/{slug}` - Get series details
- `GET /api/v1/series/{slug}/seasons` - Get seasons
- `GET /api/v1/series/{slug}/episodes` - Get episodes

### Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/movies/{slug}/related` | Get related movies |

### Quick Test Examples

**Get related movies:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" | jq
```

**Filter by type:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL" | jq
```

### Relationship Types

- `SEQUEL` - Sequel (next movie in series)
- `PREQUEL` - Prequel (previous movie in series)
- `REMAKE` - Remake
- `SERIES` - Part of series
- `SPINOFF` - Spinoff
- `SAME_UNIVERSE` - Same universe

### Scenario 1: Get Related Movies

**Objective:** Verify retrieving related movies.

**Prerequisites:**
- Movie must have `tmdb_id` and TMDB snapshot
- `SyncMovieRelationshipsJob` must have run (check queue)
- Movie must have relationships in TMDB (collection or similar movies)

**Steps:**

1. **Get related movies:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response (if relationships exist):**
   ```json
   {
     "movie": {
       "id": 1,
       "slug": "the-matrix-1999",
       "title": "The Matrix"
     },
     "related_movies": [
       {
         "id": 2,
         "slug": "the-matrix-reloaded-2003",
         "title": "The Matrix Reloaded",
         "relationship_type": "SEQUEL",
         "relationship_label": "Sequel",
         "relationship_order": 1,
         "_links": {
           "self": {
             "href": "http://localhost:8000/api/v1/movies/the-matrix-reloaded-2003"
           }
         }
       }
     ],
     "count": 1,
     "_links": {
       "self": {
         "href": "http://localhost:8000/api/v1/movies/the-matrix-1999/related"
       },
       "movie": {
         "href": "http://localhost:8000/api/v1/movies/the-matrix-1999"
       }
     }
   }
   ```

3. **If `related_movies` is empty:**
   - Check if movie has TMDB snapshot: `docker compose exec php php artisan tinker` → `Movie::where('slug', 'the-matrix-1999')->first()->tmdbSnapshot`
   - Check if relationships exist: `MovieRelationship::where('movie_id', 1)->count()`
   - Manually trigger sync: `SyncMovieRelationshipsJob::dispatch(1)`
   - Check queue worker: `docker compose exec php php artisan horizon:status`

4. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] `movie` object present
   - [ ] `related_movies` array present (may be empty if no relationships)
   - [ ] If relationships exist: Each related movie has `relationship_type`, `relationship_label`, `relationship_order`
   - [ ] `count` matches array length
   - [ ] **Security:** `tmdb_id` is **NOT** present

---

### Scenario 2: Filter by Relationship Type

**Objective:** Verify filtering related movies by type.

**Steps:**

1. **Filter by single type:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL" \
     -H "Accept: application/json" | jq
   ```

2. **Filter by multiple types:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL&type[]=PREQUEL" \
     -H "Accept: application/json" | jq
   ```

3. **Verify:**
   - [ ] Only specified types are returned
   - [ ] `count` matches filtered results

---

### Scenario 3: Empty Relationships

**Objective:** Verify handling of movies without relationships.

**Steps:**

1. **Get related movies for standalone movie:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/standalone-movie-2020/related" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**
   ```json
   {
     "movie": {...},
     "related_movies": [],
     "count": 0,
     "_links": {...}
   }
   ```
   - [ ] Status code: `200 OK`
   - [ ] Empty array returned
   - [ ] `count` is `0`

---

**For detailed relationship testing, see:** [Relationships Testing Guide](./MANUAL_TESTING_RELATIONSHIPS.md)

---

## 🤖 Generate API

### Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/v1/generate` | Trigger AI generation |

### Scenario 1: Generate Movie Description

**Objective:** Verify generating a movie description.

**Steps:**

1. **Generate description:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/generate" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{
       "entity_type": "MOVIE",
       "slug": "the-matrix-1999",
       "locale": "en-US",
       "context_tag": "DEFAULT"
     }' | jq
   ```

2. **Verify response:**
   ```json
   {
     "job_id": "550e8400-e29b-41d4-a716-446655440000",
     "status": "PENDING",
     "entity_type": "MOVIE",
     "slug": "the-matrix-1999",
     "locale": "en-US",
     "context_tag": "DEFAULT",
     "message": "Generation job queued"
   }
   ```

3. **Verify:**
   - [ ] Status code: `202 Accepted`
   - [ ] `job_id` present
   - [ ] Status is `PENDING`
   - [ ] Job appears in queue

4. **Check job status:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
     -H "Accept: application/json" | jq
   ```

---

### Scenario 2: Generate with Different Context Tags

**Objective:** Verify generating descriptions with different context tags.

**Context Tags:**
- `DEFAULT` - Standard description
- `modern` - Modern perspective
- `critical` - Critical analysis
- `humorous` - Humorous take

**Steps:**

1. **Generate modern description (single context tag):**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/generate" \
     -H "Content-Type: application/json" \
     -d '{
       "entity_type": "MOVIE",
       "slug": "the-matrix-1999",
       "locale": "en-US",
       "context_tag": "modern"
     }' | jq
   ```

2. **Generate critical description (single context tag):**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/generate" \
     -H "Content-Type: application/json" \
     -d '{
       "entity_type": "MOVIE",
       "slug": "the-matrix-1999",
       "locale": "en-US",
       "context_tag": "critical"
     }' | jq
   ```

3. **Generate multiple context tags at once (NEW):**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/generate" \
     -H "Content-Type: application/json" \
     -d '{
       "entity_type": "MOVIE",
       "slug": "the-matrix-1999",
       "locale": "en-US",
       "context_tag": ["modern", "critical", "humorous"]
     }' | jq
   ```

4. **Verify response for multiple context tags:**
   ```json
   {
     "job_ids": [
       "550e8400-e29b-41d4-a716-446655440000",
       "660e8400-e29b-41d4-a716-446655440001",
       "770e8400-e29b-41d4-a716-446655440002"
     ],
     "status": "PENDING",
     "message": "Generation queued for multiple context tags",
     "slug": "the-matrix-1999",
     "context_tags": ["modern", "critical", "humorous"],
     "locale": "en-US",
     "jobs": [
       {
         "job_id": "550e8400-e29b-41d4-a716-446655440000",
         "status": "PENDING",
         "context_tag": "modern"
       },
       {
         "job_id": "660e8400-e29b-41d4-a716-446655440001",
         "status": "PENDING",
         "context_tag": "critical"
       },
       {
         "job_id": "770e8400-e29b-41d4-a716-446655440002",
         "status": "PENDING",
         "context_tag": "humorous"
       }
     ]
   }
   ```

5. **Verify:**
   - [ ] Single context tag creates one job (backward compatible)
   - [ ] Multiple context tags create multiple jobs (one per tag)
   - [ ] Each context tag creates separate description
   - [ ] Multiple descriptions exist for same movie
   - [ ] Default description is not overwritten
   - [ ] Response includes `job_ids` array when multiple tags provided
   - [ ] Response includes `jobs` array with details for each queued job

---

### Scenario 3: Generate Multiple Context Tags at Once

**Objective:** Verify generating multiple descriptions with different context tags in a single request.

**Steps:**

1. **Generate multiple context tags for a movie:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/generate" \
     -H "Content-Type: application/json" \
     -d '{
       "entity_type": "MOVIE",
       "slug": "the-matrix-1999",
       "locale": "en-US",
       "context_tag": ["modern", "critical", "humorous"]
     }' | jq
   ```

2. **Verify response:**
   ```json
   {
     "job_ids": [
       "550e8400-e29b-41d4-a716-446655440000",
       "660e8400-e29b-41d4-a716-446655440001",
       "770e8400-e29b-41d4-a716-446655440002"
     ],
     "status": "PENDING",
     "message": "Generation queued for multiple context tags",
     "slug": "the-matrix-1999",
     "context_tags": ["modern", "critical", "humorous"],
     "locale": "en-US",
     "jobs": [...]
   }
   ```

3. **Verify:**
   - [ ] Status code: `202 Accepted`
   - [ ] Response contains `job_ids` array with multiple job IDs
   - [ ] Response contains `context_tags` array matching input
   - [ ] Response contains `jobs` array with details for each job
   - [ ] Each job has unique `job_id`
   - [ ] Each job has correct `context_tag`

4. **Check individual job statuses:**
   ```bash
   # Check first job
   curl -X GET "http://localhost:8000/api/v1/jobs/550e8400-e29b-41d4-a716-446655440000" | jq
   
   # Check second job
   curl -X GET "http://localhost:8000/api/v1/jobs/660e8400-e29b-41d4-a716-446655440001" | jq
   ```

5. **After jobs complete, verify multiple descriptions exist:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" | jq '.descriptions'
   ```
   - [ ] Multiple descriptions exist for the movie
   - [ ] Each description has different `context_tag`
   - [ ] Descriptions are not overwritten

6. **Test backward compatibility (single string):**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/generate" \
     -H "Content-Type: application/json" \
     -d '{
       "entity_type": "MOVIE",
       "slug": "the-matrix-1999",
       "locale": "en-US",
       "context_tag": "modern"
     }' | jq
   ```
   - [ ] Single string still works (backward compatible)
   - [ ] Response format matches old format (single `job_id`, not `job_ids`)

---

### Scenario 4: Generate Person Bio

**Objective:** Verify generating a person biography.

**Steps:**

1. **Generate bio (single context tag):**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/generate" \
     -H "Content-Type: application/json" \
     -d '{
       "entity_type": "PERSON",
       "slug": "keanu-reeves",
       "locale": "en-US",
       "context_tag": "DEFAULT"
     }' | jq
   ```

2. **Generate multiple bios at once:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/generate" \
     -H "Content-Type: application/json" \
     -d '{
       "entity_type": "PERSON",
       "slug": "keanu-reeves",
       "locale": "en-US",
       "context_tag": ["modern", "critical"]
     }' | jq
   ```

3. **Verify:**
   - [ ] Status code: `202 Accepted`
   - [ ] Job(s) created for person
   - [ ] Bio(s) generated after job completion
   - [ ] Multiple bios exist for same person (if multiple context tags)

---

## 📊 Jobs API

### Endpoints Overview

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/jobs/{id}` | Get job status |

### Scenario 1: Check Job Status

**Objective:** Verify checking job status.

**Steps:**

1. **Get job status:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/jobs/550e8400-e29b-41d4-a716-446655440000" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response (PENDING):**
   ```json
   {
     "id": "550e8400-e29b-41d4-a716-446655440000",
     "status": "PENDING",
     "entity_type": "MOVIE",
     "entity_id": 1,
     "locale": "en-US",
     "created_at": "2025-12-18T13:24:00+00:00"
   }
   ```

3. **Verify response (DONE):**
   ```json
   {
     "id": "550e8400-e29b-41d4-a716-446655440000",
     "status": "DONE",
     "entity_type": "MOVIE",
     "entity_id": 1,
     "locale": "en-US",
     "created_at": "2025-12-18T13:24:00+00:00",
     "completed_at": "2025-12-18T13:24:30+00:00"
   }
   ```

4. **Verify response (FAILED):**
   ```json
   {
     "id": "550e8400-e29b-41d4-a716-446655440000",
     "status": "FAILED",
     "entity_type": "MOVIE",
     "entity_id": 1,
     "locale": "en-US",
     "error": "OpenAI API error: Rate limit exceeded",
     "created_at": "2025-12-18T13:24:00+00:00",
     "failed_at": "2025-12-18T13:24:15+00:00"
   }
   ```

5. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] Status field present: `PENDING`, `DONE`, or `FAILED`
   - [ ] Error message present if `FAILED`

---

## 🏥 Health & Admin

### Endpoints Overview

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `GET` | `/api/v1/health/openai` | Check OpenAI API health | No |
| `GET` | `/api/v1/health/tmdb` | Check TMDb API health | No |
| `GET` | `/api/v1/admin/flags` | List feature flags | Yes |
| `POST` | `/api/v1/admin/flags/{name}` | Set feature flag | Yes |
| `GET` | `/api/v1/admin/flags/usage` | Get flag usage info | Yes |
| `GET` | `/api/v1/admin/debug/config` | Debug configuration | Yes |

### Scenario 1: Health Check

**Objective:** Verify OpenAI API health.

**Steps:**

1. **Check health:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/health/openai" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**
   ```json
   {
     "status": "ok",
     "service": "openai",
     "message": "OpenAI API is accessible"
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] Status indicates API accessibility

---

### Scenario 2: Health Check - TMDB

**Objective:** Verify TMDB API connectivity.

**Steps:**

1. **Check TMDB health endpoint (recommended):**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/health/tmdb" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Status code: `200 OK` (if API is accessible)
   - [ ] Response contains `success: true` and `message: "TMDb API is accessible"`
   - [ ] If `503`, check `error` field for details

2. **Verify response structure (success):**
   ```json
   {
     "success": true,
     "service": "tmdb",
     "message": "TMDb API is accessible",
     "status": 200
   }
   ```

3. **Verify response structure (error - no API key):**
   ```json
   {
     "success": false,
     "service": "tmdb",
     "error": "TMDb API key not configured. Set TMDB_API_KEY in .env"
   }
   ```

4. **Alternative: Check TMDB via movie search (indirect verification):**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix&year=1999" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Status code: `200 OK` or `202 Accepted`
   - [ ] No TMDB-related errors in response
   - [ ] Check logs for TMDB connectivity issues

5. **Direct TMDB API test (requires API key):**
   ```bash
   # Get API key from .env
   TMDB_API_KEY=$(grep TMDB_API_KEY api/.env | cut -d '=' -f2)
   
   # Test TMDB API directly
   curl -X GET "https://api.themoviedb.org/3/movie/603?api_key=${TMDB_API_KEY}" \
     -H "Accept: application/json" | jq '.id'
   ```
   - [ ] Should return: `603` (The Matrix movie ID)
   - [ ] Status code: `200 OK`

6. **Verify TMDB configuration:**
   ```bash
   # Check if TMDB_API_KEY is set
   grep TMDB_API_KEY api/.env
   # Should show: TMDB_API_KEY=your_key_here
   ```

7. **Check logs for TMDB errors:**
   ```bash
   tail -f api/storage/logs/laravel.log | grep -i tmdb
   # Look for connection errors or rate limit warnings
   ```

8. **Verify:**
   - [ ] TMDB health endpoint returns `200 OK` (or `503` with error details)
   - [ ] TMDB API key is configured
   - [ ] Direct API call succeeds
   - [ ] No connection errors in logs

---

### Scenario 3: Feature Flags (Admin)

**Objective:** Verify feature flag management.

**Prerequisites:** Admin authentication required (Basic Auth)

**Steps:**

1. **List all flags:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/flags" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

2. **Set a flag:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/admin/flags/ai_description_generation" \
     -u "admin:password" \
     -H "Content-Type: application/json" \
     -d '{"state": "on"}' | jq
   ```

3. **Get flag usage:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/flags/usage" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

4. **Verify:**
   - [ ] Status code: `200 OK` (or `401` if not authenticated)
   - [ ] Flags list returned
   - [ ] Flag state can be changed
   - [ ] Usage information available

---

### Scenario 4: Debug Configuration (Admin)

**Objective:** Verify debug configuration endpoint.

**Steps:**

1. **Get debug config:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/debug/config" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**
   ```json
   {
     "ai_service": "real",
     "openai_model": "gpt-4o-mini",
     "queue_connection": "redis",
     "cache_driver": "redis"
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] Configuration values returned
   - [ ] Sensitive data (API keys) are **NOT** exposed

---

## 🔒 Security Verification

### Verify tmdb_id is Hidden

**Test all endpoints:**
```bash
# Movies
curl "http://localhost:8000/api/v1/movies/the-matrix-1999" | jq 'has("tmdb_id")'
# Should return: false

# People
curl "http://localhost:8000/api/v1/people/keanu-reeves" | jq 'has("tmdb_id")'
# Should return: false

# Related movies
curl "http://localhost:8000/api/v1/movies/the-matrix-1999/related" | jq '.related_movies[0] | has("tmdb_id")'
# Should return: false
```

### Verify No Sensitive Data

```bash
# Check for API keys, passwords, etc.
curl "http://localhost:8000/api/v1/movies/the-matrix-1999" | jq '.' | grep -iE "api_key|password|secret|token"
# Should return nothing
```

---

## ⚡ Performance Testing

### Test Response Times

```bash
# Single request timing
time curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
  -H "Accept: application/json" -o /dev/null -s -w "%{time_total}\n"

# Multiple requests (10)
for i in {1..10}; do
  time curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
    -H "Accept: application/json" -o /dev/null -s -w "%{time_total}\n"
done | awk '{sum+=$1; count++} END {print "Average:", sum/count, "seconds"}'
```

**Expected Results:**
- Single request: < 200ms
- Average (10 requests): < 300ms
- 95th percentile: < 500ms

---

## 🐛 Troubleshooting

### Problem: 404 for existing entity

**Solution:**
- Check slug format (exact match required)
- Verify entity exists in database
- Clear cache: `php artisan cache:clear`

### Problem: Queue jobs not processing

**Solution:**
- Check queue worker is running: `php artisan queue:work`
- Check Horizon dashboard: `http://localhost:8000/horizon`
- Check logs: `tail -f storage/logs/laravel.log`

### Problem: TMDB sync not working

**Solution:**
- Verify `TMDB_API_KEY` in `.env`
- Check TMDB API rate limits
- Verify movie has `tmdb_id` and snapshot

---

## 📝 Test Report Template

```markdown
# Test Report - MovieMind API

**Date:** YYYY-MM-DD  
**Tester:** [Name]  
**Environment:** [dev/staging/production]  
**API Version:** v1

## Test Summary

| Area | Scenarios | Passed | Failed |
|------|-----------|--------|--------|
| Movies | X | Y | Z |
| People | X | Y | Z |
| Relationships | X | Y | Z |
| Generate | X | Y | Z |
| Jobs | X | Y | Z |
| Health/Admin | X | Y | Z |

## Issues Found

[Issue descriptions]

## Recommendations

[Recommendations]
```

---

**Last updated:** 2025-12-18  
**Version:** 1.0


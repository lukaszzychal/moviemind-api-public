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
9. [Movie Reports](#movie-reports)
10. [Person Reports](#person-reports)
10. [Adaptive Rate Limiting](#adaptive-rate-limiting)
11. [Health & Admin](#health--admin)
12. [Security Verification](#security-verification)
13. [Performance Testing](#performance-testing)
14. [Troubleshooting](#troubleshooting)
15. [Test Report Template](#test-report-template)

---

## 🎯 Overview

This document provides comprehensive manual testing instructions for all MovieMind API features. It covers:

- **Movies:** Search, retrieve, refresh, and relationships
- **People:** Search, retrieve, refresh
- **Generate:** AI description and bio generation
- **Jobs:** Asynchronous job status tracking
- **Movie Reports:** User error reporting and admin management
- **Person Reports:** User error reporting for person biographies and admin management
- **Adaptive Rate Limiting:** Dynamic rate limits based on system load
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
| `GET` | `/api/v1/movies` | List all movies (with pagination) or bulk retrieve by slugs |
| `GET` | `/api/v1/movies?slugs=...` | Bulk retrieve multiple movies by slugs (RESTful) |
| `GET` | `/api/v1/movies/search` | Advanced movie search |
| `GET` | `/api/v1/movies/{slug}` | Get movie details |
| `GET` | `/api/v1/movies/{slug}/related` | Get related movies |
| `GET` | `/api/v1/movies/{slug}/collection` | Get collection (all movies in same TMDb collection) |
| `GET` | `/api/v1/movies/compare` | Compare two movies |
| `POST` | `/api/v1/movies/bulk` | Bulk retrieve multiple movies (fallback for long lists) |
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

**Search with limit per source:**

```bash
# Limit local results to 5
curl -X GET "http://localhost:8000/api/v1/movies/search?q=&local_limit=5" | jq

# Limit external results to 3
curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix&external_limit=3" | jq

# Both limits
curl -X GET "http://localhost:8000/api/v1/movies/search?q=&local_limit=5&external_limit=10" | jq
```

**Get movie details:**

```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" | jq
```

**Bulk retrieve movies (RESTful - recommended):**

```bash
curl -X GET "http://localhost:8000/api/v1/movies?slugs=the-matrix-1999,inception-2010&include=descriptions,people" | jq
```

**Bulk retrieve movies (POST fallback for long lists):**

```bash
curl -X POST "http://localhost:8000/api/v1/movies/bulk" \
  -H "Content-Type: application/json" \
  -d '{"slugs": ["the-matrix-1999", "inception-2010"], "include": ["descriptions", "people"]}' | jq
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

   **Note:** The `people` array is **not included** in the response for `GET /api/v1/movies/{slug}` endpoint by default.
   The `people` relation is only loaded when explicitly requested (e.g., in list endpoints).
   To get people data, use the `people` link in `_links` or access individual person endpoints.

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

### Scenario 4: Bulk Retrieve Movies (RESTful)

**Objective:** Verify bulk retrieval of multiple movies using GET endpoint.

**Steps:**

1. **Bulk retrieve by slugs (GET - RESTful, recommended):**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies?slugs=the-matrix-1999,inception-2010" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response format:**

   ```json
   {
     "data": [
       {
         "id": 1,
         "slug": "the-matrix-1999",
         "title": "The Matrix",
         "release_year": 1999,
         "_links": {...}
       },
       {
         "id": 2,
         "slug": "inception-2010",
         "title": "Inception",
         "release_year": 2010,
         "_links": {...}
       }
     ],
     "not_found": [],
     "count": 2,
     "requested_count": 2
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] `data` array contains requested movies
   - [ ] Movies are returned in the same order as requested slugs
   - [ ] `not_found` array lists slugs that don't exist
   - [ ] `count` matches number of found movies
   - [ ] `requested_count` matches number of requested slugs

4. **Test with include parameter:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies?slugs=the-matrix-1999&include=descriptions,people,genres" \
     -H "Accept: application/json" | jq
   ```

   - [ ] `descriptions` array included when requested
   - [ ] `people` array included when requested
   - [ ] `genres` array included when requested

5. **Test with non-existent slugs:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies?slugs=the-matrix-1999,non-existent-12345" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status code: `200 OK`
   - [ ] `data` contains only found movies
   - [ ] `not_found` contains `["non-existent-12345"]`
   - [ ] `count` is 1 (only one movie found)
   - [ ] `requested_count` is 2

6. **Test validation (max 50 slugs):**

   ```bash
   # Create a comma-separated list of 51 slugs (over limit)
   slugs=$(printf "slug-%d," {1..51} | sed 's/,$//')
   curl -X GET "http://localhost:8000/api/v1/movies?slugs=$slugs" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status code: `422 Unprocessable Entity`
   - [ ] Error message indicates max 50 slugs allowed

7. **Test with POST fallback (for long lists):**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/movies/bulk" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{
       "slugs": ["the-matrix-1999", "inception-2010"],
       "include": ["descriptions", "people"]
     }' | jq
   ```

   - [ ] Status code: `200 OK`
   - [ ] Same response format as GET endpoint
   - [ ] Useful for lists > 50 slugs (URL length limit)

**Note:** GET endpoint is RESTful and recommended. POST endpoint is available as fallback for very long lists (>50 slugs) where URL length may be an issue.

---

### Scenario 5: Movie Disambiguation

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

**Objective:** Verify bulk retrieval of multiple movies using GET endpoint.

**Steps:**

1. **Bulk retrieve by slugs (GET - RESTful, recommended):**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies?slugs=the-matrix-1999,inception-2010" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response format:**

   ```json
   {
     "data": [
       {
         "id": 1,
         "slug": "the-matrix-1999",
         "title": "The Matrix",
         "release_year": 1999,
         "_links": {...}
       },
       {
         "id": 2,
         "slug": "inception-2010",
         "title": "Inception",
         "release_year": 2010,
         "_links": {...}
       }
     ],
     "not_found": [],
     "count": 2,
     "requested_count": 2
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] `data` array contains requested movies
   - [ ] Movies are returned in the same order as requested slugs
   - [ ] `not_found` array lists slugs that don't exist
   - [ ] `count` matches number of found movies
   - [ ] `requested_count` matches number of requested slugs

4. **Test with include parameter:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies?slugs=the-matrix-1999&include=descriptions,people,genres" \
     -H "Accept: application/json" | jq
   ```

   - [ ] `descriptions` array included when requested
   - [ ] `people` array included when requested
   - [ ] `genres` array included when requested

5. **Test with non-existent slugs:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies?slugs=the-matrix-1999,non-existent-12345" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status code: `200 OK`
   - [ ] `data` contains only found movies
   - [ ] `not_found` contains `["non-existent-12345"]`
   - [ ] `count` is 1 (only one movie found)
   - [ ] `requested_count` is 2

6. **Test validation (max 50 slugs):**

   ```bash
   # Create a comma-separated list of 51 slugs (over limit)
   slugs=$(printf "slug-%d," {1..51} | sed 's/,$//')
   curl -X GET "http://localhost:8000/api/v1/movies?slugs=$slugs" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status code: `422 Unprocessable Entity`
   - [ ] Error message indicates max 50 slugs allowed

7. **Test with POST fallback (for long lists):**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/movies/bulk" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{
       "slugs": ["the-matrix-1999", "inception-2010"],
       "include": ["descriptions", "people"]
     }' | jq
   ```

   - [ ] Status code: `200 OK`
   - [ ] Same response format as GET endpoint
   - [ ] Useful for lists > 50 slugs (URL length limit)

**Note:** GET endpoint is RESTful and recommended. POST endpoint is available as fallback for very long lists (>50 slugs) where URL length may be an issue.

---

### Scenario 6: Refresh Movie from TMDB

**Objective:** Verify refreshing movie metadata from TMDB.

**⚠️ Important:** This endpoint requires `POST` method. Using `GET` will return `405 Method Not Allowed`.

**Steps:**

1. **Refresh movie (must use POST):**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/refresh" \
     -H "Accept: application/json" | jq
   ```

   **Common mistake:** Opening this URL in a browser (which uses GET) will fail.
   Always use `curl -X POST` or a tool like Postman/Insomnia.

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

### Scenario 7: Movie Collections

**Objective:** Verify collection endpoint returns all movies in the same TMDb collection.

**Prerequisites:**
- Movies must have TMDb snapshots with `belongs_to_collection` data
- At least 2 movies in the same collection

**Steps:**

1. **Get collection for a movie:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/collection" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response format:**

   ```json
   {
     "collection": {
       "name": "The Matrix Collection",
       "tmdb_collection_id": 234,
       "count": 3
     },
     "movies": [
       {
         "id": "...",
         "slug": "the-matrix-1999",
         "title": "The Matrix",
         "release_year": 1999,
         "_links": {...}
       },
       {
         "id": "...",
         "slug": "the-matrix-reloaded-2003",
         "title": "The Matrix Reloaded",
         "release_year": 2003,
         "_links": {...}
       },
       {
         "id": "...",
         "slug": "the-matrix-revolutions-2003",
         "title": "The Matrix Revolutions",
         "release_year": 2003,
         "_links": {...}
       }
     ],
     "_links": {
       "self": "http://localhost:8000/api/v1/movies/the-matrix-1999/collection"
     }
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] `collection.name` matches TMDb collection name
   - [ ] `collection.tmdb_collection_id` is a valid TMDb collection ID
   - [ ] `collection.count` matches number of movies in collection
   - [ ] `movies` array contains all movies from the same collection
   - [ ] Each movie has complete data (slug, title, release_year, etc.)
   - [ ] `_links.self` is present and correct

4. **Test with movie without collection:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/standalone-movie-2020/collection" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status code: `404 Not Found`
   - [ ] Error message indicates collection not found

5. **Test with movie without TMDb snapshot:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/movie-without-snapshot-2020/collection" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status code: `404 Not Found`
   - [ ] Error message indicates snapshot not found

6. **Test with non-existent movie:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/non-existent-movie-2020/collection" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status code: `404 Not Found`
   - [ ] Error message indicates movie not found

**Note:** Collections are retrieved from TMDb snapshots. If a movie doesn't have a snapshot or the snapshot doesn't contain `belongs_to_collection` data, the endpoint returns 404.

---

### Scenario 8: Movie Relationships

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

   **Common mistake:** Opening this URL in a browser (which uses GET) will fail.
   Always use `curl -X POST` or a tool like Postman/Insomnia.

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

**Important:** Movie relationships are stored **locally in the database** (table `movie_relationships`),
but they are **synchronized from TMDB** asynchronously.

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
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type=collection" | jq
```

**Filter by genre:**

```bash
# Single genre
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?genre=science-fiction" | jq

# Multiple genres (AND logic)
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?genres[]=science-fiction&genres[]=action" | jq
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

### Scenario 3: Filter Related Movies by Genre

**Objective:** Verify filtering related movies by genre.

**Prerequisites:**
- Movie must have related movies
- Related movies must have genres assigned

**Steps:**

1. **Filter by single genre:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?genre=science-fiction" \
     -H "Accept: application/json" | jq
   ```

2. **Filter by multiple genres (AND logic - movie must have ALL genres):**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?genres[]=science-fiction&genres[]=action" \
     -H "Accept: application/json" | jq
   ```

3. **Filter by genre with type filter:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type=collection&genre=science-fiction" \
     -H "Accept: application/json" | jq
   ```

4. **Verify:**
   - [ ] Only movies with specified genre(s) are returned
   - [ ] When multiple genres specified, only movies with ALL genres are returned (AND logic)
   - [ ] Genre filter is case-insensitive (e.g., `SCIENCE-FICTION` works the same as `science-fiction`)
   - [ ] `count` matches filtered results
   - [ ] Empty result when no movies match genre filter

5. **Test case-insensitive filtering:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?genre=SCIENCE-FICTION" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Should return same results as lowercase `science-fiction`

**Note:** Genre filtering works with both `collection` and `similar` types. If a movie doesn't have genres assigned, it won't match any genre filter.

---

### Scenario 4: Compare Two Movies

**Objective:** Verify comparing two movies to find common elements and differences.

**Prerequisites:**
- Two movies must exist in the database
- Movies should have genres and people assigned (optional, for richer comparison)

**Steps:**

1. **Compare two movies:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/compare?slug1=the-matrix-1999&slug2=inception-2010" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response structure:**

   ```json
   {
     "movie1": {
       "id": "...",
       "slug": "the-matrix-1999",
       "title": "The Matrix",
       "release_year": 1999,
       "director": "..."
     },
     "movie2": {
       "id": "...",
       "slug": "inception-2010",
       "title": "Inception",
       "release_year": 2010,
       "director": "..."
     },
     "comparison": {
       "common_genres": ["Science Fiction", "Action"],
       "common_people": [
         {
           "person": {
             "id": "...",
             "slug": "keanu-reeves",
             "name": "Keanu Reeves"
           },
           "roles_in_movie1": ["ACTOR"],
           "roles_in_movie2": ["ACTOR"]
         }
       ],
       "year_difference": 11,
       "similarity_score": 0.75
     }
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] `movie1` and `movie2` contain basic movie information
   - [ ] `common_genres` array lists genres present in both movies
   - [ ] `common_people` array lists people who worked on both movies with their roles
   - [ ] `year_difference` is the absolute difference in release years
   - [ ] `similarity_score` is a float between 0.0 and 1.0

4. **Test with movies that have no common elements:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/compare?slug1=movie1&slug2=movie2" \
     -H "Accept: application/json" | jq
   ```

   - [ ] `common_genres` is an empty array
   - [ ] `common_people` is an empty array
   - [ ] `similarity_score` is low (close to 0.0)

5. **Test validation (missing parameters):**

   ```bash
   # Missing slug1
   curl -X GET "http://localhost:8000/api/v1/movies/compare?slug2=test" \
     -H "Accept: application/json" | jq
   # Should return 422 Unprocessable Entity

   # Missing slug2
   curl -X GET "http://localhost:8000/api/v1/movies/compare?slug1=test" \
     -H "Accept: application/json" | jq
   # Should return 422 Unprocessable Entity
   ```

6. **Test with non-existent movies:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/compare?slug1=non-existent-123&slug2=also-non-existent-456" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status code: `404 Not Found`

**Note:** Similarity score is calculated based on:
- Common genres (40% weight)
- Common people (40% weight)
- Year proximity (20% weight)

---

### Scenario 5: Empty Relationships

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

## 📝 Movie Reports

### Endpoints Overview

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `POST` | `/api/v1/movies/{slug}/report` | Report an error in movie description | No |
| `GET` | `/api/v1/admin/reports` | List all movie reports (with filtering) | Yes |
| `POST` | `/api/v1/admin/reports/{id}/verify` | Verify report and trigger regeneration | Yes |

### Report Types

- `grammar` - Grammar or spelling errors (weight: 1)
- `factual_error` - Factual inaccuracies (weight: 3)
- `inappropriate` - Inappropriate content (weight: 5)
- `hallucination` - AI hallucination (weight: 5)
- `other` - Other issues (weight: 1)

### Report Statuses

- `pending` - Report submitted, awaiting verification
- `verified` - Report verified by admin, regeneration queued
- `resolved` - Description regenerated, issue resolved
- `rejected` - Report rejected by admin

### Priority Scoring

Reports are automatically assigned a priority score based on:

- Report type weight (see above)
- Number of pending reports of the same type

**Priority thresholds:**

- **High:** `priority_score >= 3.0` (factual errors, inappropriate content, hallucinations)
- **Medium:** `1.0 <= priority_score < 3.0` (grammar errors with multiple reports)
- **Low:** `priority_score < 1.0` (single grammar/other reports)

---

### Scenario 1: User Reports an Error

**Objective:** Verify that users can report errors in movie descriptions.

**Steps:**

1. **Report a grammar error:**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{
       "type": "grammar",
       "message": "There is a typo in the description: 'recieve' should be 'receive'",
       "suggested_fix": "Change 'recieve' to 'receive'",
       "description_id": "550e8400-e29b-41d4-a716-446655440000"
     }' | jq
   ```

2. **Verify response:**

   ```json
   {
     "report_id": "660e8400-e29b-41d4-a716-446655440001",
     "priority_score": 1.0,
     "status": "pending"
   }
   ```

3. **Verify:**
   - [ ] Status code: `201 Created`
   - [ ] `report_id` present (UUID)
   - [ ] `priority_score` calculated automatically
   - [ ] Status is `pending`

4. **Report a factual error (higher priority):**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "factual_error",
       "message": "The description incorrectly states the release year as 2000, but it was 1999",
       "suggested_fix": "Update release year to 1999"
     }' | jq
   ```

   - [ ] Priority score is `3.0` (higher than grammar)

5. **Report without description_id (general movie issue):**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "inappropriate",
       "message": "The description contains inappropriate language"
     }' | jq
   ```

   - [ ] Report created successfully (description_id is optional)
   - [ ] Priority score is `5.0` (highest)

6. **Test validation errors:**

   ```bash
   # Missing required fields
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "grammar"
     }' | jq
   ```

   - [ ] Status code: `422 Unprocessable Entity`
   - [ ] Error message indicates missing `message`

   ```bash
   # Invalid report type
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "invalid_type",
       "message": "Test message"
     }' | jq
   ```

   - [ ] Status code: `422 Unprocessable Entity`
   - [ ] Error message indicates invalid type

7. **Test with non-existent movie:**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/movies/non-existent-movie/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "grammar",
       "message": "Test message"
     }' | jq
   ```

   - [ ] Status code: `404 Not Found`

---

### Scenario 2: Admin Lists Reports

**Objective:** Verify that admins can list and filter reports.

**Prerequisites:** Admin authentication required (Basic Auth)

**Steps:**

1. **List all reports:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response structure:**

   ```json
   {
     "data": [
       {
         "id": "660e8400-e29b-41d4-a716-446655440001",
         "movie_id": "550e8400-e29b-41d4-a716-446655440000",
         "description_id": "770e8400-e29b-41d4-a716-446655440002",
         "type": "factual_error",
         "message": "The description incorrectly states the release year...",
         "suggested_fix": "Update release year to 1999",
         "status": "pending",
         "priority_score": 3.0,
         "verified_by": null,
         "verified_at": null,
         "resolved_at": null,
         "created_at": "2025-12-18T13:24:00+00:00"
       }
     ],
     "meta": {
       "current_page": 1,
       "per_page": 50,
       "total": 1,
       "last_page": 1
     }
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] Reports sorted by `priority_score DESC, created_at DESC`
   - [ ] High priority reports appear first
   - [ ] Pagination metadata present

4. **Filter by status:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?status=pending" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Only pending reports returned
   - [ ] Status code: `200 OK`

5. **Filter by priority (high):**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?priority=high" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Only reports with `priority_score >= 3.0` returned
   - [ ] All returned reports have high priority

6. **Filter by priority (medium):**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?priority=medium" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Only reports with `1.0 <= priority_score < 3.0` returned

7. **Filter by priority (low):**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?priority=low" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Only reports with `priority_score < 1.0` returned

8. **Combine filters:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?status=pending&priority=high" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Only high-priority pending reports returned

9. **Test pagination:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?per_page=10&page=1" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Maximum 10 reports per page
   - [ ] Pagination metadata correct

10. **Test without authentication:**

    ```bash
    curl -X GET "http://localhost:8000/api/v1/admin/reports" \
      -H "Accept: application/json" | jq
    ```

    - [ ] Status code: `401 Unauthorized`

---

### Scenario 3: Admin Verifies Report

**Objective:** Verify that admins can verify reports and trigger automatic regeneration.

**Prerequisites:**

- Admin authentication required
- Queue worker must be running (for regeneration job)

**Steps:**

1. **Verify a report:**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/admin/reports/660e8400-e29b-41d4-a716-446655440001/verify" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**

   ```json
   {
     "id": "660e8400-e29b-41d4-a716-446655440001",
     "movie_id": "550e8400-e29b-41d4-a716-446655440000",
     "description_id": "770e8400-e29b-41d4-a716-446655440002",
     "status": "verified",
     "verified_at": "2025-12-18T13:25:00+00:00"
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] Status changed to `verified`
   - [ ] `verified_at` timestamp present
   - [ ] Regeneration job queued (check queue logs)

4. **Check queue for regeneration job:**

   ```bash
   # Check Horizon dashboard or queue logs
   tail -f storage/logs/laravel.log | grep "RegenerateMovieDescriptionJob"
   ```

   - [ ] `RegenerateMovieDescriptionJob` dispatched
   - [ ] Job contains correct `movie_id` and `description_id`

5. **Verify report after regeneration job completes:**

   ```bash
   # Wait for job to complete, then check report status
   curl -X GET "http://localhost:8000/api/v1/admin/reports/660e8400-e29b-41d4-a716-446655440001" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status changed to `resolved` (after job completion)
   - [ ] `resolved_at` timestamp present
   - [ ] Description text updated (check movie endpoint)

6. **Test with non-existent report:**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/admin/reports/non-existent-id/verify" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status code: `404 Not Found`

7. **Test without authentication:**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/admin/reports/660e8400-e29b-41d4-a716-446655440001/verify" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status code: `401 Unauthorized`

8. **Test verification without description_id:**

   ```bash
   # Create report without description_id
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "other",
       "message": "General movie issue"
     }' | jq
   
   # Verify the report
   curl -X POST "http://localhost:8000/api/v1/admin/reports/{report_id}/verify" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Report verified successfully
   - [ ] No regeneration job queued (description_id is null)

---

### Scenario 4: End-to-End Report Flow

**Objective:** Verify complete flow from user report to resolution.

**Steps:**

1. **User reports an error:**

   ```bash
   REPORT_RESPONSE=$(curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "factual_error",
       "message": "The description contains incorrect information about the director",
       "suggested_fix": "Update director information"
     }' | jq -r '.report_id')
   
   echo "Report ID: $REPORT_RESPONSE"
   ```

2. **Admin lists reports and sees new report:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?status=pending&priority=high" \
     -u "admin:password" \
     -H "Accept: application/json" | jq '.data[] | select(.id == "'"$REPORT_RESPONSE"'")'
   ```

   - [ ] Report appears in high-priority pending reports
   - [ ] Priority score is `3.0` (factual_error weight)

3. **Admin verifies report:**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/admin/reports/$REPORT_RESPONSE/verify" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status changed to `verified`
   - [ ] Regeneration job queued

4. **Wait for regeneration job to complete:**

   ```bash
   # Check job status in Horizon or logs
   # Wait for RegenerateMovieDescriptionJob to complete
   ```

5. **Verify report is resolved:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports/$REPORT_RESPONSE" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

   - [ ] Status is `resolved`
   - [ ] `resolved_at` timestamp present

6. **Verify description was regenerated:**

   ```bash
   # Get movie with updated description
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
     -H "Accept: application/json" | jq '.descriptions[] | select(.id == "description_id_from_report")'
   ```

   - [ ] Description text updated
   - [ ] New `ai_model` and `created_at` timestamp

---

### Scenario 5: Priority Score Calculation

**Objective:** Verify that priority scores are calculated correctly.

**Steps:**

1. **Create multiple reports of different types:**

   ```bash
   # Grammar error (weight: 1)
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{"type": "grammar", "message": "Typo in description"}' | jq '.priority_score'
   # Should return: 1.0
   
   # Factual error (weight: 3)
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{"type": "factual_error", "message": "Incorrect information"}' | jq '.priority_score'
   # Should return: 3.0
   
   # Inappropriate content (weight: 5)
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{"type": "inappropriate", "message": "Inappropriate language"}' | jq '.priority_score'
   # Should return: 5.0
   ```

2. **Verify priority filtering:**

   ```bash
   # High priority (>= 3.0)
   curl -X GET "http://localhost:8000/api/v1/admin/reports?priority=high" \
     -u "admin:password" | jq '.data[] | .priority_score'
   # Should only show: 3.0, 5.0
   
   # Medium priority (1.0 <= score < 3.0)
   curl -X GET "http://localhost:8000/api/v1/admin/reports?priority=medium" \
     -u "admin:password" | jq '.data[] | .priority_score'
   # Should only show: 1.0
   
   # Low priority (< 1.0)
   curl -X GET "http://localhost:8000/api/v1/admin/reports?priority=low" \
     -u "admin:password" | jq '.data[] | .priority_score'
   # Should be empty (all reports have score >= 1.0)
   ```

---

### Troubleshooting Movie Reports

**Problem: Report not appearing in admin list**

**Solution:**

- Verify report was created: Check database `movie_reports` table
- Check authentication: Admin endpoint requires Basic Auth
- Verify status filter: Report might be filtered out

**Problem: Regeneration job not queued after verification**

**Solution:**

- Check if `description_id` is null (no job queued for general movie reports)
- Verify queue worker is running: `php artisan queue:work` or Horizon
- Check logs for job dispatch errors

**Problem: Priority score seems incorrect**

**Solution:**

- Verify report type weight in `ReportType` enum
- Check if multiple reports of same type affect aggregation
- Verify priority calculation logic in `MovieReportService`

---

## 📝 Person Reports

### Endpoints Overview

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `POST` | `/api/v1/people/{slug}/report` | Report an error in person biography | No |
| `GET` | `/api/v1/admin/reports?type=person` | List person reports (with filtering) | Yes |
| `POST` | `/api/v1/admin/reports/{id}/verify` | Verify report and trigger bio regeneration | Yes |

**Note:** Admin endpoints support both movie and person reports. Use `?type=person` to filter only person reports, or `?type=all` to see both types.

### Report Types

Same as Movie Reports:
- `FACTUAL_ERROR` - Factual inaccuracies (weight: 3.0)
- `GRAMMAR_ERROR` - Grammar or spelling errors (weight: 1.0)
- `INAPPROPRIATE_CONTENT` - Inappropriate content (weight: 5.0)
- `OTHER` - Other issues (weight: 1.0)

### Priority Scoring

Same logic as Movie Reports:
- Report type weight × number of pending reports of same type
- **High:** `priority_score >= 3.0`
- **Medium:** `1.0 <= priority_score < 3.0`
- **Low:** `priority_score < 1.0`

---

### Scenario 1: User Reports Person Biography Error

**Objective:** Verify that users can report errors in person biographies.

**Steps:**

1. **Report a factual error:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves-1964/report" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
     -d '{
       "type": "FACTUAL_ERROR",
       "message": "Birth date is incorrect. Should be 1964-09-02, not 1964-09-03.",
       "suggested_fix": "Update birth_date to 1964-09-02"
     }' | jq
   ```

2. **Verify response:**
   ```json
   {
     "data": {
       "id": "550e8400-e29b-41d4-a716-446655440000",
       "person_id": "123e4567-e89b-12d3-a456-426614174000",
       "bio_id": null,
       "type": "FACTUAL_ERROR",
       "message": "Birth date is incorrect...",
       "suggested_fix": "Update birth_date to 1964-09-02",
       "status": "pending",
       "priority_score": 3.0,
       "created_at": "2025-12-23T10:30:00+00:00"
     }
   }
   ```

3. **Verify:**
   - [ ] Status code: `201 Created`
   - [ ] Report ID is returned (UUID)
   - [ ] `person_id` matches the person
   - [ ] `type` is valid enum value
   - [ ] `status` is `pending`
   - [ ] `priority_score` is calculated

4. **Test with bio_id (specific bio report):**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves-1964/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "GRAMMAR_ERROR",
       "message": "There is a grammatical error in the biography text.",
       "bio_id": "550e8400-e29b-41d4-a716-446655440001"
     }' | jq
   ```
   - [ ] `bio_id` is included in response
   - [ ] Report is linked to specific bio

5. **Test validation errors:**
   ```bash
   # Missing required field
   curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves-1964/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "FACTUAL_ERROR"
     }' | jq
   ```
   - [ ] Status code: `422 Unprocessable Entity`
   - [ ] Error message indicates missing `message` field

6. **Test non-existent person:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/people/non-existent-person-9999/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "FACTUAL_ERROR",
       "message": "This person does not exist"
     }' | jq
   ```
   - [ ] Status code: `404 Not Found`

---

### Scenario 2: Admin Lists Person Reports

**Objective:** Verify that admins can list and filter person reports.

**Steps:**

1. **List all reports (including person reports):**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?type=all" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

2. **List only person reports:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?type=person" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

3. **Verify response structure:**
   ```json
   {
     "data": [
       {
         "id": "550e8400-e29b-41d4-a716-446655440000",
         "entity_type": "person",
         "person_id": "123e4567-e89b-12d3-a456-426614174000",
         "bio_id": "550e8400-e29b-41d4-a716-446655440001",
         "type": "FACTUAL_ERROR",
         "message": "Birth date is incorrect...",
         "status": "pending",
         "priority_score": 3.0,
         "created_at": "2025-12-23T10:30:00+00:00"
       }
     ],
     "meta": {
       "current_page": 1,
       "per_page": 50,
       "total": 1,
       "last_page": 1
     }
   }
   ```

4. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] `entity_type` is `"person"` for person reports
   - [ ] `person_id` and `bio_id` are present
   - [ ] Reports are sorted by `priority_score` desc, then `created_at` desc

5. **Filter by status:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?type=person&status=pending" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Only pending reports returned

6. **Filter by priority:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?type=person&priority=high" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Only reports with `priority_score >= 3.0` returned

---

### Scenario 3: Admin Verifies Person Report

**Objective:** Verify that admins can verify person reports and trigger bio regeneration.

**Prerequisites:**
- Queue worker must be running: `php artisan queue:work` or Laravel Horizon
- Person must have a bio (for regeneration to work)

**Steps:**

1. **Verify a person report:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/admin/reports/{report_id}/verify" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**
   ```json
   {
     "id": "550e8400-e29b-41d4-a716-446655440000",
     "entity_type": "person",
     "person_id": "123e4567-e89b-12d3-a456-426614174000",
     "bio_id": "550e8400-e29b-41d4-a716-446655440001",
     "status": "verified",
     "verified_at": "2025-12-23T11:00:00+00:00"
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] `status` changed to `verified`
   - [ ] `verified_at` timestamp is set
   - [ ] `entity_type` is `"person"`

4. **Check queue for regeneration job:**
   - [ ] `RegeneratePersonBioJob` is queued (if `bio_id` is present)
   - [ ] Job parameters: `person_id` and `bio_id` match the report

5. **Test verification without bio_id:**
   ```bash
   # Verify a report that has bio_id = null
   curl -X POST "http://localhost:8000/api/v1/admin/reports/{report_id}/verify" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Status code: `200 OK`
   - [ ] Report is verified
   - [ ] No regeneration job is queued (bio_id is null)

6. **Test non-existent report:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/admin/reports/00000000-0000-0000-0000-000000000000/verify" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Status code: `404 Not Found`

---

### Scenario 4: Bio Regeneration After Verification

**Objective:** Verify that bio regeneration works after report verification.

**Prerequisites:**
- Report must be verified (use Scenario 3)
- Queue worker must process the job
- OpenAI API key must be configured (or use mock mode)

**Steps:**

1. **Verify the report (see Scenario 3)**

2. **Wait for job to process (or manually trigger)**

3. **Verify old bio is deleted:**
   ```sql
   -- Check that old bio no longer exists (due to unique constraint)
   SELECT * FROM person_bios WHERE id = '{old_bio_id}';
   ```
   - [ ] Old bio is deleted (or text prefixed with `[ARCHIVED]`)

4. **Verify new bio is created:**
   ```sql
   -- Check new bio exists
   SELECT * FROM person_bios WHERE person_id = '{person_id}' ORDER BY created_at DESC LIMIT 1;
   ```
   - [ ] New bio exists with same `locale` and `context_tag`
   - [ ] New bio has updated `text` (regenerated by AI)

5. **Verify person's default_bio_id is updated (if old bio was default):**
   ```sql
   SELECT default_bio_id FROM people WHERE id = '{person_id}';
   ```
   - [ ] `default_bio_id` points to new bio (if old bio was default)

6. **Verify reports are marked as resolved:**
   ```sql
   SELECT * FROM person_reports 
   WHERE person_id = '{person_id}' 
   AND bio_id IN ('{old_bio_id}', '{new_bio_id}')
   AND status = 'resolved';
   ```
   - [ ] Related reports are marked as `resolved`
   - [ ] `resolved_at` timestamp is set

---

### Troubleshooting Person Reports

**Problem: Report not appearing in admin list**

**Solution:**
- Verify report was created: Check database `person_reports` table
- Check authentication: Admin endpoint requires Basic Auth
- Verify type filter: Use `?type=person` to see only person reports
- Verify status filter: Report might be filtered out

**Problem: Regeneration job not queued after verification**

**Solution:**
- Check if `bio_id` is null (no job queued for general person reports)
- Verify queue worker is running: `php artisan queue:work` or Horizon
- Check logs for job dispatch errors

**Problem: Priority score seems incorrect**

**Solution:**
- Verify report type weight in `ReportType` enum
- Check if multiple reports of same type affect aggregation
- Verify priority calculation logic in `PersonReportService`

**Problem: Bio regeneration fails**

**Solution:**
- Verify OpenAI API key is configured (or mock mode is enabled)
- Check queue worker logs: `tail -f storage/logs/laravel.log`
- Verify person exists and has valid slug
- Check unique constraint on `person_bios` table (person_id, locale, context_tag)

---

## ⚡ Adaptive Rate Limiting

### Overview

Adaptive Rate Limiting dynamically adjusts API rate limits based on real-time system load.
Instead of fixed limits, the system monitors CPU load, queue size, and active jobs,
then automatically adjusts rate limits to maintain stability.

**Protected Endpoints:**

- `GET /api/v1/movies/search` - Search endpoint (default: 100 req/min)
- `POST /api/v1/generate` - AI generation endpoint (default: 10 req/min)
- `POST /api/v1/movies/{slug}/report` - Movie report endpoint (default: 20 req/min)
- `POST /api/v1/people/{slug}/report` - Person report endpoint (default: 20 req/min)

**Load Monitoring:**

- **CPU Load** (40% weight) - System CPU utilization
- **Queue Size** (40% weight) - Number of pending jobs in Redis queue
- **Active Jobs** (20% weight) - Number of currently processing jobs

**Load Levels:**

- **Low** (<30%) - Default limits applied (100/10/20 req/min)
- **Medium** (30-50%) - 20% reduction (80/8/16 req/min)
- **High** (50-70%) - 50% reduction (50/5/10 req/min)
- **Critical** (>70%) - Minimum limits (20/2/5 req/min)

---

### Scenario 1: Verify Rate Limit Headers

**Objective:** Verify that rate limit headers are included in responses.

**Steps:**

1. **Send a request to protected endpoint:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
     -H "Accept: application/json" -v 2>&1 | grep -i "rate"
   ```

2. **Verify response headers:**
   - [ ] `X-RateLimit-Limit` header present (e.g., `100`)
   - [ ] `X-RateLimit-Remaining` header present (e.g., `99`)
   - [ ] Headers reflect current dynamic limit (not always default)

3. **Test all protected endpoints:**

   ```bash
   # Search endpoint
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
     -H "Accept: application/json" -i | grep -i "rate"
   
   # Generate endpoint
   curl -X POST "http://localhost:8000/api/v1/generate" \
     -H "Content-Type: application/json" \
     -d '{"entity_type": "MOVIE", "slug": "the-matrix-1999"}' \
     -i | grep -i "rate"
   
   # Report endpoint
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{"type": "grammar", "message": "Test"}' \
     -i | grep -i "rate"
   ```

   - [ ] All endpoints return rate limit headers
   - [ ] Limits differ per endpoint (search > report > generate)

---

### Scenario 2: Test Rate Limit Enforcement

**Objective:** Verify that rate limits are enforced and 429 responses are returned when exceeded.

**Steps:**

1. **Send multiple requests quickly:**

   ```bash
   # Send 150 requests to search endpoint (limit: 100/min)
   for i in {1..150}; do
     curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
       -H "Accept: application/json" -w "\nStatus: %{http_code}\n" \
       -o /dev/null -s
     sleep 0.1
   done
   ```

2. **Verify rate limiting:**
   - [ ] First ~100 requests return `200 OK`
   - [ ] Remaining requests return `429 Too Many Requests`
   - [ ] `429` response includes `Retry-After` header
   - [ ] `X-RateLimit-Remaining` shows `0` when limit exceeded

3. **Check 429 response structure:**

   ```bash
   # Trigger rate limit
   for i in {1..105}; do
     curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
       -H "Accept: application/json" -s
   done | jq '.'
   ```

   **Expected 429 response:**

   ```json
   {
     "error": "Too many requests",
     "message": "Rate limit exceeded. Please try again later.",
     "retry_after": 45
   }
   ```

   - [ ] Status code: `429 Too Many Requests`
   - [ ] Error message present
   - [ ] `retry_after` seconds indicated

---

### Scenario 3: Test Dynamic Limit Adjustment

**Objective:** Verify that rate limits adjust based on system load.

**Prerequisites:** Ability to simulate system load (CPU, queue, active jobs)

**Steps:**

1. **Check default limits under normal load:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
     -H "Accept: application/json" -i | grep "X-RateLimit-Limit"
   # Should show: X-RateLimit-Limit: 100
   ```

2. **Generate system load:**

   ```bash
   # Option A: Fill queue with jobs
   for i in {1..1000}; do
     curl -X POST "http://localhost:8000/api/v1/generate" \
       -H "Content-Type: application/json" \
       -d '{"entity_type": "MOVIE", "slug": "the-matrix-1999"}' -s > /dev/null
   done
   
   # Option B: Generate CPU load (Docker)
   docker compose exec php sh -c 'for i in $(seq 1 4); do while true; do :; done & done'
   ```

3. **Wait for load to register (5-10 seconds):**

   ```bash
   sleep 10
   ```

4. **Check adjusted limits:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
     -H "Accept: application/json" -i | grep "X-RateLimit-Limit"
   # Should show reduced limit (e.g., 50, 20, etc.)
   ```

5. **Verify:**
   - [ ] Limit reduced under high load
   - [ ] Limit never goes below minimum (20 for search, 2 for generate, 5 for report)
   - [ ] After load decreases, limit returns to normal

6. **Check logs for rate limit changes:**

   ```bash
   tail -f api/storage/logs/laravel.log | grep -i "rate limit"
   ```

   - [ ] Logs show load factor and adjusted limits
   - [ ] Logs indicate when limits change

7. **Cleanup (stop CPU load):**

   ```bash
   # Kill CPU load processes
   docker compose exec php sh -c 'pkill -f "while true"'
   ```

---

### Scenario 4: Test Per-Endpoint Limits

**Objective:** Verify that each endpoint has independent rate limits.

**Steps:**

1. **Exhaust search endpoint limit:**

   ```bash
   # Send 105 requests to search (exceed 100/min limit)
   for i in {1..105}; do
     curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
       -H "Accept: application/json" -w "%{http_code}\n" -o /dev/null -s
   done
   ```

2. **Test generate endpoint (should still work):**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/generate" \
     -H "Content-Type: application/json" \
     -d '{"entity_type": "MOVIE", "slug": "the-matrix-1999"}' \
     -w "%{http_code}\n" -o /dev/null -s
   # Should return: 202 (or 429 only if generate limit also exceeded)
   ```

3. **Test report endpoint (should still work):**

   ```bash
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{"type": "grammar", "message": "Test"}' \
     -w "%{http_code}\n" -o /dev/null -s
   # Should return: 201 (or 429 only if report limit also exceeded)
   ```

4. **Verify:**
   - [ ] Search endpoint rate-limited (429)
   - [ ] Generate endpoint still accessible (different limit)
   - [ ] Report endpoint still accessible (different limit)
   - [ ] Each endpoint has independent counter

---

### Scenario 5: Test Minimum Limits

**Objective:** Verify that rate limits never go below minimum values even under critical load.

**Steps:**

1. **Simulate critical system load:**

   ```bash
   # Fill queue to maximum
   for i in {1..2000}; do
     curl -X POST "http://localhost:8000/api/v1/generate" \
       -H "Content-Type: application/json" \
       -d '{"entity_type": "MOVIE", "slug": "the-matrix-1999"}' -s > /dev/null
   done
   
   # Generate CPU load
   docker compose exec php sh -c 'for i in $(seq 1 4); do while true; do :; done & done'
   ```

2. **Wait for load to register:**

   ```bash
   sleep 15
   ```

3. **Check minimum limits:**

   ```bash
   # Search endpoint (minimum: 20 req/min)
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
     -H "Accept: application/json" -i | grep "X-RateLimit-Limit"
   # Should show: X-RateLimit-Limit: 20 (minimum)
   
   # Generate endpoint (minimum: 2 req/min)
   curl -X POST "http://localhost:8000/api/v1/generate" \
     -H "Content-Type: application/json" \
     -d '{"entity_type": "MOVIE", "slug": "the-matrix-1999"}' \
     -i | grep "X-RateLimit-Limit"
   # Should show: X-RateLimit-Limit: 2 (minimum)
   
   # Report endpoint (minimum: 5 req/min)
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/report" \
     -H "Content-Type: application/json" \
     -d '{"type": "grammar", "message": "Test"}' \
     -i | grep "X-RateLimit-Limit"
   # Should show: X-RateLimit-Limit: 5 (minimum)
   ```

4. **Verify:**
   - [ ] Limits never go below minimum values
   - [ ] API remains accessible even under critical load
   - [ ] Minimum limits are endpoint-specific

5. **Cleanup:**

   ```bash
   # Stop CPU load
   docker compose exec php sh -c 'pkill -f "while true"'
   ```

---

### Scenario 6: Test Load Recovery

**Objective:** Verify that rate limits return to normal after load decreases.

**Steps:**

1. **Generate load and verify reduction:**

   ```bash
   # Generate load
   for i in {1..1000}; do
     curl -X POST "http://localhost:8000/api/v1/generate" \
       -H "Content-Type: application/json" \
       -d '{"entity_type": "MOVIE", "slug": "the-matrix-1999"}' -s > /dev/null
   done
   
   # Wait and check reduced limit
   sleep 10
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
     -H "Accept: application/json" -i | grep "X-RateLimit-Limit"
   # Should show reduced limit
   ```

2. **Wait for load to decrease:**

   ```bash
   # Let queue process (wait 30-60 seconds)
   echo "Waiting for load to decrease..."
   sleep 60
   ```

3. **Check if limits returned to normal:**

   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
     -H "Accept: application/json" -i | grep "X-RateLimit-Limit"
   # Should show: X-RateLimit-Limit: 100 (or close to default)
   ```

4. **Verify:**
   - [ ] Limits increase as load decreases
   - [ ] Limits eventually return to default values
   - [ ] System recovers gracefully

---

### Troubleshooting Adaptive Rate Limiting

**Problem: Rate limits seem too restrictive**

**Solution:**

- Check system load: High CPU/queue/active jobs → lower limits
- Verify configuration: `api/config/rate-limiting.php`
- Check logs for load factor calculations: `tail -f api/storage/logs/laravel.log | grep "rate limit"`

**Problem: Rate limits not adjusting with load**

**Solution:**

- Verify CPU load monitoring is enabled: Check `sys_getloadavg()` availability
- Check queue size monitoring: Verify Redis connection
- Check active jobs monitoring: Verify Horizon is running
- Review logs for errors in load calculation

**Problem: Getting 429 even with low system load**

**Solution:**

- Check if you've exceeded the rate limit in the current window (1 minute)
- Wait for the `Retry-After` period before retrying
- Verify rate limit key (should be per IP address)
- Check logs for rate limit violations

**Problem: CPU load shows 0.0 or not available**

**Solution:**

- This is normal in Docker containers where `sys_getloadavg()` may not reflect container CPU
- System falls back to queue and active jobs monitoring
- Check `docs/CPU_LOAD_VERIFICATION_RESULTS.md` for Docker-specific notes

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
| Movie Reports | X | Y | Z |
| Adaptive Rate Limiting | X | Y | Z |
| Health/Admin | X | Y | Z |

## Issues Found

[Issue descriptions]

## Recommendations

[Recommendations]
```

---

**Last updated:** 2025-12-18  
**Version:** 1.0

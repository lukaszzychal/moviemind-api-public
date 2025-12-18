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

### Step 1: Start API Server

```bash
cd api
php artisan serve
# Server should start on http://localhost:8000
```

**Verify:**
```bash
curl http://localhost:8000/api/v1/health/openai
# Should return 200 OK
```

### Step 2: Run Migrations

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

### Step 3: Start Queue Worker

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

### Step 4: Verify API Health

```bash
# Check OpenAI health (this verifies API connectivity)
curl -X GET "http://localhost:8000/api/v1/health/openai"
# Should return status 200 with OpenAI status

# Note: TMDB API access is verified indirectly when creating/searching movies
# There's no dedicated TMDB health endpoint
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

**Refresh movie:**
```bash
curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/refresh" | jq
```

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
     "people": [
       {
         "id": 1,
         "name": "Keanu Reeves",
         "slug": "keanu-reeves",
         "role": "ACTOR",
         "character_name": "Neo"
       }
     ],
     "_links": {
       "self": {
         "href": "http://localhost:8000/api/v1/movies/the-matrix-1999"
       }
     }
   }
   ```

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] All movie fields present
   - [ ] `default_description` present (if exists)
   - [ ] `descriptions` array present with all descriptions
   - [ ] `people` array present (actors, crew)
   - [ ] **Security:** `tmdb_id` is **NOT** present
   - [ ] `_links` present

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

**Steps:**

1. **Refresh movie:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/refresh" \
     -H "Accept: application/json" | jq
   ```

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

**Refresh person:**
```bash
curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves/refresh" | jq
```

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

**Steps:**

1. **Refresh person:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves/refresh" \
     -H "Accept: application/json" | jq
   ```

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

**Steps:**

1. **Get related movies:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**
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

3. **Verify:**
   - [ ] Status code: `200 OK`
   - [ ] `movie` object present
   - [ ] `related_movies` array present
   - [ ] Each related movie has `relationship_type`, `relationship_label`, `relationship_order`
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

1. **Generate modern description:**
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

2. **Generate critical description:**
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

3. **Verify:**
   - [ ] Each context tag creates separate description
   - [ ] Multiple descriptions exist for same movie
   - [ ] Default description is not overwritten

---

### Scenario 3: Generate Person Bio

**Objective:** Verify generating a person biography.

**Steps:**

1. **Generate bio:**
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

2. **Verify:**
   - [ ] Status code: `202 Accepted`
   - [ ] Job created for person
   - [ ] Bio generated after job completion

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

### Scenario 2: Feature Flags (Admin)

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

### Scenario 3: Debug Configuration (Admin)

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


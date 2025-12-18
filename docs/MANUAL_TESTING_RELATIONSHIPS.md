# Manual Testing Guide - Movie Relationships

> **Created:** 2025-12-18  
> **Context:** Detailed manual testing instructions for Movie Relationships API endpoints  
> **Category:** reference  
> **Target Audience:** QA Engineers, Testers

## 📋 Related Documents

- [Main Testing Guide](../MANUAL_TESTING_GUIDE.md) - General setup and overview
- [Movies Testing Guide](../MANUAL_TESTING_MOVIES.md) - Movies API
- [People Testing Guide](../MANUAL_TESTING_PEOPLE.md) - People API

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Environment Setup](#environment-setup)
3. [Test Data Preparation](#test-data-preparation)
4. [Test Scenarios](#test-scenarios)
5. [Database Verification](#database-verification)
6. [Queue & Logs Verification](#queue--logs-verification)
7. [Performance Testing](#performance-testing)
8. [Security Verification](#security-verification)
9. [Troubleshooting](#troubleshooting)
10. [Test Report Template](#test-report-template)

---

## 🎯 Purpose

This document provides comprehensive step-by-step manual testing instructions for QA engineers to verify the Movie Relationships feature (Stage 4) implementation. It covers all aspects: API endpoints, database integrity, queue processing, security, and edge cases.

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
# Should show: "2025_12_18_132400_create_movie_relationships_table ... DONE"
```

**Verify migration:**
```bash
# Check if table exists (PostgreSQL)
psql -d moviemind -c "\d movie_relationships"

# Or check via Laravel tinker
php artisan tinker
>>> Schema::hasTable('movie_relationships')
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

## 🗄️ Test Data Preparation

### Method 1: Create Test Data via Database (SQL)

**Create movies with relationships:**

```sql
-- Insert test movies
INSERT INTO movies (title, slug, release_year, director, created_at, updated_at) VALUES
('The Matrix', 'the-matrix-1999', 1999, 'Wachowski Brothers', NOW(), NOW()),
('The Matrix Reloaded', 'the-matrix-reloaded-2003', 2003, 'Wachowski Brothers', NOW(), NOW()),
('The Matrix Revolutions', 'the-matrix-revolutions-2003', 2003, 'Wachowski Brothers', NOW(), NOW()),
('The Matrix Resurrections', 'the-matrix-resurrections-2021', 2021, 'Lana Wachowski', NOW(), NOW()),
('Standalone Movie', 'standalone-movie-2020', 2020, 'Test Director', NOW(), NOW());

-- Get movie IDs (adjust based on your database)
-- Assuming IDs: 1=Matrix, 2=Reloaded, 3=Revolutions, 4=Resurrections, 5=Standalone

-- Insert relationships
INSERT INTO movie_relationships (movie_id, related_movie_id, relationship_type, `order`, created_at, updated_at) VALUES
(1, 2, 'SEQUEL', 1, NOW(), NOW()),  -- Matrix -> Reloaded (sequel)
(1, 3, 'SEQUEL', 2, NOW(), NOW()),  -- Matrix -> Revolutions (sequel)
(1, 4, 'SEQUEL', 3, NOW(), NOW()),  -- Matrix -> Resurrections (sequel)
(2, 1, 'PREQUEL', 1, NOW(), NOW()),  -- Reloaded -> Matrix (prequel)
(3, 1, 'PREQUEL', 1, NOW(), NOW());  -- Revolutions -> Matrix (prequel)
```

### Method 2: Create Test Data via API (if endpoints exist)

```bash
# Search for a movie (this will create it if not exists)
curl -X GET "http://localhost:8000/api/v1/movies/search?query=matrix&year=1999" \
  -H "Accept: application/json"

# Wait for queue processing (relationships will be synced automatically)
# Check queue logs or Horizon dashboard
```

### Method 3: Use Laravel Tinker

```bash
php artisan tinker
```

```php
// Create movies
$matrix = \App\Models\Movie::create([
    'title' => 'The Matrix',
    'slug' => 'the-matrix-1999',
    'release_year' => 1999,
    'director' => 'Wachowski Brothers',
]);

$reloaded = \App\Models\Movie::create([
    'title' => 'The Matrix Reloaded',
    'slug' => 'the-matrix-reloaded-2003',
    'release_year' => 2003,
    'director' => 'Wachowski Brothers',
]);

// Create relationship
\App\Models\MovieRelationship::create([
    'movie_id' => $matrix->id,
    'related_movie_id' => $reloaded->id,
    'relationship_type' => \App\Enums\RelationshipType::SEQUEL,
    'order' => 1,
]);
```

---

## 🔍 Test Scenarios

### Scenario 1: Get Related Movies (Basic Functionality)

**Objective:** Verify that the endpoint returns related movies correctly.

**Given:** A movie exists with related movies (sequel, prequel)

**When:** A GET request is sent to `/api/v1/movies/{slug}/related`

**Then:**
- Response status should be `200 OK`
- Response should contain movie info and related movies array
- Each related movie should have `relationship_type`, `relationship_label`, and `relationship_order`

**Detailed Steps:**

1. **Prepare test data** (use Method 1, 2, or 3 above)

2. **Send GET request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" \
     -H "Accept: application/json" \
     -H "Content-Type: application/json" \
     -v
   ```

3. **Verify response structure:**
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
         "release_year": 2003,
         "director": "Wachowski Brothers",
         "genres": [],
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

4. **Verification Checklist:**
   - [ ] Status code: `200 OK`
   - [ ] `movie` object contains `id`, `slug`, `title`
   - [ ] `related_movies` is an array
   - [ ] Each related movie has:
     - [ ] `id`, `slug`, `title`
     - [ ] `relationship_type` (uppercase: SEQUEL, PREQUEL, etc.)
     - [ ] `relationship_label` (human-readable: "Sequel", "Prequel")
     - [ ] `relationship_order` (integer or null)
     - [ ] `_links.self` pointing to related movie endpoint
   - [ ] `count` matches number of items in `related_movies` array
   - [ ] `_links.self` points to `/api/v1/movies/{slug}/related`
   - [ ] `_links.movie` points to `/api/v1/movies/{slug}`
   - [ ] **Security:** `tmdb_id` is **NOT** present anywhere in response

5. **Test with multiple relationships:**
   ```bash
   # Should return all related movies
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" \
     -H "Accept: application/json" | jq '.related_movies | length'
   # Should return: 3 (if Matrix has 3 sequels)
   ```

---

### Scenario 2: Filter by Relationship Type (Single Type)

**Objective:** Verify that filtering by relationship type works correctly.

**Given:** A movie exists with related movies of different types (sequel, remake, prequel)

**When:** A GET request is sent with `type[]=SEQUEL` query parameter

**Then:** Only movies with SEQUEL relationship type should be returned

**Detailed Steps:**

1. **Prepare test data with multiple relationship types:**
   ```sql
   -- Create additional movies
   INSERT INTO movies (title, slug, release_year, director, created_at, updated_at) VALUES
   ('Matrix Remake', 'matrix-remake-2025', 2025, 'New Director', NOW(), NOW());

   -- Create relationships of different types
   INSERT INTO movie_relationships (movie_id, related_movie_id, relationship_type, created_at, updated_at) VALUES
   (1, 2, 'SEQUEL', NOW(), NOW()),      -- Sequel
   (1, 6, 'REMAKE', NOW(), NOW());      -- Remake (assuming ID 6 for remake)
   ```

2. **Test single type filter:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL" \
     -H "Accept: application/json" | jq
   ```

3. **Verify response:**
   - [ ] Status code: `200 OK`
   - [ ] Only SEQUEL relationships are returned
   - [ ] REMAKE relationships are excluded
   - [ ] `count` matches filtered results
   - [ ] All returned items have `relationship_type: "SEQUEL"`

4. **Test each relationship type:**
   ```bash
   # Test SEQUEL
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL" | jq '.count'
   
   # Test PREQUEL
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-reloaded-2003/related?type[]=PREQUEL" | jq '.count'
   
   # Test REMAKE
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=REMAKE" | jq '.count'
   
   # Test SERIES
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SERIES" | jq '.count'
   
   # Test SPINOFF
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SPINOFF" | jq '.count'
   
   # Test SAME_UNIVERSE
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SAME_UNIVERSE" | jq '.count'
   ```

---

### Scenario 3: Filter by Multiple Relationship Types

**Objective:** Verify that filtering by multiple types works correctly.

**Given:** A movie exists with related movies of different types

**When:** A GET request is sent with `type[]=SEQUEL&type[]=PREQUEL` query parameters

**Then:** Only movies with SEQUEL or PREQUEL relationship types should be returned

**Detailed Steps:**

1. **Send GET request with multiple type filters:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL&type[]=PREQUEL" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**
   - [ ] Status code: `200 OK`
   - [ ] Both SEQUEL and PREQUEL relationships are returned
   - [ ] REMAKE, SERIES, SPINOFF, SAME_UNIVERSE are excluded
   - [ ] `count` matches sum of SEQUEL + PREQUEL relationships
   - [ ] Each item has `relationship_type` equal to either "SEQUEL" or "PREQUEL"

3. **Test with all types:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL&type[]=PREQUEL&type[]=REMAKE&type[]=SERIES&type[]=SPINOFF&type[]=SAME_UNIVERSE" \
     -H "Accept: application/json" | jq '.count'
   # Should return same count as without filter
   ```

---

### Scenario 4: Invalid Relationship Type Filter

**Objective:** Verify that invalid relationship types are handled gracefully.

**Given:** A movie exists with relationships

**When:** A GET request is sent with `type[]=INVALID` query parameter

**Then:** Should return empty array or handle gracefully

**Detailed Steps:**

1. **Test invalid type:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=INVALID" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**
   - [ ] Status code: `200 OK` (or `422 Unprocessable Entity` if validation is strict)
   - [ ] Empty array returned OR error message explaining invalid type
   - [ ] `count` is `0`

3. **Test case sensitivity:**
   ```bash
   # Lowercase (should work - API converts to uppercase)
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=sequel" | jq
   
   # Mixed case
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=Sequel" | jq
   ```

---

### Scenario 5: Movie Not Found (404)

**Objective:** Verify that non-existent movies return proper 404 error.

**Given:** A movie does not exist

**When:** A GET request is sent to `/api/v1/movies/{slug}/related`

**Then:** Response status should be `404 Not Found`

**Detailed Steps:**

1. **Send GET request for non-existent movie:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/non-existent-movie-9999/related" \
     -H "Accept: application/json" \
     -v
   ```

2. **Verify response:**
   ```json
   {
     "error": "Movie not found",
     "message": "The requested movie could not be found."
   }
   ```
   - [ ] Status code: `404 Not Found`
   - [ ] Error message is present and clear
   - [ ] Response format is consistent with other error responses

3. **Test with various invalid slugs:**
   ```bash
   # Non-existent slug
   curl -X GET "http://localhost:8000/api/v1/movies/this-movie-does-not-exist-2020/related"
   
   # Malformed slug
   curl -X GET "http://localhost:8000/api/v1/movies/invalid@slug#123/related"
   
   # Empty slug (should be handled by route)
   curl -X GET "http://localhost:8000/api/v1/movies//related"
   ```

---

### Scenario 6: Empty Relationships

**Objective:** Verify that movies without relationships return empty array correctly.

**Given:** A movie exists but has no related movies

**When:** A GET request is sent to `/api/v1/movies/{slug}/related`

**Then:** Response should return empty array with `count: 0`

**Detailed Steps:**

1. **Find or create a standalone movie** (no relationships):
   ```sql
   INSERT INTO movies (title, slug, release_year, director, created_at, updated_at) VALUES
   ('Standalone Movie', 'standalone-movie-2020', 2020, 'Test Director', NOW(), NOW());
   ```

2. **Send GET request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/standalone-movie-2020/related" \
     -H "Accept: application/json" | jq
   ```

3. **Verify response:**
   ```json
   {
     "movie": {
       "id": 10,
       "slug": "standalone-movie-2020",
       "title": "Standalone Movie"
     },
     "related_movies": [],
     "count": 0,
     "_links": {
       "self": {
         "href": "http://localhost:8000/api/v1/movies/standalone-movie-2020/related"
       },
       "movie": {
         "href": "http://localhost:8000/api/v1/movies/standalone-movie-2020"
       }
     }
   }
   ```
   - [ ] Status code: `200 OK`
   - [ ] `related_movies` is empty array `[]` (not null)
   - [ ] `count` is `0`
   - [ ] `movie` object is present with correct info
   - [ ] `_links` are present and correct

---

### Scenario 7: All Relationship Types Verification

**Objective:** Verify that all relationship types are supported and work correctly.

**Relationship Types:**
- `SEQUEL` - Sequel (next movie in series)
- `PREQUEL` - Prequel (previous movie in series)
- `REMAKE` - Remake
- `SERIES` - Part of series
- `SPINOFF` - Spinoff
- `SAME_UNIVERSE` - Same universe

**Detailed Steps:**

1. **Create test data with all relationship types:**
   ```sql
   -- Create movies
   INSERT INTO movies (title, slug, release_year, director, created_at, updated_at) VALUES
   ('Original Movie', 'original-movie-2000', 2000, 'Director A', NOW(), NOW()),
   ('Sequel Movie', 'sequel-movie-2002', 2002, 'Director A', NOW(), NOW()),
   ('Prequel Movie', 'prequel-movie-1998', 1998, 'Director A', NOW(), NOW()),
   ('Remake Movie', 'remake-movie-2010', 2010, 'Director B', NOW(), NOW()),
   ('Series Movie', 'series-movie-2001', 2001, 'Director A', NOW(), NOW()),
   ('Spinoff Movie', 'spinoff-movie-2005', 2005, 'Director C', NOW(), NOW()),
   ('Universe Movie', 'universe-movie-2003', 2003, 'Director D', NOW(), NOW());

   -- Create relationships (assuming IDs: 10=Original, 11=Sequel, 12=Prequel, 13=Remake, 14=Series, 15=Spinoff, 16=Universe)
   INSERT INTO movie_relationships (movie_id, related_movie_id, relationship_type, created_at, updated_at) VALUES
   (10, 11, 'SEQUEL', NOW(), NOW()),
   (10, 12, 'PREQUEL', NOW(), NOW()),
   (10, 13, 'REMAKE', NOW(), NOW()),
   (10, 14, 'SERIES', NOW(), NOW()),
   (10, 15, 'SPINOFF', NOW(), NOW()),
   (10, 16, 'SAME_UNIVERSE', NOW(), NOW());
   ```

2. **Test each relationship type:**
   ```bash
   # Get all relationships
   curl -X GET "http://localhost:8000/api/v1/movies/original-movie-2000/related" | jq '.related_movies[] | {title: .title, type: .relationship_type, label: .relationship_label}'
   
   # Expected output:
   # {
   #   "title": "Sequel Movie",
  #   "type": "SEQUEL",
   #   "label": "Sequel"
   # }
   # {
   #   "title": "Prequel Movie",
   #   "type": "PREQUEL",
   #   "label": "Prequel"
   # }
   # ... etc
   ```

3. **Verify:**
   - [ ] All 6 relationship types are returned
   - [ ] `relationship_label` is human-readable for each type:
     - [ ] SEQUEL → "Sequel"
     - [ ] PREQUEL → "Prequel"
     - [ ] REMAKE → "Remake"
     - [ ] SERIES → "Series"
     - [ ] SPINOFF → "Spinoff"
     - [ ] SAME_UNIVERSE → "Same Universe"
   - [ ] `relationship_order` is present (if applicable)

---

### Scenario 8: Automatic Synchronization from TMDB (Collection)

**Objective:** Verify that relationships are automatically synchronized from TMDB collections.

**Given:** A movie is created from TMDB data that belongs to a collection (e.g., Matrix trilogy)

**When:** Movie is created via API

**Then:** Relationships should be synchronized automatically via queue job

**Detailed Steps:**

1. **Create a movie from TMDB** (via search endpoint):
   ```bash
   # Search for Matrix (this will create movie if not exists)
   curl -X GET "http://localhost:8000/api/v1/movies/search?query=matrix&year=1999" \
     -H "Accept: application/json" | jq
   ```

2. **Wait for queue processing:**
   ```bash
   # Check queue logs
   tail -f api/storage/logs/laravel.log | grep -E "SyncMovieRelationshipsJob|SyncMovieMetadataJob"
   
   # Or check Horizon dashboard
   # Open: http://localhost:8000/horizon
   # Look for: SyncMovieRelationshipsJob
   ```

3. **Verify job was dispatched:**
   ```bash
   # Check logs for job dispatch
   grep "SyncMovieRelationshipsJob" api/storage/logs/laravel.log | tail -5
   
   # Should see:
   # SyncMovieRelationshipsJob started
   # SyncMovieRelationshipsJob finished
   ```

4. **Wait for job completion** (usually 5-30 seconds depending on TMDB API response time)

5. **Verify relationships were created:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" \
     -H "Accept: application/json" | jq
   ```

6. **Expected results:**
   - [ ] Related movies from TMDB collection are present
   - [ ] Relationship types are correctly assigned:
     - [ ] Movies before current in collection → PREQUEL
     - [ ] Movies after current in collection → SEQUEL
     - [ ] Movies in same collection → SERIES
   - [ ] `relationship_order` reflects position in collection
   - [ ] Related movies are created if they don't exist locally

7. **Verify in database:**
   ```sql
   -- Get movie ID
   SELECT id FROM movies WHERE slug = 'the-matrix-1999';
   -- Assume ID = 1
   
   -- Check relationships
   SELECT 
     mr.relationship_type,
     mr.`order`,
     m2.title as related_movie_title
   FROM movie_relationships mr
   JOIN movies m2 ON mr.related_movie_id = m2.id
   WHERE mr.movie_id = 1;
   ```

---

### Scenario 9: Automatic Synchronization from TMDB (Similar Movies)

**Objective:** Verify that similar movies are synchronized as SAME_UNIVERSE relationships.

**Given:** A movie is created from TMDB data with similar movies

**When:** Movie is created via API

**Then:** Similar movies should be synchronized as SAME_UNIVERSE relationships

**Detailed Steps:**

1. **Create a movie from TMDB** (movies with similar movies):
   ```bash
   # Search for a popular movie (e.g., Inception)
   curl -X GET "http://localhost:8000/api/v1/movies/search?query=inception&year=2010" \
     -H "Accept: application/json" | jq
   ```

2. **Wait for queue processing** (check logs as in Scenario 8)

3. **Verify similar movies were synced:**
   ```bash
   # Get movie slug from search response, then:
   curl -X GET "http://localhost:8000/api/v1/movies/inception-2010/related?type[]=SAME_UNIVERSE" \
     -H "Accept: application/json" | jq
   ```

4. **Expected results:**
   - [ ] Similar movies from TMDB are present
   - [ ] Relationship type is `SAME_UNIVERSE`
   - [ ] `relationship_label` is "Same Universe"
   - [ ] Limited to top 10 similar movies (check implementation)

---

### Scenario 10: Bidirectional Relationships

**Objective:** Verify that relationships work in both directions.

**Given:** Movie A has SEQUEL relationship to Movie B

**When:** Querying Movie B for related movies

**Then:** Movie A should appear as PREQUEL relationship

**Detailed Steps:**

1. **Create bidirectional relationship:**
   ```sql
   -- Movie 1 -> Movie 2 (SEQUEL)
   INSERT INTO movie_relationships (movie_id, related_movie_id, relationship_type, `order`, created_at, updated_at) VALUES
   (1, 2, 'SEQUEL', 1, NOW(), NOW());
   ```

2. **Test from Movie 1 perspective:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL" | jq
   # Should show Movie 2 as SEQUEL
   ```

3. **Test from Movie 2 perspective:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-reloaded-2003/related?type[]=PREQUEL" | jq
   # Should show Movie 1 as PREQUEL (if reverse relationship exists)
   ```

4. **Note:** Current implementation may require manual creation of reverse relationships. Check implementation details.

---

### Scenario 11: Relationship Order

**Objective:** Verify that `relationship_order` is correctly set and used.

**Given:** A movie has multiple sequels with order values

**When:** GET request returns related movies

**Then:** Movies should be ordered by `relationship_order` (if applicable)

**Detailed Steps:**

1. **Create movies with order:**
   ```sql
   INSERT INTO movie_relationships (movie_id, related_movie_id, relationship_type, `order`, created_at, updated_at) VALUES
   (1, 2, 'SEQUEL', 1, NOW(), NOW()),
   (1, 3, 'SEQUEL', 2, NOW(), NOW()),
   (1, 4, 'SEQUEL', 3, NOW(), NOW());
   ```

2. **Test ordering:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL" | jq '.related_movies[] | {title: .title, order: .relationship_order}'
   ```

3. **Verify:**
   - [ ] `relationship_order` values are present
   - [ ] Order values are sequential (1, 2, 3, etc.)
   - [ ] Movies are returned in correct order (check API implementation)

---

### Scenario 12: Large Number of Relationships (Performance)

**Objective:** Verify that endpoint handles large number of relationships efficiently.

**Given:** A movie has 50+ related movies

**When:** GET request is sent

**Then:** Response should be returned within reasonable time (< 1 second)

**Detailed Steps:**

1. **Create test data with many relationships** (use script or SQL):
   ```sql
   -- Create 50 movies
   -- Create 50 relationships
   -- (Use a script for this)
   ```

2. **Test performance:**
   ```bash
   time curl -X GET "http://localhost:8000/api/v1/movies/test-movie-with-many-relations/related" \
     -H "Accept: application/json" > /dev/null
   ```

3. **Verify:**
   - [ ] Response time < 1 second
   - [ ] All relationships are returned
   - [ ] No timeout errors
   - [ ] Memory usage is reasonable

---

## 🗄️ Database Verification

### Check Relationships Table Structure

```sql
-- PostgreSQL
\d movie_relationships

-- Expected structure:
-- Column           | Type                        | Nullable
-- -----------------+-----------------------------+----------
-- id               | bigint                      | not null
-- movie_id         | bigint                      | not null
-- related_movie_id | bigint                      | not null
-- relationship_type| varchar(20)                 | not null
-- order            | smallint                    | nullable
-- created_at       | timestamp without time zone | nullable
-- updated_at       | timestamp without time zone | nullable
```

### Verify Data Integrity

```sql
-- Check all relationships
SELECT 
    m1.title as movie_title,
    m1.slug as movie_slug,
    mr.relationship_type,
    mr.`order`,
    m2.title as related_movie_title,
    m2.slug as related_movie_slug
FROM movie_relationships mr
JOIN movies m1 ON mr.movie_id = m1.id
JOIN movies m2 ON mr.related_movie_id = m2.id
ORDER BY m1.id, mr.relationship_type, mr.`order`;

-- Count relationships per movie
SELECT 
    m.title,
    m.slug,
    COUNT(mr.id) as relationship_count
FROM movies m
LEFT JOIN movie_relationships mr ON m.id = mr.movie_id
GROUP BY m.id, m.title, m.slug
ORDER BY relationship_count DESC;

-- Check for orphaned relationships (related movie doesn't exist)
SELECT mr.*
FROM movie_relationships mr
LEFT JOIN movies m ON mr.related_movie_id = m.id
WHERE m.id IS NULL;
-- Should return 0 rows

-- Check for duplicate relationships
SELECT movie_id, related_movie_id, relationship_type, COUNT(*) as count
FROM movie_relationships
GROUP BY movie_id, related_movie_id, relationship_type
HAVING COUNT(*) > 1;
-- Should return 0 rows (unique constraint should prevent this)
```

### Verify TMDB Snapshot Exists

```sql
-- Check if movie has TMDB snapshot (required for sync)
SELECT 
    m.id,
    m.title,
    m.slug,
    ts.tmdb_id,
    ts.tmdb_type,
    ts.fetched_at
FROM movies m
LEFT JOIN tmdb_snapshots ts ON m.id = ts.entity_id AND ts.entity_type = 'MOVIE'
WHERE m.slug = 'the-matrix-1999';
```

---

## 📊 Queue & Logs Verification

### Check Queue Status

**Using Horizon Dashboard:**
1. Open `http://localhost:8000/horizon`
2. Check "Recent Jobs" for `SyncMovieRelationshipsJob`
3. Verify job status: `completed`, `failed`, or `processing`

**Using Command Line:**
```bash
# Check queue status
php artisan queue:work --once --verbose

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### Check Logs

```bash
# Watch logs in real-time
tail -f api/storage/logs/laravel.log

# Filter for relationship sync jobs
tail -f api/storage/logs/laravel.log | grep -E "SyncMovieRelationshipsJob|movie_relationships"

# Check for errors
grep -i "error\|exception\|failed" api/storage/logs/laravel.log | grep -i "relationship\|sync" | tail -20
```

### Expected Log Messages

**Successful sync:**
```
[2025-12-18 13:24:00] local.INFO: SyncMovieRelationshipsJob started {"movie_id":1,"attempt":1}
[2025-12-18 13:24:05] local.INFO: SyncMovieRelationshipsJob finished {"movie_id":1}
```

**Warning (no snapshot):**
```
[2025-12-18 13:24:00] local.WARNING: SyncMovieRelationshipsJob: No TMDb snapshot found for movie {"movie_id":1}
```

**Warning (no TMDB details):**
```
[2025-12-18 13:24:00] local.WARNING: SyncMovieRelationshipsJob: No TMDb details found for movie {"movie_id":1,"tmdb_id":603}
```

---

## ⚡ Performance Testing

### Test Response Time

```bash
# Single request timing
time curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" \
  -H "Accept: application/json" -o /dev/null -s -w "%{time_total}\n"

# Multiple requests (10)
for i in {1..10}; do
  time curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" \
    -H "Accept: application/json" -o /dev/null -s -w "%{time_total}\n"
done | awk '{sum+=$1; count++} END {print "Average:", sum/count, "seconds"}'
```

**Expected Results:**
- Single request: < 200ms
- Average (10 requests): < 300ms
- 95th percentile: < 500ms

### Test Concurrent Requests

```bash
# Use Apache Bench or similar
ab -n 100 -c 10 "http://localhost:8000/api/v1/movies/the-matrix-1999/related"

# Or use curl in parallel
for i in {1..20}; do
  curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" &
done
wait
```

---

## 🔒 Security Verification

### Verify tmdb_id is Hidden

**Test 1: Check movie response**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" | jq 'has("tmdb_id")'
# Should return: false
```

**Test 2: Check related movies**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" | jq '.related_movies[0] | has("tmdb_id")'
# Should return: false
```

**Test 3: Deep check (all nested objects)**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" | jq '.. | objects | has("tmdb_id")' | grep -v false
# Should return nothing (no true values)
```

### Verify No Sensitive Data

```bash
# Check for API keys, passwords, etc.
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" | jq '.' | grep -iE "api_key|password|secret|token"
# Should return nothing
```

---

## 🐛 Troubleshooting

### Problem: Empty related_movies array

**Symptoms:**
- API returns `related_movies: []` and `count: 0`
- But relationships exist in database

**Diagnosis Steps:**

1. **Check database:**
   ```sql
   SELECT * FROM movie_relationships WHERE movie_id = <movie_id>;
   ```

2. **Check movie exists:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999"
   ```

3. **Check logs:**
   ```bash
   grep "MovieController.*related" api/storage/logs/laravel.log | tail -10
   ```

**Possible Causes & Solutions:**

| Cause | Solution |
|-------|----------|
| Movie has no relationships | Create relationships via SQL or wait for TMDB sync |
| Queue job hasn't processed | Check queue worker, wait 30 seconds, retry |
| TMDB data doesn't contain collection | Use movie with known collection (e.g., Matrix, Star Wars) |
| Database connection issue | Check database connection, verify migrations |

---

### Problem: Relationships not syncing from TMDB

**Symptoms:**
- Movie created but no relationships appear
- Queue job shows warnings in logs

**Diagnosis Steps:**

1. **Check queue worker:**
   ```bash
   # Is queue worker running?
   ps aux | grep "queue:work\|horizon"
   ```

2. **Check TMDB API key:**
   ```bash
   grep TMDB_API_KEY api/.env
   # Should show: TMDB_API_KEY=your_key_here
   ```

3. **Check TMDB snapshot:**
   ```sql
   SELECT * FROM tmdb_snapshots WHERE entity_id = <movie_id> AND entity_type = 'MOVIE';
   ```

4. **Check logs:**
   ```bash
   grep "SyncMovieRelationshipsJob" api/storage/logs/laravel.log | tail -20
   ```

**Possible Causes & Solutions:**

| Cause | Solution |
|-------|----------|
| Queue worker not running | Start: `php artisan queue:work` or `php artisan horizon` |
| TMDB API key missing/invalid | Check `.env`, verify key at https://www.themoviedb.org/settings/api |
| Movie doesn't have TMDB snapshot | Movie must be created from TMDB search, not manually |
| TMDB API rate limit | Wait 10 seconds, retry |
| Movie not in collection | Use movie with known collection |

---

### Problem: Invalid relationship type filter

**Symptoms:**
- Filter doesn't work
- Returns all relationships instead of filtered

**Diagnosis Steps:**

1. **Check filter syntax:**
   ```bash
   # Correct format
   curl "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL"
   
   # Wrong format (will not work)
   curl "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type=SEQUEL"
   ```

2. **Check case sensitivity:**
   ```bash
   # Uppercase works
   curl "...?type[]=SEQUEL"
   
   # Lowercase should also work (API converts to uppercase)
   curl "...?type[]=sequel"
   ```

**Possible Causes & Solutions:**

| Cause | Solution |
|-------|----------|
| Wrong query parameter format | Use `type[]=SEQUEL` not `type=SEQUEL` |
| Invalid type name | Use: SEQUEL, PREQUEL, REMAKE, SERIES, SPINOFF, SAME_UNIVERSE |
| Case sensitivity | API converts to uppercase, but use uppercase for clarity |

---

### Problem: 404 for existing movie

**Symptoms:**
- Movie exists in database but API returns 404

**Diagnosis Steps:**

1. **Check movie slug:**
   ```sql
   SELECT id, title, slug FROM movies WHERE slug LIKE '%matrix%';
   ```

2. **Check API directly:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999"
   ```

3. **Check route:**
   ```bash
   php artisan route:list | grep related
   # Should show: GET|HEAD api/v1/movies/{slug}/related
   ```

**Possible Causes & Solutions:**

| Cause | Solution |
|-------|----------|
| Wrong slug format | Use exact slug from database |
| Route not registered | Check `api/routes/api.php` |
| Cache issue | Clear cache: `php artisan cache:clear` |

---

### Problem: Slow response time

**Symptoms:**
- API response takes > 1 second
- Timeout errors

**Diagnosis Steps:**

1. **Check database indexes:**
   ```sql
   -- Check indexes on movie_relationships
   \d movie_relationships
   -- Should have indexes on: movie_id, related_movie_id, relationship_type
   ```

2. **Check query performance:**
   ```bash
   # Enable query logging
   # Check Laravel logs for slow queries
   ```

3. **Check number of relationships:**
   ```sql
   SELECT COUNT(*) FROM movie_relationships WHERE movie_id = <movie_id>;
   ```

**Possible Causes & Solutions:**

| Cause | Solution |
|-------|----------|
| Missing database indexes | Run migrations: `php artisan migrate` |
| Too many relationships | Consider pagination (future enhancement) |
| N+1 query problem | Check eager loading in controller |
| Database connection slow | Check database performance |

---

## 📝 Test Report Template

```markdown
# Test Report - Stage 4: Movie Relationships

**Date:** YYYY-MM-DD  
**Tester:** [Name]  
**Environment:** [dev/staging/production]  
**API Version:** v1  
**Build:** [commit hash or version]

## Test Summary

| Metric | Value |
|--------|-------|
| Total Scenarios | 12 |
| Passed | X |
| Failed | Y |
| Blocked | Z |
| Execution Time | HH:MM |

## Test Results

### Scenario 1: Get Related Movies (Basic)
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Execution Time:** X seconds
- **Notes:** [Any observations]

### Scenario 2: Filter by Relationship Type (Single)
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Execution Time:** X seconds
- **Notes:** [Any observations]

### Scenario 3: Filter by Multiple Types
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Execution Time:** X seconds
- **Notes:** [Any observations]

### Scenario 4: Invalid Relationship Type Filter
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Execution Time:** X seconds
- **Notes:** [Any observations]

### Scenario 5: Movie Not Found (404)
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Execution Time:** X seconds
- **Notes:** [Any observations]

### Scenario 6: Empty Relationships
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Execution Time:** X seconds
- **Notes:** [Any observations]

### Scenario 7: All Relationship Types
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Execution Time:** X seconds
- **Notes:** [Any observations]

### Scenario 8: TMDB Collection Sync
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Execution Time:** X seconds
- **Notes:** [Any observations]

### Scenario 9: TMDB Similar Movies Sync
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Execution Time:** X seconds
- **Notes:** [Any observations]

### Scenario 10: Bidirectional Relationships
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Execution Time:** X seconds
- **Notes:** [Any observations]

### Scenario 11: Relationship Order
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Execution Time:** X seconds
- **Notes:** [Any observations]

### Scenario 12: Performance (Large Dataset)
- **Status:** ✅ PASS / ❌ FAIL / ⏸️ BLOCKED
- **Response Time:** X ms (average)
- **Notes:** [Any observations]

## Security Verification

- [ ] `tmdb_id` is NOT present in API responses
- [ ] No sensitive data exposed
- [ ] SQL injection protection verified
- [ ] Input validation works correctly

## Performance Metrics

| Metric | Value | Target | Status |
|--------|-------|--------|--------|
| Average Response Time | X ms | < 300ms | ✅/❌ |
| 95th Percentile | X ms | < 500ms | ✅/❌ |
| Max Response Time | X ms | < 1000ms | ✅/❌ |
| Throughput (req/s) | X | > 10 | ✅/❌ |

## Issues Found

### Critical Issues
1. **[Issue Title]**
   - **Description:** [Detailed description]
   - **Steps to Reproduce:** [Steps]
   - **Expected:** [Expected behavior]
   - **Actual:** [Actual behavior]
   - **Screenshots/Logs:** [Attach if available]

### High Priority Issues
1. **[Issue Title]**
   - [Description]

### Medium Priority Issues
1. **[Issue Title]**
   - [Description]

### Low Priority Issues
1. **[Issue Title]**
   - [Description]

## Recommendations

- [Recommendation 1]
- [Recommendation 2]

## Sign-off

**Tester:** [Name]  
**Date:** YYYY-MM-DD  
**Status:** ✅ APPROVED / ❌ REJECTED / ⏸️ PENDING

**Developer Review:** [Name]  
**Date:** YYYY-MM-DD  
**Status:** ✅ APPROVED / ❌ REJECTED / ⏸️ PENDING
```

---

## 🛠️ Quick Reference Commands

### Basic Tests
```bash
# Get related movies
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" | jq

# Filter by type
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL" | jq

# Multiple types
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL&type[]=PREQUEL" | jq

# Non-existent movie
curl -X GET "http://localhost:8000/api/v1/movies/non-existent-9999/related" | jq
```

### Database Checks
```sql
-- Count relationships
SELECT COUNT(*) FROM movie_relationships;

-- Check specific movie
SELECT * FROM movie_relationships WHERE movie_id = 1;

-- Check TMDB snapshot
SELECT * FROM tmdb_snapshots WHERE entity_id = 1 AND entity_type = 'MOVIE';
```

### Log Checks
```bash
# Watch logs
tail -f api/storage/logs/laravel.log | grep -i relationship

# Check for errors
grep -i error api/storage/logs/laravel.log | grep -i relationship | tail -10
```

---

## 📚 Additional Resources

- **API Documentation:** `docs/openapi.yaml`
- **Postman Collection:** `docs/postman/moviemind-api.postman_collection.json`
- **Feature Tests:** `api/tests/Feature/MovieRelationshipsTest.php`
- **Unit Tests:** `api/tests/Unit/Jobs/SyncMovieRelationshipsJobTest.php`
- **Implementation Plan:** `docs/issue/NEW_SEARCH_USE_CASE_IMPLEMENTATION_PLAN.md`

---

**Last updated:** 2025-12-18  
**Version:** 1.0

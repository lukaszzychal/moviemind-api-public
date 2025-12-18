# Manual Testing Guide - Stage 4: Movie Relationships

> **Created:** 2025-12-18  
> **Context:** Manual testing instructions for Stage 4 - Movie Relationships feature  
> **Category:** reference

## 🎯 Purpose

This document provides step-by-step manual testing instructions for QA to verify the Movie Relationships feature (Stage 4) implementation.

## 📋 Prerequisites

- API server running (`php artisan serve` or Docker)
- Database migrated (`php artisan migrate`)
- Queue worker running (`php artisan queue:work` or `php artisan horizon`)
- Access to TMDB API (for automatic synchronization tests)
- API testing tool (curl, Postman, Insomnia, or browser)

## 🔍 Test Scenarios

### Scenario 1: Get Related Movies (Basic)

**Given:** A movie exists with related movies (sequel, prequel)

**When:** A GET request is sent to `/api/v1/movies/{slug}/related`

**Then:**
- Response status should be `200 OK`
- Response should contain movie info and related movies array
- Each related movie should have `relationship_type`, `relationship_label`, and `relationship_order`

**Steps:**

1. **Prepare test data** (via database or API):
   ```bash
   # Option 1: Use existing movie with relationships
   # Option 2: Create test data via API (if endpoints exist)
   ```

2. **Send GET request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" \
     -H "Accept: application/json"
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

4. **Verify:**
   - ✅ Status code: `200`
   - ✅ `movie` object contains correct movie info
   - ✅ `related_movies` is an array
   - ✅ Each related movie has `relationship_type` (SEQUEL, PREQUEL, etc.)
   - ✅ Each related movie has `relationship_label` (human-readable)
   - ✅ `count` matches number of related movies
   - ✅ `_links` are present and correct
   - ✅ `tmdb_id` is **NOT** present in response (security check)

---

### Scenario 2: Filter by Relationship Type

**Given:** A movie exists with related movies of different types (sequel, remake)

**When:** A GET request is sent with `type[]` query parameter

**Then:** Only movies with specified relationship types should be returned

**Steps:**

1. **Send GET request with filter:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL" \
     -H "Accept: application/json"
   ```

2. **Verify response:**
   - ✅ Status code: `200`
   - ✅ Only SEQUEL relationships are returned
   - ✅ Other types (REMAKE, PREQUEL, etc.) are excluded
   - ✅ `count` matches filtered results

3. **Test multiple types:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL&type[]=PREQUEL" \
     -H "Accept: application/json"
   ```
   - ✅ Both SEQUEL and PREQUEL relationships are returned

4. **Test invalid type:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=INVALID" \
     -H "Accept: application/json"
   ```
   - ✅ Should return empty array or handle gracefully (check implementation)

---

### Scenario 3: Movie Not Found (404)

**Given:** A movie does not exist

**When:** A GET request is sent to `/api/v1/movies/{slug}/related`

**Then:** Response status should be `404 Not Found`

**Steps:**

1. **Send GET request for non-existent movie:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/non-existent-movie-9999/related" \
     -H "Accept: application/json"
   ```

2. **Verify response:**
   - ✅ Status code: `404`
   - ✅ Error message is present

---

### Scenario 4: Empty Relationships

**Given:** A movie exists but has no related movies

**When:** A GET request is sent to `/api/v1/movies/{slug}/related`

**Then:** Response should return empty array with `count: 0`

**Steps:**

1. **Find or create a standalone movie** (no relationships)

2. **Send GET request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/standalone-movie-2020/related" \
     -H "Accept: application/json"
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
   - ✅ Status code: `200`
   - ✅ `related_movies` is empty array `[]`
   - ✅ `count` is `0`

---

### Scenario 5: All Relationship Types

**Given:** A movie with various relationship types

**When:** GET request without filter

**Then:** All relationship types should be returned

**Relationship Types:**
- `SEQUEL` - Sequel (next movie in series)
- `PREQUEL` - Prequel (previous movie in series)
- `REMAKE` - Remake
- `SERIES` - Part of series
- `SPINOFF` - Spinoff
- `SAME_UNIVERSE` - Same universe

**Steps:**

1. **Create test data with multiple relationship types** (via database or API)

2. **Send GET request without filter:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/test-movie-2020/related" \
     -H "Accept: application/json"
   ```

3. **Verify:**
   - ✅ All relationship types are returned
   - ✅ `relationship_label` is human-readable (e.g., "Sequel", "Prequel")
   - ✅ `relationship_order` is present (if applicable)

---

### Scenario 6: Automatic Synchronization from TMDB (Advanced)

**Given:** A movie is created from TMDB data with collection/similar movies

**When:** Movie is created via API

**Then:** Relationships should be synchronized automatically via queue job

**Steps:**

1. **Create a movie from TMDB** (if endpoint exists):
   ```bash
   curl -X POST "http://localhost:8000/api/v1/movies/search?query=matrix" \
     -H "Accept: application/json"
   ```

2. **Wait for queue processing** (check queue worker logs):
   ```bash
   # Check Horizon dashboard or queue logs
   tail -f storage/logs/laravel.log | grep SyncMovieRelationshipsJob
   ```

3. **Verify relationships were created:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" \
     -H "Accept: application/json"
   ```
   - ✅ Related movies from TMDB collection are present
   - ✅ Relationship types are correctly assigned (SEQUEL, PREQUEL, SERIES)
   - ✅ Similar movies (SAME_UNIVERSE) are present

4. **Check database directly** (optional):
   ```sql
   SELECT * FROM movie_relationships WHERE movie_id = <movie_id>;
   ```

---

## 🔍 Verification Checklist

### Response Structure
- [ ] Response has `movie` object with `id`, `slug`, `title`
- [ ] Response has `related_movies` array
- [ ] Response has `count` field (integer)
- [ ] Response has `_links` object with `self` and `movie` links
- [ ] Each related movie has:
  - [ ] `id`, `slug`, `title`
  - [ ] `relationship_type` (SEQUEL, PREQUEL, etc.)
  - [ ] `relationship_label` (human-readable)
  - [ ] `relationship_order` (nullable integer)
  - [ ] `_links` object

### Security
- [ ] `tmdb_id` is **NOT** present in response (check all nested objects)
- [ ] No sensitive data exposed

### Filtering
- [ ] Single type filter works (`?type[]=SEQUEL`)
- [ ] Multiple type filters work (`?type[]=SEQUEL&type[]=PREQUEL`)
- [ ] Invalid type is handled gracefully
- [ ] Empty filter returns all types

### Edge Cases
- [ ] 404 for non-existent movie
- [ ] Empty array for movie with no relationships
- [ ] Large number of relationships (performance test)

### HATEOAS Links
- [ ] `_links.self` points to `/api/v1/movies/{slug}/related`
- [ ] `_links.movie` points to `/api/v1/movies/{slug}`
- [ ] Each related movie has `_links.self` pointing to its own endpoint

---

## 🛠️ Quick Test Commands

### Test 1: Basic Related Movies
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" \
  -H "Accept: application/json" | jq
```

### Test 2: Filter by Type
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL" \
  -H "Accept: application/json" | jq
```

### Test 3: Multiple Types
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related?type[]=SEQUEL&type[]=PREQUEL" \
  -H "Accept: application/json" | jq
```

### Test 4: Non-existent Movie
```bash
curl -X GET "http://localhost:8000/api/v1/movies/non-existent-9999/related" \
  -H "Accept: application/json" | jq
```

### Test 5: Empty Relationships
```bash
curl -X GET "http://localhost:8000/api/v1/movies/standalone-movie-2020/related" \
  -H "Accept: application/json" | jq
```

---

## 📊 Expected Response Examples

### Success Response (with relationships)
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
      "genres": ["Action", "Sci-Fi"],
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

### Success Response (no relationships)
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

### Error Response (404)
```json
{
  "error": "Movie not found",
  "message": "The requested movie could not be found."
}
```

---

## 🐛 Troubleshooting

### Problem: Empty related_movies array

**Possible causes:**
1. Movie has no relationships in database
2. Queue job hasn't processed yet (check queue worker)
3. TMDB data doesn't contain collection/similar movies

**Solution:**
- Check database: `SELECT * FROM movie_relationships WHERE movie_id = ?;`
- Check queue logs for `SyncMovieRelationshipsJob`
- Verify TMDB snapshot exists: `SELECT * FROM tmdb_snapshots WHERE entity_id = ?;`

### Problem: Relationships not syncing from TMDB

**Possible causes:**
1. Queue worker not running
2. TMDB API key not configured
3. Movie doesn't have TMDB snapshot

**Solution:**
- Start queue worker: `php artisan queue:work` or `php artisan horizon`
- Check `.env` for `TMDB_API_KEY`
- Verify movie has `tmdb_id` and snapshot

### Problem: Invalid relationship type filter

**Possible causes:**
1. Typo in type name (case-sensitive)
2. Invalid type value

**Solution:**
- Use uppercase: `SEQUEL`, `PREQUEL`, etc.
- Valid types: `SEQUEL`, `PREQUEL`, `REMAKE`, `SERIES`, `SPINOFF`, `SAME_UNIVERSE`

---

## 📝 Test Report Template

```
## Test Report - Stage 4: Movie Relationships

**Date:** YYYY-MM-DD
**Tester:** [Name]
**Environment:** [dev/staging/production]

### Test Results

| Scenario | Status | Notes |
|----------|--------|-------|
| Basic related movies | ✅/❌ | |
| Filter by type | ✅/❌ | |
| Multiple type filters | ✅/❌ | |
| 404 handling | ✅/❌ | |
| Empty relationships | ✅/❌ | |
| TMDB synchronization | ✅/❌ | |

### Issues Found

1. [Issue description]
2. [Issue description]

### Recommendations

- [Recommendation]
```

---

**Last updated:** 2025-12-18


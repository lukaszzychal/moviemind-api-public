# Manual Testing Guide - Movies API

> **Created:** 2025-12-18  
> **Context:** Detailed manual testing instructions for Movies API endpoints  
> **Category:** reference  
> **Target Audience:** QA Engineers, Testers

## 📋 Table of Contents

1. [Overview](#overview)
2. [Endpoints](#endpoints)
3. [Test Scenarios](#test-scenarios)
4. [Database Verification](#database-verification)
5. [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

This document provides detailed manual testing instructions for all Movies API endpoints. For general setup and prerequisites, see the [Main Testing Guide](../MANUAL_TESTING_GUIDE.md).

**Related Documents:**
- [Main Testing Guide](../MANUAL_TESTING_GUIDE.md) - General setup and overview
- [Relationships Testing Guide](../MANUAL_TESTING_RELATIONSHIPS.md) - Movie relationships
- [People Testing Guide](../MANUAL_TESTING_PEOPLE.md) - People API

---

## 📡 Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/movies` | List all movies (with pagination) |
| `GET` | `/api/v1/movies/search` | Advanced movie search |
| `GET` | `/api/v1/movies/{slug}` | Get movie details |
| `GET` | `/api/v1/movies/{slug}/related` | Get related movies |
| `POST` | `/api/v1/movies/{slug}/refresh` | Refresh movie from TMDB |

---

## 🔍 Test Scenarios

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

### Scenario 6: Movie Not Found (404)

**Objective:** Verify proper 404 handling.

**Steps:**

1. **Request non-existent movie:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/movies/non-existent-movie-9999" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**
   ```json
   {
     "error": "Movie not found",
     "message": "The requested movie could not be found."
   }
   ```
   - [ ] Status code: `404 Not Found`
   - [ ] Error message is clear

---

## 🗄️ Database Verification

### Check Movie Data

```sql
-- List all movies
SELECT id, title, slug, release_year, director, tmdb_id FROM movies ORDER BY id;

-- Check movie descriptions
SELECT md.*, m.title as movie_title
FROM movie_descriptions md
JOIN movies m ON md.movie_id = m.id
WHERE m.slug = 'the-matrix-1999';

-- Check movie people (actors/crew)
SELECT 
    p.name,
    p.slug,
    mp.role,
    mp.character_name
FROM movie_person mp
JOIN people p ON mp.person_id = p.id
JOIN movies m ON mp.movie_id = m.id
WHERE m.slug = 'the-matrix-1999';
```

---

## 🐛 Troubleshooting

### Problem: 404 for existing movie

**Solution:**
- Check slug format (exact match required)
- Verify entity exists in database
- Clear cache: `php artisan cache:clear`

### Problem: Search returns no results

**Solution:**
- Check TMDB API key is configured
- Verify search query format
- Check logs for TMDB API errors

---

**Last updated:** 2025-12-18  
**Version:** 1.0


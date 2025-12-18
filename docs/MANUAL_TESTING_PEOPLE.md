# Manual Testing Guide - People API

> **Created:** 2025-12-18  
> **Context:** Detailed manual testing instructions for People API endpoints  
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

This document provides detailed manual testing instructions for all People API endpoints. For general setup and prerequisites, see the [Main Testing Guide](../MANUAL_TESTING_GUIDE.md).

**Related Documents:**
- [Main Testing Guide](../MANUAL_TESTING_GUIDE.md) - General setup and overview
- [Movies Testing Guide](../MANUAL_TESTING_MOVIES.md) - Movies API

---

## 📡 Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/people` | List all people (with pagination) |
| `GET` | `/api/v1/people/{slug}` | Get person details |
| `POST` | `/api/v1/people/{slug}/refresh` | Refresh person from TMDB |

---

## 🔍 Test Scenarios

### Scenario 1: List All People

**Objective:** Verify that listing people works.

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

### Scenario 4: Person Not Found (404)

**Objective:** Verify proper 404 handling.

**Steps:**

1. **Request non-existent person:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/people/non-existent-person-9999" \
     -H "Accept: application/json" | jq
   ```

2. **Verify response:**
   ```json
   {
     "error": "Person not found",
     "message": "The requested person could not be found."
   }
   ```
   - [ ] Status code: `404 Not Found`
   - [ ] Error message is clear

---

## 🗄️ Database Verification

### Check Person Data

```sql
-- List all people
SELECT id, name, slug, birth_date, birthplace, tmdb_id FROM people ORDER BY id;

-- Check person bios
SELECT pb.*, p.name as person_name
FROM actor_bios pb
JOIN people p ON pb.person_id = p.id
WHERE p.slug = 'keanu-reeves';

-- Check person movies
SELECT 
    m.title,
    m.slug,
    mp.role,
    mp.character_name
FROM movie_person mp
JOIN movies m ON mp.movie_id = m.id
JOIN people p ON mp.person_id = p.id
WHERE p.slug = 'keanu-reeves';
```

---

## 🐛 Troubleshooting

### Problem: 404 for existing person

**Solution:**
- Check slug format (exact match required)
- Verify person exists in database
- Clear cache: `php artisan cache:clear`

---

**Last updated:** 2025-12-18  
**Version:** 1.0


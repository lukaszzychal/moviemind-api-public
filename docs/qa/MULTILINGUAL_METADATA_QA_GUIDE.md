# Multilingual Movie Metadata - QA Testing Guide

> **For:** QA Engineers, Testers  
> **Related Task:** Multilingual Metadata Implementation  
> **Last Updated:** 2026-01-07

---

## 📋 Overview

This guide provides comprehensive testing scenarios and manual testing procedures for the Multilingual Metadata feature in MovieMind API.

## 🎯 What to Test

### Core Functionality
- ✅ Locale parameter acceptance
- ✅ Localized data retrieval
- ✅ Fallback to en-US when locale not found
- ✅ Invalid locale handling
- ✅ Backward compatibility (no locale parameter)
- ✅ All movie endpoints with locale support

## 🧪 Test Scenarios

### Scenario 1: Successful Locale Retrieval

**Objective:** Verify movie data is returned in requested locale when available.

**Steps:**
1. Ensure a movie has Polish locale data in database
2. Send request: `GET /api/v1/movies/{slug}?locale=pl-PL`
3. Verify response status is `200 OK`
4. Check response contains:
   - `locale: "pl-PL"`
   - `title_localized` field with Polish title
   - `director_localized` field with Polish director name (if available)
   - `tagline` field (if available)
   - `synopsis` field (if available)

**Expected Result:**
- Response contains localized data
- `locale` field matches requested locale
- All localized fields are present when data exists

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=pl-PL" \
  -H "Accept: application/json"
```

**Example Response:**
```json
{
  "id": "019b966c-e873-70cf-9851-fcb66b790b5d",
  "title": "The Matrix",
  "slug": "the-matrix-1999",
  "locale": "pl-PL",
  "title_localized": "Matrix",
  "director_localized": "Wachowscy",
  "tagline": "Świat się zmienił",
  "release_year": 1999,
  ...
}
```

---

### Scenario 2: Fallback to en-US

**Objective:** Verify API falls back to English when requested locale doesn't exist.

**Steps:**
1. Ensure a movie has only English locale data (no Polish)
2. Send request: `GET /api/v1/movies/{slug}?locale=pl-PL`
3. Verify response status is `200 OK`
4. Check response contains:
   - `locale: "en-US"` (fallback locale)
   - `title_localized` field with English title
   - English data is returned

**Expected Result:**
- Response contains English data (fallback)
- `locale` field shows `en-US`
- No error is returned

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=pl-PL" \
  -H "Accept: application/json"
```

**Example Response:**
```json
{
  "id": "019b966c-e873-70cf-9851-fcb66b790b5d",
  "title": "The Matrix",
  "slug": "the-matrix-1999",
  "locale": "en-US",
  "title_localized": "The Matrix",
  "release_year": 1999,
  ...
}
```

---

### Scenario 3: Invalid Locale Handling

**Objective:** Verify API handles invalid locale gracefully.

**Steps:**
1. Send request with invalid locale: `GET /api/v1/movies/{slug}?locale=invalid-locale`
2. Verify response status is `200 OK` (not error)
3. Check response falls back to `en-US`

**Expected Result:**
- No error is returned
- API falls back to `en-US`
- Response is valid JSON

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=invalid-locale" \
  -H "Accept: application/json"
```

**Example Response:**
```json
{
  "id": "019b966c-e873-70cf-9851-fcb66b790b5d",
  "title": "The Matrix",
  "slug": "the-matrix-1999",
  "locale": "en-US",
  ...
}
```

---

### Scenario 4: Missing Locale - No Data Available

**Objective:** Verify API handles case when no locale data exists at all.

**Steps:**
1. Ensure a movie has no locale data in `movie_locales` table
2. Send request: `GET /api/v1/movies/{slug}?locale=pl-PL`
3. Verify response status is `200 OK`
4. Check response:
   - Does NOT contain `locale` field
   - Does NOT contain `title_localized` field
   - Contains original `title` and `director` fields

**Expected Result:**
- Response is valid but doesn't include localized fields
- Original movie data is returned
- No error is returned

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=pl-PL" \
  -H "Accept: application/json"
```

**Example Response:**
```json
{
  "id": "019b966c-e873-70cf-9851-fcb66b790b5d",
  "title": "The Matrix",
  "slug": "the-matrix-1999",
  "release_year": 1999,
  "director": "Wachowskis",
  ...
}
```

---

### Scenario 5: Backward Compatibility - No Locale Parameter

**Objective:** Verify existing API clients work without locale parameter.

**Steps:**
1. Send request without locale parameter: `GET /api/v1/movies/{slug}`
2. Verify response status is `200 OK`
3. Check response structure matches previous API version
4. Verify no breaking changes

**Expected Result:**
- Response works exactly as before
- No `locale` field in response
- Original movie data returned
- No errors

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
  -H "Accept: application/json"
```

**Example Response:**
```json
{
  "id": "019b966c-e873-70cf-9851-fcb66b790b5d",
  "title": "The Matrix",
  "slug": "the-matrix-1999",
  "release_year": 1999,
  ...
}
```

---

### Scenario 6: All Supported Locales

**Objective:** Verify all supported locales work correctly.

**Supported Locales:**
- `en-US` (English - United States)
- `pl-PL` (Polish - Poland)
- `de-DE` (German - Germany)
- `fr-FR` (French - France)
- `es-ES` (Spanish - Spain)

**Steps:**
1. For each supported locale:
   - Send request: `GET /api/v1/movies/{slug}?locale={locale}`
   - Verify response status is `200 OK`
   - Verify `locale` field matches requested locale (or fallback)

**Expected Result:**
- All locales are accepted
- Responses are valid
- Fallback works when locale data doesn't exist

**Example Requests:**
```bash
# English
curl "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=en-US"

# Polish
curl "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=pl-PL"

# German
curl "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=de-DE"

# French
curl "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=fr-FR"

# Spanish
curl "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=es-ES"
```

---

### Scenario 7: List Movies with Locale

**Objective:** Verify list endpoint accepts locale parameter.

**Steps:**
1. Send request: `GET /api/v1/movies?locale=pl-PL`
2. Verify response status is `200 OK`
3. Check response structure

**Expected Result:**
- Response is valid
- Locale parameter is accepted (may or may not affect results depending on implementation)

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies?locale=pl-PL" \
  -H "Accept: application/json"
```

---

### Scenario 8: Search Movies with Locale

**Objective:** Verify search endpoint accepts locale parameter.

**Steps:**
1. Send request: `GET /api/v1/movies/search?q=matrix&locale=pl-PL`
2. Verify response status is `200 OK`
3. Check response structure

**Expected Result:**
- Response is valid
- Locale parameter is accepted (may or may not affect results depending on implementation)

**Example Request:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix&locale=pl-PL" \
  -H "Accept: application/json"
```

---

### Scenario 9: Bulk Movies with Locale

**Objective:** Verify bulk endpoint accepts locale parameter.

**Steps:**
1. Send request: `POST /api/v1/movies/bulk` with locale in query string
2. Verify response status is `200 OK`
3. Check response structure

**Expected Result:**
- Response is valid
- Locale parameter is accepted (may or may not affect results depending on implementation)

**Example Request:**
```bash
curl -X POST "http://localhost:8000/api/v1/movies/bulk?locale=pl-PL" \
  -H "Content-Type: application/json" \
  -d '{"slugs": ["the-matrix-1999"]}'
```

---

## 🔍 Manual Testing Procedures

### Test 1: Basic Locale Retrieval

```bash
# 1. Create test data (via admin or seeder)
# 2. Test Polish locale
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=pl-PL" | jq

# Expected: Response with locale=pl-PL and localized fields
```

### Test 2: Fallback Behavior

```bash
# 1. Ensure movie has only English locale
# 2. Request Polish locale
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=pl-PL" | jq

# Expected: Response with locale=en-US (fallback)
```

### Test 3: Invalid Locale

```bash
# Request with invalid locale
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=xyz-ABC" | jq

# Expected: Response with locale=en-US (fallback, no error)
```

### Test 4: Backward Compatibility

```bash
# Request without locale parameter
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" | jq

# Expected: Response without locale field (backward compatible)
```

### Test 5: All Endpoints

```bash
# List endpoint
curl "http://localhost:8000/api/v1/movies?locale=pl-PL" | jq

# Search endpoint
curl "http://localhost:8000/api/v1/movies/search?q=matrix&locale=pl-PL" | jq

# Show endpoint
curl "http://localhost:8000/api/v1/movies/the-matrix-1999?locale=pl-PL" | jq

# Bulk endpoint
curl -X POST "http://localhost:8000/api/v1/movies/bulk?locale=pl-PL" \
  -H "Content-Type: application/json" \
  -d '{"slugs": ["the-matrix-1999"]}' | jq
```

---

## ✅ Expected Results Summary

| Scenario | Status Code | Locale Field | Localized Fields | Notes |
|----------|-------------|--------------|------------------|-------|
| Locale exists | 200 | Requested locale | Present | All localized data returned |
| Locale not found (fallback) | 200 | en-US | Present | Falls back to English |
| Invalid locale | 200 | en-US | Present | Falls back to English |
| No locale data | 200 | Not present | Not present | Original data returned |
| No locale parameter | 200 | Not present | Not present | Backward compatible |

---

## 🐛 Known Issues / Limitations

1. **Search Endpoint:** Full locale support in search results may require additional implementation. Locale parameter is accepted but may not filter results by locale.

2. **List Endpoint:** Locale parameter is accepted but may not filter or localize list results. Individual movie details in list may not include localized fields.

3. **Bulk Endpoint:** Locale parameter is accepted but may not localize bulk results. Individual movie details in bulk response may not include localized fields.

4. **Test database:** All tests use PostgreSQL (same as production); no SQLite-specific limitations.

---

## 📝 Test Data Setup

### Creating Test Locale Data

To test locale functionality, you need to create `movie_locales` records:

```sql
-- Example: Create Polish locale for a movie
INSERT INTO movie_locales (id, movie_id, locale, title_localized, director_localized, tagline, synopsis, created_at, updated_at)
VALUES (
  gen_random_uuid(),
  (SELECT id FROM movies WHERE slug = 'the-matrix-1999'),
  'pl-PL',
  'Matrix',
  'Wachowscy',
  'Świat się zmienił',
  'Opis filmu Matrix po polsku',
  NOW(),
  NOW()
);
```

Or via Laravel Tinker:
```php
$movie = Movie::where('slug', 'the-matrix-1999')->first();
MovieLocale::create([
    'movie_id' => $movie->id,
    'locale' => \App\Enums\Locale::PL_PL,
    'title_localized' => 'Matrix',
    'director_localized' => 'Wachowscy',
    'tagline' => 'Świat się zmienił',
]);
```

---

## 🔗 Related Documentation

- **Business Documentation:** `docs/business/MULTILINGUAL_METADATA_BUSINESS.md`
- **API Specification:** `docs/openapi.yaml`
- **Development Roadmap:** `docs/en/MovieMind-Development-Roadmap.md`

---

**For questions or issues, contact the development team.**


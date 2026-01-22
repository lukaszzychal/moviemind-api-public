# MovieMind API - Manual Test Plans

> **For:** QA Engineers, Testers, Manual Testers  
> **Last Updated:** 2026-01-22  
> **Status:** Portfolio/Demo Project

---

## 📖 How to Use This Document

This document provides **complete manual testing guide** from basic setup to full functionality testing. Each test case includes:

- **Prerequisites:** What you need before testing
- **Steps:** Detailed test steps
- **cURL Commands:** Ready-to-copy commands with payloads
- **Expected Results:** What to expect

**Quick Navigation:**
- [Quick Start](#-quick-start---testing-from-scratch) - Start here for initial setup
- [Movies Endpoints](#-movies-endpoints) - Test movie-related functionality
- [People Endpoints](#-people-endpoints) - Test person-related functionality
- [TV Series/Shows](#-tv-series-endpoints) - Test TV content
- [Generation](#-generation-endpoint) - Test AI generation
- [Authentication](#-authentication--authorization) - Test auth and rate limiting
- [Health Checks](#-health-checks) - Test service health
- [External Integrations](#-external-integrations) - Test TMDB/TVmaze/OpenAI
- [Admin Panel UI](#-admin-panel-ui-testing) - Test admin interface (Filament)
- [Admin API](#-admin-features-api) - Test admin API endpoints

**Tip:** Copy cURL commands directly to your terminal. Replace `mm_your_api_key_here` with your actual API key.

---

## 🎯 Overview

This document provides comprehensive manual test plans for MovieMind API, including test cases for all endpoints, integrations, and features.

**Note:** This is a portfolio/demo project. For production deployment, see [Production Testing](#production-testing).

---

## 🚀 Quick Start - Testing from Scratch

### Prerequisites Setup

1. **Start Docker Services:**
```bash
docker compose up -d
```

2. **Run Migrations and Seeders:**
```bash
docker compose exec php php artisan migrate --seed
```

3. **Get Demo API Key:**
After seeding, check the console output or database for demo API keys:
- Free Plan: `mm_...` (prefix visible in logs)
- Pro Plan: `mm_...` (prefix visible in logs)
- Enterprise Plan: `mm_...` (prefix visible in logs)

Or check database:
```bash
docker compose exec db psql -U moviemind -d moviemind -c "SELECT name, key_prefix, plan_id FROM api_keys WHERE is_active = true;"
```

4. **Set Environment Variable (Optional):**
```bash
export API_KEY="mm_your_actual_api_key_here"
```

### Basic Test Flow

**1. Health Check:**
```bash
curl -X GET "http://localhost:8000/api/v1/health" \
  -H "X-API-Key: $API_KEY" \
  -H "Accept: application/json"
```

**2. List Movies:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies" \
  -H "X-API-Key: $API_KEY" \
  -H "Accept: application/json"
```

**3. Get Specific Movie:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
  -H "X-API-Key: $API_KEY" \
  -H "Accept: application/json"
```

**4. Generate Description (Pro/Enterprise only):**
```bash
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: $API_KEY" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "pl-PL",
    "context_tag": "modern"
  }'
```

**5. Check Job Status:**
```bash
# Replace {job_id} with job ID from previous response
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "X-API-Key: $API_KEY" \
  -H "Accept: application/json"
```

---

## 📋 Test Case Structure

Each test case includes:
- **Test ID:** Unique identifier
- **Description:** What is being tested
- **Prerequisites:** Required setup
- **Steps:** Detailed test steps
- **Expected Result:** Expected outcome
- **Priority:** P0 (Critical), P1 (High), P2 (Medium), P3 (Low)

---

## 🎬 Movies Endpoints

### TC-MOVIE-001: List Movies

**Test ID:** TC-MOVIE-001  
**Priority:** P0  
**Description:** Verify that listing movies returns correct data

**Prerequisites:**
- API key with Free plan or higher
- Movies exist in database

**Steps:**
1. Send `GET /api/v1/movies` with valid API key
2. Verify response status is `200 OK`
3. Verify response contains `data` array
4. Verify each movie has required fields: `id`, `slug`, `title`, `release_year`

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- Response contains array of movies
- Each movie has required fields
- HATEOAS links present

**Example Response:**
```json
{
  "data": [
    {
      "id": "01234567-89ab-cdef-0123-456789abcdef",
      "slug": "the-matrix-1999",
      "title": "The Matrix",
      "release_year": 1999
    }
  ],
  "links": {
    "self": "http://localhost:8000/api/v1/movies"
  }
}
```

---

### TC-MOVIE-002: Get Movie by Slug

**Test ID:** TC-MOVIE-002  
**Priority:** P0  
**Description:** Verify that retrieving movie by slug returns correct data

**Prerequisites:**
- API key with Free plan or higher
- Movie exists: `the-matrix-1999`

**Steps:**
1. Send `GET /api/v1/movies/the-matrix-1999` with valid API key
2. Verify response status is `200 OK`
3. Verify response contains movie data
4. Verify movie has descriptions array
5. Verify movie has people array
6. Verify HATEOAS links present

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- Movie data correct
- Descriptions array present
- People array present
- Links present

**Example Response:**
```json
{
  "data": {
    "id": "01234567-89ab-cdef-0123-456789abcdef",
    "slug": "the-matrix-1999",
    "title": "The Matrix",
    "release_year": 1999,
    "descriptions": [...],
    "people": [...]
  },
  "links": {
    "self": "http://localhost:8000/api/v1/movies/the-matrix-1999"
  }
}
```

---

### TC-MOVIE-003: Search Movies

**Test ID:** TC-MOVIE-003  
**Priority:** P1  
**Description:** Verify that searching movies returns relevant results

**Prerequisites:**
- API key with Free plan or higher
- Movies exist in database

**Steps:**
1. Send `GET /api/v1/movies/search?q=matrix` with valid API key
2. Verify response status is `200 OK`
3. Verify response contains relevant movies
4. Verify search results are sorted by relevance

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- Relevant movies returned
- Results sorted correctly

**Example Response:**
```json
{
  "data": [
    {
      "id": "01234567-89ab-cdef-0123-456789abcdef",
      "slug": "the-matrix-1999",
      "title": "The Matrix",
      "release_year": 1999
    }
  ],
  "meta": {
    "query": "matrix",
    "total": 1
  }
}
```

---

### TC-MOVIE-004: Bulk Retrieve Movies

**Test ID:** TC-MOVIE-004  
**Priority:** P1  
**Description:** Verify that bulk retrieve returns multiple movies

**Prerequisites:**
- API key with Pro plan or higher
- Movies exist: `the-matrix-1999`, `inception-2010`, `interstellar-2014`

**Steps:**
1. Send `POST /api/v1/movies/bulk` with slugs array
2. Verify response status is `200 OK`
3. Verify response contains all requested movies
4. Verify movies are in correct order

**cURL Command:**
```bash
curl -X POST "http://localhost:8000/api/v1/movies/bulk" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "slugs": [
      "the-matrix-1999",
      "inception-2010",
      "interstellar-2014"
    ]
  }'
```

**Expected Result:**
- Status: `200 OK`
- All requested movies returned
- Correct order maintained

**Example Response:**
```json
{
  "data": [
    {
      "id": "...",
      "slug": "the-matrix-1999",
      "title": "The Matrix"
    },
    {
      "id": "...",
      "slug": "inception-2010",
      "title": "Inception"
    },
    {
      "id": "...",
      "slug": "interstellar-2014",
      "title": "Interstellar"
    }
  ]
}
```

---

### TC-MOVIE-005: Compare Movies

**Test ID:** TC-MOVIE-005  
**Priority:** P2  
**Description:** Verify that comparing movies returns comparison data

**Prerequisites:**
- API key with Pro plan or higher
- Movies exist: `the-matrix-1999`, `inception-2010`

**Steps:**
1. Send `GET /api/v1/movies/compare?slugs=the-matrix-1999,inception-2010`
2. Verify response status is `200 OK`
3. Verify response contains both movies
4. Verify comparison data (common genres, people) present

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/compare?slugs=the-matrix-1999,inception-2010" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- Both movies returned
- Comparison data present

---

### TC-MOVIE-006: Get Related Movies

**Test ID:** TC-MOVIE-006  
**Priority:** P1  
**Description:** Verify that related movies endpoint returns related content

**Prerequisites:**
- API key with Free plan or higher
- Movie exists: `the-matrix-1999`
- Related movies exist

**Steps:**
1. Send `GET /api/v1/movies/the-matrix-1999/related` with valid API key
2. Verify response status is `200 OK`
3. Verify response contains related movies
4. Verify relationship types are correct

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999/related" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- Related movies returned
- Relationship types correct

---

### TC-MOVIE-007: Get Movie Collection

**Test ID:** TC-MOVIE-007  
**Priority:** P2  
**Description:** Verify that movie collection endpoint returns collection data

**Prerequisites:**
- API key with Free plan or higher
- Movie exists: `the-matrix-1999`
- Movie is part of collection

**Steps:**
1. Send `GET /api/v1/movies/the-matrix-1999/collection` with valid API key
2. Verify response status is `200 OK`
3. Verify response contains collection data
4. Verify all movies in collection are listed

**Expected Result:**
- Status: `200 OK`
- Collection data returned
- All collection movies listed

---

### TC-MOVIE-008: Refresh Movie Data

**Test ID:** TC-MOVIE-008  
**Priority:** P1  
**Description:** Verify that refresh endpoint queues generation job

**Prerequisites:**
- API key with Pro plan or higher
- Movie exists: `the-matrix-1999`
- Feature flag `ai_description_generation` enabled

**Steps:**
1. Send `POST /api/v1/movies/the-matrix-1999/refresh` with valid API key
2. Verify response status is `202 Accepted`
3. Verify response contains `job_id`
4. Poll `GET /api/v1/jobs/{job_id}` until status is `DONE`
5. Verify movie data refreshed

**cURL Command:**
```bash
curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/refresh" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Check Job Status:**
```bash
# Replace {job_id} with actual job ID from response
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `202 Accepted`
- Job ID returned
- Job completes successfully
- Movie data refreshed

---

### TC-MOVIE-009: Report Movie Issue

**Test ID:** TC-MOVIE-009  
**Priority:** P2  
**Description:** Verify that reporting movie issue creates report

**Prerequisites:**
- API key with Free plan or higher
- Movie exists: `the-matrix-1999`

**Steps:**
1. Send `POST /api/v1/movies/the-matrix-1999/report` with report data
2. Verify response status is `201 Created`
3. Verify response contains `report_id`
4. Verify report stored in database (via Admin API)

**Expected Result:**
- Status: `201 Created`
- Report ID returned
- Report stored correctly

---

### TC-MOVIE-010: Disambiguation

**Test ID:** TC-MOVIE-010  
**Priority:** P1  
**Description:** Verify that ambiguous movie requests return disambiguation options

**Prerequisites:**
- API key with Free plan or higher
- Multiple movies with same title/year exist

**Steps:**
1. Send `GET /api/v1/movies/heat-1995` (ambiguous)
2. Verify response status is `300 Multiple Choices`
3. Verify response contains `disambiguation` object
4. Verify disambiguation options are listed
5. Select option via `?slug=heat-1995-michael-mann`
6. Verify correct movie returned

**Expected Result:**
- Status: `300 Multiple Choices`
- Disambiguation options present
- Selection works correctly

---

## 👥 People Endpoints

Similar test cases to Movies:
- TC-PERSON-001: List People
- TC-PERSON-002: Get Person by Slug
- TC-PERSON-003: Search People
- TC-PERSON-004: Bulk Retrieve People
- TC-PERSON-005: Compare People
- TC-PERSON-006: Get Related People
- TC-PERSON-007: Refresh Person Data
- TC-PERSON-008: Report Person Issue
- TC-PERSON-009: Disambiguation

---

## 📺 TV Series Endpoints

### TC-TVSERIES-001: List TV Series

**Test ID:** TC-TVSERIES-001  
**Priority:** P0  
**Description:** Verify that listing TV series returns correct data

**Prerequisites:**
- API key with Free plan or higher
- TV series exist in database

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/tv-series" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- Response contains array of TV series
- Each TV series has required fields

---

### TC-TVSERIES-002: Get TV Series by Slug

**Test ID:** TC-TVSERIES-002  
**Priority:** P0  
**Description:** Verify that retrieving TV series by slug returns correct data

**Prerequisites:**
- API key with Free plan or higher
- TV series exists: `breaking-bad-2008`

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- TV series data correct
- Descriptions array present

---

### TC-TVSERIES-003: Search TV Series

**Test ID:** TC-TVSERIES-003  
**Priority:** P1  
**Description:** Verify that searching TV series returns relevant results

**Prerequisites:**
- API key with Free plan or higher
- TV series exist in database

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/tv-series/search?q=breaking" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- Relevant TV series returned

---

## 📺 TV Shows Endpoints

### TC-TVSHOW-001: List TV Shows

**Test ID:** TC-TVSHOW-001  
**Priority:** P0  
**Description:** Verify that listing TV shows returns correct data

**Prerequisites:**
- API key with Free plan or higher
- TV shows exist in database

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/tv-shows" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- Response contains array of TV shows
- Each TV show has required fields

---

### TC-TVSHOW-002: Get TV Show by Slug

**Test ID:** TC-TVSHOW-002  
**Priority:** P0  
**Description:** Verify that retrieving TV show by slug returns correct data

**Prerequisites:**
- API key with Free plan or higher
- TV show exists: `the-tonight-show-1954`

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/tv-shows/the-tonight-show-1954" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- TV show data correct
- Descriptions array present

---

### TC-TVSHOW-003: Search TV Shows

**Test ID:** TC-TVSHOW-003  
**Priority:** P1  
**Description:** Verify that searching TV shows returns relevant results

**Prerequisites:**
- API key with Free plan or higher
- TV shows exist in database

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/tv-shows/search?q=tonight" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- Relevant TV shows returned

---

## 🤖 Generation Endpoint

### TC-GEN-001: Generate Movie Description

**Test ID:** TC-GEN-001  
**Priority:** P0  
**Description:** Verify that generating movie description queues job

**Prerequisites:**
- API key with Pro plan or higher
- Feature flag `ai_description_generation` enabled
- Movie exists: `the-matrix-1999`

**Steps:**
1. Send `POST /api/v1/generate` with movie generation request
2. Verify response status is `202 Accepted`
3. Verify response contains `job_id`
4. Poll `GET /api/v1/jobs/{job_id}` until status is `DONE`
5. Verify description generated and saved

**cURL Command:**
```bash
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "pl-PL",
    "context_tag": "modern"
  }'
```

**Check Job Status:**
```bash
# Replace {job_id} with actual job ID from response
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `202 Accepted`
- Job ID returned
- Job completes successfully
- Description generated and saved

**Example Response:**
```json
{
  "success": true,
  "message": "Generation queued",
  "job_id": "01234567-89ab-cdef-0123-456789abcdef",
  "status": "PENDING"
}
```

---

### TC-GEN-002: Generate Person Biography

**Test ID:** TC-GEN-002  
**Priority:** P0  
**Description:** Verify that generating person biography queues job

**Prerequisites:**
- API key with Pro plan or higher
- Feature flag `ai_bio_generation` enabled
- Person exists: `keanu-reeves-1964`

**Steps:**
1. Send `POST /api/v1/generate` with person generation request
2. Verify response status is `202 Accepted`
3. Verify response contains `job_id`
4. Poll `GET /api/v1/jobs/{job_id}` until status is `DONE`
5. Verify biography generated and saved

**cURL Command:**
```bash
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "PERSON",
    "slug": "keanu-reeves-1964",
    "locale": "pl-PL",
    "context_tag": "modern"
  }'
```

**Check Job Status:**
```bash
# Replace {job_id} with actual job ID from response
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `202 Accepted`
- Job ID returned
- Job completes successfully
- Biography generated and saved

---

### TC-GEN-003: Generate with Context Tag

**Test ID:** TC-GEN-003  
**Priority:** P1  
**Description:** Verify that context tags affect generation style

**Prerequisites:**
- API key with Pro plan or higher
- Feature flag `ai_description_generation` enabled
- Movie exists: `the-matrix-1999`

**Steps:**
1. Generate with `context_tag: modern`
2. Generate with `context_tag: critical`
3. Generate with `context_tag: humorous`
4. Verify each description has different style

**cURL Commands:**

**Modern Style:**
```bash
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "pl-PL",
    "context_tag": "modern"
  }'
```

**Critical Style:**
```bash
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "pl-PL",
    "context_tag": "critical"
  }'
```

**Humorous Style:**
```bash
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "pl-PL",
    "context_tag": "humorous"
  }'
```

**Expected Result:**
- Different styles generated
- Context tags respected

---

### TC-GEN-004: Generate with Locale

**Test ID:** TC-GEN-004  
**Priority:** P1  
**Description:** Verify that locale affects generation language

**Prerequisites:**
- API key with Pro plan or higher
- Feature flag `ai_description_generation` enabled
- Movie exists: `the-matrix-1999`

**Steps:**
1. Generate with `locale: pl-PL`
2. Generate with `locale: en-US`
3. Generate with `locale: de-DE`
4. Verify each description in correct language

**cURL Commands:**

**Polish (pl-PL):**
```bash
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "pl-PL",
    "context_tag": "modern"
  }'
```

**English (en-US):**
```bash
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "en-US",
    "context_tag": "modern"
  }'
```

**German (de-DE):**
```bash
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "de-DE",
    "context_tag": "modern"
  }'
```

**Expected Result:**
- Descriptions in correct languages
- Locale respected

---

## 🔑 Authentication & Authorization

### TC-AUTH-001: API Key Authentication

**Test ID:** TC-AUTH-001  
**Priority:** P0  
**Description:** Verify that API key authentication works

**Steps:**
1. Send request without API key
2. Verify response status is `401 Unauthorized`
3. Send request with invalid API key
4. Verify response status is `401 Unauthorized`
5. Send request with valid API key
6. Verify response status is `200 OK`

**cURL Commands:**

**Without API Key (should fail):**
```bash
curl -X GET "http://localhost:8000/api/v1/movies" \
  -H "Accept: application/json"
```

**With Invalid API Key (should fail):**
```bash
curl -X GET "http://localhost:8000/api/v1/movies" \
  -H "X-API-Key: mm_invalid_key_12345" \
  -H "Accept: application/json"
```

**With Valid API Key (should succeed):**
```bash
curl -X GET "http://localhost:8000/api/v1/movies" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Without API key: `401 Unauthorized`
- Invalid API key: `401 Unauthorized`
- Valid API key: `200 OK`

---

### TC-AUTH-002: Rate Limiting

**Test ID:** TC-AUTH-002  
**Priority:** P0  
**Description:** Verify that rate limiting is enforced per plan

**Prerequisites:**
- API key with Free plan (10 req/min limit)

**Steps:**
1. Send 10 requests within 1 minute
2. Verify all requests succeed
3. Send 11th request
4. Verify response status is `429 Too Many Requests`
5. Wait 1 minute
6. Send request again
7. Verify request succeeds

**cURL Commands:**

**Free Plan (10 requests/minute):**
```bash
# Send 11 requests rapidly (11th should fail)
for i in {1..11}; do
  echo "Request $i:"
  curl -X GET "http://localhost:8000/api/v1/movies" \
    -H "X-API-Key: mm_free_plan_key_here" \
    -H "Accept: application/json" \
    -w "\nHTTP Status: %{http_code}\n\n" \
    -o /dev/null -s
  sleep 0.1
done
```

**Check Rate Limit Headers:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json" \
  -i | grep -i "x-ratelimit"
```

**Expected Result:**
- Rate limit enforced correctly
- Headers present (X-RateLimit-Limit, X-RateLimit-Remaining)
- Retry after works
- Different limits per plan (Free: 10, Pro: 100, Enterprise: 1000)

---

## 🌍 Multilingual Support

### TC-I18N-001: Locale Parameter

**Test ID:** TC-I18N-001  
**Priority:** P1  
**Description:** Verify that locale parameter affects response

**Prerequisites:**
- API key with Free plan or higher
- Movie exists with multiple locale descriptions

**Steps:**
1. Request with `locale: pl-PL`
2. Verify response contains Polish description
3. Request with `locale: en-US`
4. Verify response contains English description
5. Request with invalid locale
6. Verify fallback to `en-US`

**Expected Result:**
- Correct locale returned
- Fallback works correctly

---

## 🔗 External Integrations

### TC-INT-TMDB-001: TMDB Verification

**Test ID:** TC-INT-TMDB-001  
**Priority:** P1  
**Description:** Verify that TMDB verification works

**Prerequisites:**
- Feature flag `tmdb_verification` enabled
- Valid TMDB API key configured

**Steps:**
1. Request movie not in database: `annihilation-2018`
2. Verify TMDB verification triggered
3. Verify movie created from TMDB data
4. Verify response contains movie data

**cURL Commands:**

**Request Movie (triggers TMDB verification):**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/annihilation-2018" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Check TMDB Health:**
```bash
curl -X GET "http://localhost:8000/api/v1/health/tmdb" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- TMDB verification works
- Movie created correctly
- Data from TMDB

---

### TC-INT-TVMAZE-001: TVmaze Verification

**Test ID:** TC-INT-TVMAZE-001  
**Priority:** P1  
**Description:** Verify that TVmaze verification works

**Prerequisites:**
- Feature flag `tvmaze_verification` enabled

**Steps:**
1. Request TV series not in database: `breaking-bad-2008`
2. Verify TVmaze verification triggered
3. Verify TV series created from TVmaze data
4. Verify response contains TV series data

**cURL Commands:**

**Request TV Series (triggers TVmaze verification):**
```bash
curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Check TVmaze Health:**
```bash
curl -X GET "http://localhost:8000/api/v1/health/tvmaze" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- TVmaze verification works
- TV series created correctly
- Data from TVmaze

---

### TC-INT-OPENAI-001: OpenAI Generation

**Test ID:** TC-INT-OPENAI-001  
**Priority:** P0  
**Description:** Verify that OpenAI generation works

**Prerequisites:**
- Feature flag `ai_description_generation` enabled
- Valid OpenAI API key configured
- API key with Pro plan or higher

**Steps:**
1. Request generation for movie
2. Verify job queued
3. Poll job status until `DONE`
4. Verify description generated
5. Verify description is unique (not copied)

**cURL Commands:**

**Generate Description (triggers OpenAI API):**
```bash
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "pl-PL",
    "context_tag": "modern"
  }'
```

**Check Job Status:**
```bash
# Replace {job_id} with job ID from response
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Check OpenAI Health:**
```bash
curl -X GET "http://localhost:8000/api/v1/health/openai" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Generation works
- Description unique (not copied from external sources)
- Job completes successfully

---

## 🎛️ Admin Features (API)

### TC-ADMIN-001: Feature Flag Management (API)

**Test ID:** TC-ADMIN-001  
**Priority:** P1  
**Description:** Verify that feature flags can be toggled via API

**Prerequisites:**
- Admin credentials
- API key with admin access

**Steps:**
1. List all flags via `GET /api/v1/admin/flags`
2. Enable flag via `POST /api/v1/admin/flags/{name}`
3. Verify flag enabled
4. Disable flag via `DELETE /api/v1/admin/flags/{name}`
5. Verify flag disabled

**cURL Commands:**

**List All Flags:**
```bash
curl -X GET "http://localhost:8000/api/v1/admin/flags" \
  -H "X-API-Key: mm_your_admin_api_key_here" \
  -H "Accept: application/json"
```

**Enable Flag:**
```bash
curl -X POST "http://localhost:8000/api/v1/admin/flags/ai_description_generation" \
  -H "X-API-Key: mm_your_admin_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"state": "on"}'
```

**Disable Flag:**
```bash
curl -X DELETE "http://localhost:8000/api/v1/admin/flags/ai_description_generation" \
  -H "X-API-Key: mm_your_admin_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Flags can be toggled
- Changes take effect immediately

---

### TC-ADMIN-002: API Key Management (API)

**Test ID:** TC-ADMIN-002  
**Priority:** P1  
**Description:** Verify that API keys can be managed via API

**Prerequisites:**
- Admin credentials
- API key with admin access

**Steps:**
1. Create API key via `POST /api/v1/admin/api-keys`
2. Verify key created and shown (once)
3. List keys via `GET /api/v1/admin/api-keys`
4. Revoke key via `POST /api/v1/admin/api-keys/{id}/revoke`
5. Verify key revoked
6. Regenerate key via `POST /api/v1/admin/api-keys/{id}/regenerate`
7. Verify new key generated

**cURL Commands:**

**Create API Key:**
```bash
curl -X POST "http://localhost:8000/api/v1/admin/api-keys" \
  -H "X-API-Key: mm_your_admin_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test API Key",
    "plan_id": 2
  }'
```

**List API Keys:**
```bash
curl -X GET "http://localhost:8000/api/v1/admin/api-keys" \
  -H "X-API-Key: mm_your_admin_api_key_here" \
  -H "Accept: application/json"
```

**Revoke API Key:**
```bash
# Replace {id} with actual API key ID
curl -X POST "http://localhost:8000/api/v1/admin/api-keys/{id}/revoke" \
  -H "X-API-Key: mm_your_admin_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Keys can be created, revoked, regenerated
- Security maintained

---

## 🖥️ Admin Panel UI Testing

> **Note:** For comprehensive UI testing, see [Admin Panel Manual Test Plan](ADMIN_PANEL_MANUAL_TEST_PLAN.md)

### Quick Access

**URL:** `http://localhost:8000/admin`

**Default Admin Credentials:**
- **Email:** `admin@moviemind.local`
- **Password:** `password123`

**Setup (if needed):**
```bash
# Seed admin user
docker compose exec php php artisan db:seed --class=AdminUserSeeder
```

---

### TC-UI-001: Admin Panel Login

**Test ID:** TC-UI-001  
**Priority:** P0  
**Description:** Verify that admin panel login works

**Prerequisites:**
- Admin user exists in database
- Docker services running

**Steps:**
1. Open `http://localhost:8000/admin` in browser
2. Verify redirect to login page (`/admin/login`)
3. Enter invalid credentials
4. Verify error message displayed
5. Enter valid credentials (`admin@moviemind.local` / `password123`)
6. Verify successful login and redirect to dashboard

**Expected Result:**
- Login page accessible
- Invalid credentials rejected
- Valid credentials accepted
- Dashboard accessible after login

---

### TC-UI-002: Dashboard Widgets

**Test ID:** TC-UI-002  
**Priority:** P0  
**Description:** Verify that dashboard widgets display correctly

**Prerequisites:**
- Logged in as admin
- Database contains test data (run seeders)

**Steps:**
1. Navigate to dashboard (`/admin`)
2. Verify `StatsOverview` widget displays:
   - Total Movies
   - Total People
   - Total TV Series
   - Total TV Shows
   - Pending Jobs
   - Failed Jobs
3. Verify `JobsChart` widget displays
4. Verify `RecentJobsWidget` displays
5. Verify `FailedJobsWidget` displays

**Expected Result:**
- All widgets visible
- Statistics accurate
- Charts render correctly

---

### TC-UI-003: Movie Resource Management

**Test ID:** TC-UI-003  
**Priority:** P1  
**Description:** Verify that movie resources can be managed via UI

**Prerequisites:**
- Logged in as admin
- Movies exist in database

**Steps:**
1. Navigate to Movies list (`/admin/movies`)
2. Verify table displays movies
3. Verify columns: `Descriptions` (count), `TMDb` (link)
4. Click `TMDb` link - verify opens in new tab
5. Click `Generate AI` action (⚡ icon)
6. Fill modal: select locale and context tag
7. Submit modal - verify notification "Generation queued successfully"
8. Navigate to movie edit page (`/admin/movies/{id}/edit`)
9. Verify `Descriptions` section displays list of descriptions

**Expected Result:**
- Movies list displays correctly
- TMDb links work
- AI generation modal works
- Descriptions section displays correctly

---

### TC-UI-004: Feature Flags Management (UI)

**Test ID:** TC-UI-004  
**Priority:** P1  
**Description:** Verify that feature flags can be managed via UI

**Prerequisites:**
- Logged in as admin

**Steps:**
1. Navigate to Feature Flags page (`/admin/feature-flags`)
2. Verify list of flags displays
3. Toggle a flag (e.g., `ai_description_generation`)
4. Verify flag state changes
5. Verify notification confirms change
6. Test API endpoint to verify flag state changed

**Expected Result:**
- Feature flags page accessible
- Flags can be toggled
- Changes reflected immediately
- API confirms changes

---

### TC-UI-005: Jobs Dashboard

**Test ID:** TC-UI-005  
**Priority:** P1  
**Description:** Verify that jobs dashboard displays correctly

**Prerequisites:**
- Logged in as admin
- Jobs exist in database (run some generation jobs)

**Steps:**
1. Navigate to Jobs Dashboard (`/admin/jobs-dashboard`)
2. Verify job statistics display
3. Verify recent jobs list
4. Verify failed jobs list
5. Verify job status filters work

**Expected Result:**
- Jobs dashboard accessible
- Statistics accurate
- Job lists display correctly
- Filters work

---

### TC-UI-006: Reports Management

**Test ID:** TC-UI-006  
**Priority:** P2  
**Description:** Verify that reports can be managed via UI

**Prerequisites:**
- Logged in as admin
- Reports exist in database (create via API or seeder)

**Steps:**
1. Navigate to Reports list (`/admin/reports`)
2. Verify table displays reports
3. Use `Status` filter - select `pending`
4. Verify only pending reports displayed
5. Use `Entity Type` filter - select `movie`
6. Verify only movie reports displayed
7. Click `Verify & Regenerate` action (if status is `pending`)
8. Verify confirmation dialog appears

**Expected Result:**
- Reports list displays correctly
- Filters work
- Actions available for pending reports

---

### Complete UI Test Flow

**Full Test Scenario:**
1. **Login** → Access admin panel
2. **Dashboard** → Verify widgets and statistics
3. **Movies** → List, view, edit, generate AI
4. **People** → List, view, edit, generate AI
5. **TV Series** → List, view, edit, generate AI
6. **TV Shows** → List, view, edit, generate AI
7. **Feature Flags** → Toggle flags, verify changes
8. **Jobs Dashboard** → Monitor jobs, check status
9. **Reports** → View, filter, manage reports
10. **Logout** → Verify logout works

**For detailed step-by-step instructions, see:** [Admin Panel Manual Test Plan](ADMIN_PANEL_MANUAL_TEST_PLAN.md)

---

## ⚠️ Error Scenarios

### TC-ERROR-001: 404 Not Found

**Test ID:** TC-ERROR-001  
**Priority:** P0  
**Description:** Verify that 404 is returned for non-existent resources

**Steps:**
1. Request non-existent movie: `GET /api/v1/movies/non-existent-1999`
2. Verify response status is `404 Not Found`
3. Verify error message is clear

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/movies/non-existent-1999" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `404 Not Found`
- Clear error message

**Example Error Response:**
```json
{
  "success": false,
  "error": "Not Found",
  "message": "Movie not found: non-existent-1999"
}
```

---

### TC-ERROR-002: 422 Validation Error

**Test ID:** TC-ERROR-002  
**Priority:** P0  
**Description:** Verify that validation errors are returned

**Steps:**
1. Send invalid request (missing required fields)
2. Verify response status is `422 Unprocessable Entity`
3. Verify validation errors are detailed

**Expected Result:**
- Status: `422 Unprocessable Entity`
- Detailed validation errors

---

### TC-ERROR-003: 429 Rate Limit Exceeded

**Test ID:** TC-ERROR-003  
**Priority:** P0  
**Description:** Verify that rate limit errors are returned

**Steps:**
1. Exceed rate limit (send too many requests)
2. Verify response status is `429 Too Many Requests`
3. Verify `retry_after` header present

**cURL Command (Exceed Rate Limit):**
```bash
# Send 11 requests rapidly to exceed Free plan limit (10/minute)
for i in {1..11}; do
  echo "Request $i:"
  curl -X GET "http://localhost:8000/api/v1/movies" \
    -H "X-API-Key: mm_free_plan_key_here" \
    -H "Accept: application/json" \
    -w "\nHTTP Status: %{http_code}\n\n"
  sleep 0.1
done
```

**Expected Result:**
- Status: `429 Too Many Requests` (for 11th request)
- Retry information provided
- Rate limit headers present

**Example Error Response:**
```json
{
  "success": false,
  "error": "Rate limit exceeded",
  "message": "Too many requests. Please try again later.",
  "retry_after": 60,
  "limit": 10,
  "remaining": 0
}
```

---

## 🏥 Health Checks

### TC-HEALTH-001: API Health Check

**Test ID:** TC-HEALTH-001  
**Priority:** P0  
**Description:** Verify that API health check endpoint works

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/health" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK`
- Response indicates API is healthy

**Example Response:**
```json
{
  "success": true,
  "service": "api",
  "status": "healthy",
  "timestamp": "2026-01-22T18:00:00Z"
}
```

---

### TC-HEALTH-002: TMDB Health Check

**Test ID:** TC-HEALTH-002  
**Priority:** P1  
**Description:** Verify that TMDB health check endpoint works

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/health/tmdb" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK` (if TMDB accessible) or `503 Service Unavailable` (if not)
- Response indicates TMDB service status

---

### TC-HEALTH-003: TVmaze Health Check

**Test ID:** TC-HEALTH-003  
**Priority:** P1  
**Description:** Verify that TVmaze health check endpoint works

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/health/tvmaze" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK` (if TVmaze accessible) or `503 Service Unavailable` (if not)
- Response indicates TVmaze service status

---

### TC-HEALTH-004: OpenAI Health Check

**Test ID:** TC-HEALTH-004  
**Priority:** P1  
**Description:** Verify that OpenAI health check endpoint works

**cURL Command:**
```bash
curl -X GET "http://localhost:8000/api/v1/health/openai" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

**Expected Result:**
- Status: `200 OK` (if OpenAI accessible) or `503 Service Unavailable` (if not)
- Response indicates OpenAI service status

---

## 📊 Test Scenarios

### Scenario 1: Happy Path - Movie Retrieval

**Complete Flow:**
1. Search for movie
2. Get movie details
3. Generate description
4. Check job status
5. Retrieve movie with new description

**cURL Commands:**
```bash
# 1. Search for movie
curl -X GET "http://localhost:8000/api/v1/movies/search?q=matrix" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"

# 2. Get movie details
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"

# 3. Generate description
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "pl-PL",
    "context_tag": "modern"
  }'

# 4. Check job status (replace {job_id} with actual job ID)
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"

# 5. Retrieve movie with new description
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
  -H "X-API-Key: mm_your_api_key_here" \
  -H "Accept: application/json"
```

---

### Scenario 2: Complete User Journey - From Search to Generation

**Complete Flow:**
1. Health check
2. Search for content
3. Get details
4. Generate AI content
5. Monitor job
6. Retrieve final result
2. Get movie details: `GET /api/v1/movies/the-matrix-1999`
3. Get related movies: `GET /api/v1/movies/the-matrix-1999/related`
4. Get collection: `GET /api/v1/movies/the-matrix-1999/collection`

**Expected:** All requests succeed, data is correct

---

### Scenario 2: AI Generation Flow

1. Request movie not in database: `GET /api/v1/movies/annihilation-2018`
2. Verify `202 Accepted` with job ID
3. Poll job status: `GET /api/v1/jobs/{job_id}`
4. Wait for `DONE` status
5. Request movie again: `GET /api/v1/movies/annihilation-2018`
6. Verify movie data with description

**Expected:** Generation completes, movie available

---

### Scenario 3: Multilingual Content

1. Generate description in Polish: `POST /api/v1/generate` with `locale: pl-PL`
2. Generate description in English: `POST /api/v1/generate` with `locale: en-US`
3. Request movie with Polish locale: `GET /api/v1/movies/the-matrix-1999?locale=pl-PL`
4. Request movie with English locale: `GET /api/v1/movies/the-matrix-1999?locale=en-US`
5. Verify correct descriptions returned

**Expected:** Multilingual content works correctly

---

## ✅ Pre-Release Checklist

### Functional Tests
- [ ] All endpoints tested
- [ ] All entity types tested
- [ ] All error scenarios tested
- [ ] Authentication/authorization tested
- [ ] Rate limiting tested

### Integration Tests
- [ ] TMDB integration tested
- [ ] TVmaze integration tested
- [ ] OpenAI integration tested
- [ ] Health checks tested

### Admin Tests
- [ ] Feature flags tested
- [ ] API key management tested
- [ ] Analytics tested
- [ ] Reports management tested

### Performance Tests
- [ ] Response times acceptable
- [ ] Rate limiting works
- [ ] Caching works
- [ ] Bulk operations efficient

---

## 📚 Related Documentation

- [Test Strategy](TEST_STRATEGY.md) - Testing strategy overview
- [Automated Tests](AUTOMATED_TESTS.md) - Automated testing guide
- [API Testing Guide](../../API_TESTING_GUIDE.md) - API testing examples
- [Admin Panel Manual Test Plan](ADMIN_PANEL_MANUAL_TEST_PLAN.md) - Admin panel tests

---

**Last Updated:** 2026-01-22  
**Status:** Portfolio/Demo Project

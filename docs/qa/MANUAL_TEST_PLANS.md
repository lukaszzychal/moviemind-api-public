# MovieMind API - Manual Test Plans

> **For:** QA Engineers, Testers, Manual Testers  
> **Last Updated:** 2026-01-21  
> **Status:** Portfolio/Demo Project

---

## 🎯 Overview

This document provides comprehensive manual test plans for MovieMind API, including test cases for all endpoints, integrations, and features.

**Note:** This is a portfolio/demo project. For production deployment, see [Production Testing](#production-testing).

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

**Expected Result:**
- Status: `200 OK`
- Response contains array of movies
- Each movie has required fields
- HATEOAS links present

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

**Expected Result:**
- Status: `200 OK`
- Movie data correct
- Descriptions array present
- People array present
- Links present

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

**Expected Result:**
- Status: `200 OK`
- Relevant movies returned
- Results sorted correctly

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

**Expected Result:**
- Status: `200 OK`
- All requested movies returned
- Correct order maintained

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

Similar test cases to Movies:
- TC-TVSERIES-001: List TV Series
- TC-TVSERIES-002: Get TV Series by Slug
- TC-TVSERIES-003: Search TV Series
- TC-TVSERIES-004: Compare TV Series
- TC-TVSERIES-005: Get Related TV Series
- TC-TVSERIES-006: Refresh TV Series Data
- TC-TVSERIES-007: Report TV Series Issue

---

## 📺 TV Shows Endpoints

Similar test cases to Movies:
- TC-TVSHOW-001: List TV Shows
- TC-TVSHOW-002: Get TV Show by Slug
- TC-TVSHOW-003: Search TV Shows
- TC-TVSHOW-004: Compare TV Shows
- TC-TVSHOW-005: Get Related TV Shows
- TC-TVSHOW-006: Refresh TV Show Data
- TC-TVSHOW-007: Report TV Show Issue

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

**Expected Result:**
- Status: `202 Accepted`
- Job ID returned
- Job completes successfully
- Description generated and saved

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

**Expected Result:**
- Missing/invalid key: `401 Unauthorized`
- Valid key: `200 OK`

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

**Expected Result:**
- Rate limit enforced correctly
- Headers present
- Retry after works

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
- Valid TMDB API key

**Steps:**
1. Request movie not in database: `annihilation-2018`
2. Verify TMDB verification triggered
3. Verify movie created from TMDB data
4. Verify response contains movie data

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
- Valid OpenAI API key
- API key with Pro plan or higher

**Steps:**
1. Request generation for movie
2. Verify job queued
3. Poll job status until `DONE`
4. Verify description generated
5. Verify description is unique (not copied)

**Expected Result:**
- Generation works
- Description unique
- Job completes successfully

---

## 🎛️ Admin Features

### TC-ADMIN-001: Feature Flag Management

**Test ID:** TC-ADMIN-001  
**Priority:** P1  
**Description:** Verify that feature flags can be toggled

**Prerequisites:**
- Admin credentials

**Steps:**
1. List all flags via `GET /api/v1/admin/flags`
2. Enable flag via `POST /api/v1/admin/flags/{name}`
3. Verify flag enabled
4. Disable flag via `DELETE /api/v1/admin/flags/{name}`
5. Verify flag disabled

**Expected Result:**
- Flags can be toggled
- Changes take effect immediately

---

### TC-ADMIN-002: API Key Management

**Test ID:** TC-ADMIN-002  
**Priority:** P1  
**Description:** Verify that API keys can be managed

**Prerequisites:**
- Admin credentials

**Steps:**
1. Create API key via `POST /api/v1/admin/api-keys`
2. Verify key created and shown (once)
3. List keys via `GET /api/v1/admin/api-keys`
4. Revoke key via `POST /api/v1/admin/api-keys/{id}/revoke`
5. Verify key revoked
6. Regenerate key via `POST /api/v1/admin/api-keys/{id}/regenerate`
7. Verify new key generated

**Expected Result:**
- Keys can be created, revoked, regenerated
- Security maintained

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

**Expected Result:**
- Status: `404 Not Found`
- Clear error message

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

**Expected Result:**
- Status: `429 Too Many Requests`
- Retry information provided

---

## 📊 Test Scenarios

### Scenario 1: Happy Path - Movie Retrieval

1. Search for movie: `GET /api/v1/movies/search?q=matrix`
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

**Last Updated:** 2026-01-21  
**Status:** Portfolio/Demo Project

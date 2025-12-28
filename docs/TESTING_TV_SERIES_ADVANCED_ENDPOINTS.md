# Testing Guide - TV Series & TV Shows Advanced Endpoints

> **Created:** 2025-12-28  
> **Context:** Comprehensive testing guide for TV Series and TV Shows advanced endpoints  
> **Category:** reference  
> **Target Audience:** QA Engineers, Testers  
> **Related:** [Complete Manual Testing Guide](./MANUAL_TESTING_GUIDE.md)

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [Endpoint Summary](#endpoint-summary)
4. [Related Endpoints](#related-endpoints)
5. [Refresh Endpoints](#refresh-endpoints)
6. [Report Endpoints](#report-endpoints)
7. [Compare Endpoints](#compare-endpoints)
8. [Admin Panel Integration](#admin-panel-integration)
9. [Test Scenarios Matrix](#test-scenarios-matrix)
10. [Common Issues & Solutions](#common-issues--solutions)

---

## 🎯 Overview

This guide provides comprehensive testing instructions specifically for the advanced endpoints added to TV Series and TV Shows:

- **Related** - Get related TV series/shows
- **Refresh** - Refresh metadata from TMDb
- **Report** - Report issues with descriptions
- **Compare** - Compare two TV series/shows

These endpoints mirror the functionality of Movie endpoints, ensuring consistency across entity types.

### Key Differences from Movies

- TV Series/Shows use `first_air_date` instead of `release_year`
- TV Series/Shows may have `number_of_seasons` and `number_of_episodes`
- Relationship types are similar but context may differ (e.g., spinoffs are more common)

---

## 📋 Prerequisites

### Required Setup

1. **Database** - TV Series and TV Shows must exist with relationships and descriptions
2. **TMDb Integration** - TV Series/Shows should have TMDb snapshots for refresh to work
3. **Queue Worker** - Laravel Horizon or `php artisan queue:work` running (for regeneration jobs)
4. **Admin Access** - Basic Auth credentials for admin endpoints

### Test Data Requirements

**Minimum test data:**
- At least 2 TV Series with relationships
- At least 2 TV Shows with relationships
- TV Series/Shows with TMDb snapshots (for refresh)
- TV Series/Shows with descriptions (for reports)

**Recommended test data:**
- TV Series with multiple relationships (sequels, spinoffs)
- TV Shows in same collection/universe
- TV Series/Shows with common genres and people (for compare)

---

## 📊 Endpoint Summary

### TV Series Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `GET` | `/api/v1/tv-series/{slug}/related` | Get related TV series | No |
| `POST` | `/api/v1/tv-series/{slug}/refresh` | Refresh from TMDb | No |
| `POST` | `/api/v1/tv-series/{slug}/report` | Report an issue | No |
| `GET` | `/api/v1/tv-series/compare` | Compare two TV series | No |

### TV Shows Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `GET` | `/api/v1/tv-shows/{slug}/related` | Get related TV shows | No |
| `POST` | `/api/v1/tv-shows/{slug}/refresh` | Refresh from TMDb | No |
| `POST` | `/api/v1/tv-shows/{slug}/report` | Report an issue | No |
| `GET` | `/api/v1/tv-shows/compare` | Compare two TV shows | No |

### Admin Endpoints

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| `GET` | `/api/v1/admin/reports?type=tv_series` | List TV Series reports | Yes |
| `GET` | `/api/v1/admin/reports?type=tv_show` | List TV Show reports | Yes |
| `POST` | `/api/v1/admin/reports/{id}/verify` | Verify report | Yes |

---

## 🔗 Related Endpoints

### GET `/api/v1/tv-series/{slug}/related`

**Purpose:** Retrieve TV series related to the specified TV series.

#### Test Scenario 1: Basic Related Query

**Steps:**

1. **Request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/related" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (200 OK):**
   ```json
   {
     "tv_series": {
       "id": "...",
       "slug": "breaking-bad-2008",
       "title": "Breaking Bad",
       "first_air_date": "2008-01-20"
     },
     "related_tv_series": [
       {
         "id": "...",
         "slug": "better-call-saul-2015",
         "title": "Better Call Saul",
         "relationship_type": "SPINOFF",
         "relationship_label": "Spinoff",
         "_links": {
           "self": {
             "href": "http://localhost:8000/api/v1/tv-series/better-call-saul-2015"
           }
         }
       }
     ],
     "count": 1,
     "_links": {
       "self": {
         "href": "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/related"
       }
     }
   }
   ```

3. **Verification Checklist:**
   - [ ] Status code: `200 OK`
   - [ ] `tv_series` object contains correct TV series info
   - [ ] `related_tv_series` is an array (may be empty)
   - [ ] Each related TV series has `relationship_type` and `relationship_label`
   - [ ] `count` matches array length
   - [ ] `_links` present and correct
   - [ ] No `tmdb_id` in response (security)

#### Test Scenario 2: Non-Existent TV Series

**Steps:**

1. **Request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/non-existent-series-9999/related" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (404 Not Found):**
   ```json
   {
     "error": "TV series not found"
   }
   ```

3. **Verification:**
   - [ ] Status code: `404 Not Found`
   - [ ] Error message is clear

#### Test Scenario 3: TV Series Without Relationships

**Steps:**

1. **Request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/standalone-series-2020/related" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (200 OK):**
   ```json
   {
     "tv_series": {...},
     "related_tv_series": [],
     "count": 0,
     "_links": {...}
   }
   ```

3. **Verification:**
   - [ ] Status code: `200 OK`
   - [ ] `related_tv_series` is empty array
   - [ ] `count` is `0`
   - [ ] No errors thrown

#### Test Scenario 4: Filter by Relationship Type

**Steps:**

1. **Request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/related?type=collection" \
     -H "Accept: application/json" | jq
   ```

2. **Verification:**
   - [ ] Only relationships of specified type are returned
   - [ ] Filter parameter works correctly

---

### GET `/api/v1/tv-shows/{slug}/related`

**Note:** Follows the same pattern as TV Series related endpoint. Test all scenarios above with TV Shows slugs instead.

---

## 🔄 Refresh Endpoints

### POST `/api/v1/tv-series/{slug}/refresh`

**Purpose:** Refresh TV series metadata from TMDb snapshot.

**⚠️ Important:** Requires `POST` method. Using `GET` returns `405 Method Not Allowed`.

#### Test Scenario 1: Successful Refresh

**Prerequisites:**
- TV series exists with TMDb snapshot

**Steps:**

1. **Request:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/refresh" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (200 OK):**
   ```json
   {
     "message": "TV series data refreshed from TMDb",
     "slug": "breaking-bad-2008",
     "tv_series_id": "...",
     "refreshed_at": "2025-12-28T10:30:00+00:00"
   }
   ```

3. **Verification Checklist:**
   - [ ] Status code: `200 OK`
   - [ ] Response contains success message
   - [ ] `refreshed_at` timestamp is present
   - [ ] TV series metadata updated in database
   - [ ] Verify by fetching TV series again

4. **Database Verification:**
   ```bash
   # Fetch TV series after refresh
   curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008" | jq
   ```
   - [ ] Metadata reflects latest TMDb data

#### Test Scenario 2: TV Series Without TMDb Snapshot

**Steps:**

1. **Request:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/tv-series/no-snapshot-series/refresh" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (404 Not Found):**
   ```json
   {
     "error": "No TMDb snapshot found for this TV series"
   }
   ```

3. **Verification:**
   - [ ] Status code: `404 Not Found`
   - [ ] Clear error message

#### Test Scenario 3: Non-Existent TV Series

**Steps:**

1. **Request:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/tv-series/non-existent-series/refresh" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (404 Not Found):**
   ```json
   {
     "error": "TV series not found"
   }
   ```

3. **Verification:**
   - [ ] Status code: `404 Not Found`

#### Test Scenario 4: Wrong HTTP Method

**Steps:**

1. **Request (GET instead of POST):**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/refresh" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (405 Method Not Allowed):**
   - [ ] Status code: `405 Method Not Allowed`

---

### POST `/api/v1/tv-shows/{slug}/refresh`

**Note:** Follows the same pattern as TV Series refresh endpoint. Test all scenarios above with TV Shows slugs instead.

---

## 📝 Report Endpoints

### POST `/api/v1/tv-series/{slug}/report`

**Purpose:** Report an issue with TV series or its description.

#### Test Scenario 1: Report with All Fields

**Steps:**

1. **Request:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "INACCURATE",
       "message": "The description incorrectly states the first air date as 2009, but it was 2008",
       "suggested_fix": "Update first air date to 2008",
       "description_id": "550e8400-e29b-41d4-a716-446655440000"
     }' | jq
   ```

2. **Expected Response (201 Created):**
   ```json
   {
     "data": {
       "id": "660e8400-e29b-41d4-a716-446655440001",
       "tv_series_id": "...",
       "description_id": "550e8400-e29b-41d4-a716-446655440000",
       "type": "INACCURATE",
       "message": "The description incorrectly states...",
       "suggested_fix": "Update first air date to 2008",
       "status": "pending",
       "priority_score": 3.0,
       "created_at": "2025-12-28T10:30:00+00:00"
     }
   }
   ```

3. **Verification Checklist:**
   - [ ] Status code: `201 Created`
   - [ ] Report ID is UUID
   - [ ] `tv_series_id` matches the TV series
   - [ ] `description_id` matches (if provided)
   - [ ] `type` is valid enum value
   - [ ] `status` is `pending`
   - [ ] `priority_score` is calculated correctly
   - [ ] Report exists in database

#### Test Scenario 2: Report Without Description ID

**Steps:**

1. **Request:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "INAPPROPRIATE",
       "message": "The TV series description contains inappropriate content"
     }' | jq
   ```

2. **Expected Response (201 Created):**
   ```json
   {
     "data": {
       "id": "...",
       "tv_series_id": "...",
       "description_id": null,
       "type": "INAPPROPRIATE",
       "status": "pending",
       "priority_score": 5.0
     }
   }
   ```

3. **Verification:**
   - [ ] Status code: `201 Created`
   - [ ] `description_id` is `null`
   - [ ] Priority score is `5.0` (INAPPROPRIATE weight)

#### Test Scenario 3: Validation Errors

**Test 3a: Missing Required Field**

```bash
curl -X POST "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/report" \
  -H "Content-Type: application/json" \
  -d '{"type": "INACCURATE"}' | jq
```

**Expected:** `422 Unprocessable Entity` with error about missing `message`

**Test 3b: Invalid Report Type**

```bash
curl -X POST "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/report" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "INVALID_TYPE",
    "message": "Test message"
  }' | jq
```

**Expected:** `422 Unprocessable Entity` with error about invalid type

**Test 3c: Message Too Short**

```bash
curl -X POST "http://localhost:8000/api/v1/tv-series/breaking-bad-2008/report" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "INACCURATE",
    "message": "Short"
  }' | jq
```

**Expected:** `422 Unprocessable Entity` with error about minimum length (10 characters)

#### Test Scenario 4: Non-Existent TV Series

**Steps:**

1. **Request:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/tv-series/non-existent-series/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "INACCURATE",
       "message": "Test message with sufficient length"
     }' | jq
   ```

2. **Expected Response (404 Not Found):**
   ```json
   {
     "error": "TV series not found"
   }
   ```

3. **Verification:**
   - [ ] Status code: `404 Not Found`

#### Test Scenario 5: Priority Score Calculation

**Steps:**

1. **Create reports with different types:**
   ```bash
   # INACCURATE (weight: 3.0)
   curl -X POST ".../tv-series/test/report" \
     -d '{"type": "INACCURATE", "message": "Test message with sufficient length"}' | jq '.priority_score'
   # Expected: 3.0

   # INAPPROPRIATE (weight: 5.0)
   curl -X POST ".../tv-series/test/report" \
     -d '{"type": "INAPPROPRIATE", "message": "Test message with sufficient length"}' | jq '.priority_score'
   # Expected: 5.0

   # OTHER (weight: 1.0)
   curl -X POST ".../tv-series/test/report" \
     -d '{"type": "OTHER", "message": "Test message with sufficient length"}' | jq '.priority_score'
   # Expected: 1.0
   ```

2. **Verification:**
   - [ ] Priority scores match expected weights
   - [ ] Multiple reports of same type increase score

---

### POST `/api/v1/tv-shows/{slug}/report`

**Note:** Follows the same pattern as TV Series report endpoint. Test all scenarios above with TV Shows slugs instead.

---

## 🔍 Compare Endpoints

### GET `/api/v1/tv-series/compare`

**Purpose:** Compare two TV series to find common elements and differences.

#### Test Scenario 1: Compare Two Related TV Series

**Prerequisites:**
- Two TV series with common genres and/or people

**Steps:**

1. **Request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/compare?slug1=breaking-bad-2008&slug2=better-call-saul-2015" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (200 OK):**
   ```json
   {
     "tv_series1": {
       "id": "...",
       "slug": "breaking-bad-2008",
       "title": "Breaking Bad",
       "first_air_year": 2008
     },
     "tv_series2": {
       "id": "...",
       "slug": "better-call-saul-2015",
       "title": "Better Call Saul",
       "first_air_year": 2015
     },
     "comparison": {
       "common_genres": ["Crime", "Drama"],
       "common_people": [
         {
           "person": {
             "id": "...",
             "slug": "bryan-cranston",
             "name": "Bryan Cranston"
           },
           "roles_in_tv_series1": ["ACTOR"],
           "roles_in_tv_series2": ["PRODUCER"]
         }
       ],
       "year_difference": 7,
       "similarity_score": 0.85
     }
   }
   ```

3. **Verification Checklist:**
   - [ ] Status code: `200 OK`
   - [ ] Both TV series objects present with basic info
   - [ ] `common_genres` array lists shared genres
   - [ ] `common_people` array lists people who worked on both
   - [ ] Each person shows roles in both TV series
   - [ ] `year_difference` is absolute difference in first air years
   - [ ] `similarity_score` is between 0.0 and 1.0

#### Test Scenario 2: Compare Unrelated TV Series

**Steps:**

1. **Request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/compare?slug1=series1&slug2=series2" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (200 OK):**
   ```json
   {
     "tv_series1": {...},
     "tv_series2": {...},
     "comparison": {
       "common_genres": [],
       "common_people": [],
       "year_difference": 10,
       "similarity_score": 0.15
     }
   }
   ```

3. **Verification:**
   - [ ] Status code: `200 OK`
   - [ ] `common_genres` is empty array
   - [ ] `common_people` is empty array
   - [ ] `similarity_score` is low (close to 0.0)

#### Test Scenario 3: Missing Parameters

**Test 3a: Missing slug1**

```bash
curl -X GET "http://localhost:8000/api/v1/tv-series/compare?slug2=test" | jq
```

**Expected:** `422 Unprocessable Entity`

**Test 3b: Missing slug2**

```bash
curl -X GET "http://localhost:8000/api/v1/tv-series/compare?slug1=test" | jq
```

**Expected:** `422 Unprocessable Entity`

#### Test Scenario 4: Non-Existent TV Series

**Steps:**

1. **Request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/compare?slug1=non-existent-123&slug2=also-non-existent-456" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (404 Not Found):**
   ```json
   {
     "error": "One or both TV series not found"
   }
   ```

3. **Verification:**
   - [ ] Status code: `404 Not Found`

---

### GET `/api/v1/tv-shows/compare`

**Note:** Follows the same pattern as TV Series compare endpoint. Test all scenarios above with TV Shows slugs instead.

---

## 🔧 Admin Panel Integration

### GET `/api/v1/admin/reports?type=tv_series`

**Purpose:** List TV Series reports with filtering and sorting.

**Prerequisites:** Admin authentication (Basic Auth)

#### Test Scenario 1: List All TV Series Reports

**Steps:**

1. **Request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?type=tv_series" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (200 OK):**
   ```json
   {
     "data": [
       {
         "id": "...",
         "entity_type": "tv_series",
         "tv_series_id": "...",
         "description_id": "...",
         "type": "INACCURATE",
         "message": "...",
         "status": "pending",
         "priority_score": 3.0,
         "created_at": "..."
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

3. **Verification:**
   - [ ] Status code: `200 OK`
   - [ ] Only TV Series reports returned (`entity_type: "tv_series"`)
   - [ ] Reports sorted by priority_score desc, created_at desc
   - [ ] Pagination metadata present

#### Test Scenario 2: Filter by Status

**Steps:**

1. **Request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?type=tv_series&status=pending" \
     -u "admin:password" | jq
   ```

2. **Verification:**
   - [ ] Only pending reports returned
   - [ ] Status filter works correctly

#### Test Scenario 3: Filter by Priority

**Steps:**

1. **Request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/admin/reports?type=tv_series&priority=high" \
     -u "admin:password" | jq
   ```

2. **Verification:**
   - [ ] Only high-priority reports (score >= 3.0) returned

---

### POST `/api/v1/admin/reports/{id}/verify`

**Purpose:** Verify a report and trigger description regeneration.

#### Test Scenario 1: Verify TV Series Report

**Prerequisites:**
- Report exists with `description_id`
- Queue worker running

**Steps:**

1. **Request:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/admin/reports/{report_id}/verify" \
     -u "admin:password" \
     -H "Accept: application/json" | jq
   ```

2. **Expected Response (200 OK):**
   ```json
   {
     "id": "...",
     "entity_type": "tv_series",
     "tv_series_id": "...",
     "description_id": "...",
     "status": "verified",
     "verified_at": "2025-12-28T11:00:00+00:00"
   }
   ```

3. **Verification:**
   - [ ] Status code: `200 OK`
   - [ ] Status changed to `verified`
   - [ ] `verified_at` timestamp set
   - [ ] Regeneration job queued (check queue/logs)

4. **Check Queue:**
   ```bash
   # Verify RegenerateTvSeriesDescriptionJob is queued
   tail -f storage/logs/laravel.log | grep "RegenerateTvSeriesDescriptionJob"
   ```

---

## 📊 Test Scenarios Matrix

| Endpoint | Success Case | Error Cases | Edge Cases |
|----------|-------------|-------------|------------|
| Related | TV series with relationships | 404 not found | Empty relationships |
| Refresh | TV series with snapshot | 404 no snapshot, 404 not found | Wrong HTTP method |
| Report | All fields provided | 422 validation, 404 not found | Missing optional fields |
| Compare | Two related TV series | 422 missing params, 404 not found | Unrelated TV series |
| Admin List | Multiple reports | 401 unauthorized | Empty results |
| Admin Verify | Report with description_id | 404 not found, 401 unauthorized | Report without description_id |

---

## 🐛 Common Issues & Solutions

### Issue 1: Related Endpoint Returns Empty Array

**Symptoms:**
- `related_tv_series` is always empty
- `count` is always 0

**Possible Causes:**
1. Relationships not synced from TMDb
2. `SyncTvSeriesRelationshipsJob` hasn't run
3. TV series has no relationships in TMDb

**Solutions:**
```bash
# Check if relationships exist
docker compose exec php php artisan tinker
>>> \App\Models\TvSeriesRelationship::where('tv_series_id', 1)->count()

# Manually trigger sync (if snapshot exists)
>>> \App\Jobs\SyncTvSeriesRelationshipsJob::dispatch(1);

# Check queue worker
>>> php artisan horizon:status
```

### Issue 2: Refresh Returns 404 "No Snapshot"

**Symptoms:**
- Refresh endpoint returns 404 even though TV series exists

**Possible Causes:**
1. TV series doesn't have TMDb snapshot
2. TMDb snapshot is missing or invalid

**Solutions:**
```bash
# Check if snapshot exists
docker compose exec php php artisan tinker
>>> \App\Models\TvSeries::where('slug', 'breaking-bad-2008')->first()->tmdbSnapshot

# If snapshot is null, TV series was created manually without TMDb
# Need to create from TMDb first or add snapshot manually
```

### Issue 3: Report Priority Score Incorrect

**Symptoms:**
- Priority score doesn't match expected weight

**Possible Causes:**
1. Multiple reports of same type affecting calculation
2. Report type weight changed

**Solutions:**
```bash
# Check report type weights
grep -A 5 "INACCURATE\|INAPPROPRIATE\|OTHER" api/app/Enums/ReportType.php

# Check calculation logic
grep -A 10 "calculatePriorityScore" api/app/Services/TvSeriesReportService.php
```

### Issue 4: Compare Returns Low Similarity Score

**Symptoms:**
- Similarity score is very low even for related TV series

**Possible Causes:**
1. TV series don't share genres or people
2. Genres/people not loaded properly

**Solutions:**
```bash
# Check if TV series have genres/people
docker compose exec php php artisan tinker
>>> $ts1 = \App\Models\TvSeries::where('slug', 'series1')->first();
>>> $ts1->genres; // Should be array
>>> $ts1->people; // Should be collection
```

### Issue 5: Regeneration Job Not Queued After Verification

**Symptoms:**
- Report verified but no regeneration job in queue

**Possible Causes:**
1. `description_id` is null (general report, not description-specific)
2. Queue worker not running
3. Job dispatch failed

**Solutions:**
```bash
# Check report description_id
docker compose exec php php artisan tinker
>>> \App\Models\TvSeriesReport::find('{report_id}')->description_id

# Check queue worker
>>> php artisan horizon:status

# Check logs
>>> tail -f storage/logs/laravel.log | grep "RegenerateTvSeriesDescriptionJob"
```

---

## ✅ Test Checklist

### Pre-Testing Setup

- [ ] Database migrations applied
- [ ] Test data seeded (TV Series, TV Shows, relationships, descriptions)
- [ ] Queue worker running (Horizon or `queue:work`)
- [ ] Admin credentials configured
- [ ] TMDb snapshots exist for refresh tests

### Endpoint Testing

**Related Endpoints:**
- [ ] GET related with relationships
- [ ] GET related without relationships (empty)
- [ ] GET related for non-existent TV series/shows
- [ ] Filter by relationship type

**Refresh Endpoints:**
- [ ] POST refresh with snapshot
- [ ] POST refresh without snapshot (404)
- [ ] POST refresh for non-existent (404)
- [ ] GET refresh (405 Method Not Allowed)

**Report Endpoints:**
- [ ] POST report with all fields
- [ ] POST report without description_id
- [ ] POST report validation errors
- [ ] POST report for non-existent (404)
- [ ] Priority score calculation

**Compare Endpoints:**
- [ ] Compare related TV series/shows
- [ ] Compare unrelated TV series/shows
- [ ] Compare missing parameters (422)
- [ ] Compare non-existent (404)

**Admin Endpoints:**
- [ ] List TV Series reports
- [ ] List TV Show reports
- [ ] Filter by status
- [ ] Filter by priority
- [ ] Verify report
- [ ] Verify without authentication (401)

### Integration Testing

- [ ] End-to-end flow: Report → Admin Verify → Regeneration
- [ ] Multiple reports → Priority sorting
- [ ] Refresh → Updated metadata visible
- [ ] Related → Correct relationships shown
- [ ] Compare → Accurate similarity scores

---

## 📚 Related Documentation

- [Complete Manual Testing Guide](./MANUAL_TESTING_GUIDE.md) - Full testing guide for all endpoints
- [TMDb Verification Testing](./TESTING_TMDB_VERIFICATION_TV_SERIES_TV_SHOWS.md) - TMDb-specific testing
- [OpenAPI Specification](./openapi.yaml) - API specification with examples

---

**Last updated:** 2025-12-28  
**Version:** 1.0


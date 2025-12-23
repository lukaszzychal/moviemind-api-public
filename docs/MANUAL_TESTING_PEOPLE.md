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

## 📝 Person Reports

### Overview

Person Reports allow users to report errors or issues with person biographies. Reports are managed through:
- **Public endpoint:** `POST /api/v1/people/{slug}/report` - Submit a report
- **Admin endpoints:** `GET /api/v1/admin/reports` and `POST /api/v1/admin/reports/{id}/verify` - Manage reports

### Scenario 1: Submit Person Report

**Objective:** Verify that users can submit reports about person biographies.

**Steps:**

1. **Submit a report:**
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
   - [ ] `type` is one of: `FACTUAL_ERROR`, `GRAMMAR_ERROR`, `INAPPROPRIATE_CONTENT`, `OTHER`
   - [ ] `status` is `pending`
   - [ ] `priority_score` is calculated (based on report type and count)

4. **Test with bio_id (specific bio report):**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves-1964/report" \
     -H "Content-Type: application/json" \
     -H "Accept: application/json" \
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

6. **Test invalid enum:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves-1964/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "INVALID_TYPE",
       "message": "Test message"
     }' | jq
   ```
   - [ ] Status code: `422 Unprocessable Entity`
   - [ ] Error message indicates invalid `type` value

7. **Test message length validation:**
   ```bash
   # Too short (< 10 characters)
   curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves-1964/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "FACTUAL_ERROR",
       "message": "Short"
     }' | jq
   ```
   - [ ] Status code: `422 Unprocessable Entity`
   - [ ] Error indicates minimum 10 characters required

8. **Test non-existent person:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/people/non-existent-person-9999/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "FACTUAL_ERROR",
       "message": "This person does not exist"
     }' | jq
   ```
   - [ ] Status code: `404 Not Found`
   - [ ] Error message: "Person not found"

### Scenario 2: Priority Score Calculation

**Objective:** Verify that priority scores are calculated correctly based on report count and type.

**Steps:**

1. **Submit first report:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves-1964/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "FACTUAL_ERROR",
       "message": "First report about factual error"
     }' | jq '.data.priority_score'
   ```
   - [ ] Priority score: `3.0` (FACTUAL_ERROR weight = 3.0, count = 1)

2. **Submit second report of same type:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves-1964/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "FACTUAL_ERROR",
       "message": "Second report about factual error"
     }' | jq '.data.priority_score'
   ```
   - [ ] Priority score: `6.0` (FACTUAL_ERROR weight = 3.0, count = 2)

3. **Submit different type report:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/people/keanu-reeves-1964/report" \
     -H "Content-Type: application/json" \
     -d '{
       "type": "GRAMMAR_ERROR",
       "message": "Grammar error report"
     }' | jq '.data.priority_score'
   ```
   - [ ] Priority score: `1.0` (GRAMMAR_ERROR weight = 1.0, count = 1, separate from FACTUAL_ERROR)

### Scenario 3: Admin - List Person Reports

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
         "suggested_fix": "Update birth_date",
         "status": "pending",
         "priority_score": 3.0,
         "verified_by": null,
         "verified_at": null,
         "resolved_at": null,
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

### Scenario 4: Admin - Verify Person Report

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
   ```bash
   # Check Laravel Horizon dashboard or logs
   # Job: RegeneratePersonBioJob should be queued
   ```
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
   - [ ] Error message: "Report not found"

### Scenario 5: Bio Regeneration After Verification

**Objective:** Verify that bio regeneration works after report verification.

**Prerequisites:**
- Report must be verified (use Scenario 4)
- Queue worker must process the job
- OpenAI API key must be configured (or use mock mode)

**Steps:**

1. **Verify the report (see Scenario 4)**

2. **Wait for job to process (or manually trigger):**
   ```bash
   # If using queue:work, job will process automatically
   # Or check Horizon dashboard for job status
   ```

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
   - [ ] New bio has `origin = 'GENERATED'`

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

7. **Get updated person data:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/people/{slug}" \
     -H "Accept: application/json" | jq '.default_bio'
   ```
   - [ ] New bio is returned as `default_bio`
   - [ ] Bio text is updated (regenerated)

## 🗄️ Database Verification

### Check Person Reports

```sql
-- List all person reports
SELECT 
    pr.id,
    pr.person_id,
    pr.bio_id,
    pr.type,
    pr.status,
    pr.priority_score,
    pr.created_at,
    p.name as person_name,
    p.slug as person_slug
FROM person_reports pr
JOIN people p ON pr.person_id = p.id
ORDER BY pr.priority_score DESC, pr.created_at DESC;

-- Check reports for specific person
SELECT * FROM person_reports 
WHERE person_id = (SELECT id FROM people WHERE slug = 'keanu-reeves-1964')
ORDER BY created_at DESC;

-- Check priority score calculation
SELECT 
    type,
    status,
    COUNT(*) as count,
    AVG(priority_score) as avg_priority
FROM person_reports
WHERE person_id = (SELECT id FROM people WHERE slug = 'keanu-reeves-1964')
GROUP BY type, status;
```

## 🐛 Troubleshooting

### Problem: 404 for existing person

**Solution:**
- Check slug format (exact match required)
- Verify person exists in database
- Clear cache: `php artisan cache:clear`

### Problem: Report not created

**Solution:**
- Verify person exists: `SELECT * FROM people WHERE slug = '{slug}';`
- Check validation errors in response
- Verify rate limiting is not blocking requests

### Problem: Priority score not calculated

**Solution:**
- Check `PersonReportService::calculatePriorityScore()` logic
- Verify report count: `SELECT COUNT(*) FROM person_reports WHERE person_id = '{person_id}' AND type = '{type}' AND status = 'pending';`
- Check report type weights (FACTUAL_ERROR = 3.0, GRAMMAR_ERROR = 1.0, etc.)

### Problem: Bio regeneration not working

**Solution:**
- Verify queue worker is running: `php artisan queue:work`
- Check job status in Horizon dashboard
- Verify OpenAI API key is configured (or mock mode is enabled)
- Check logs: `tail -f storage/logs/laravel.log`
- Verify `bio_id` is not null in the report

### Problem: Admin endpoints return 401

**Solution:**
- Verify Basic Auth credentials are correct
- Check `ADMIN_AUTH_BYPASS_ENVS` environment variable (for local testing)
- Verify middleware configuration in `routes/api.php`

---

**Last updated:** 2025-12-23  
**Version:** 2.0 (Added Person Reports section)


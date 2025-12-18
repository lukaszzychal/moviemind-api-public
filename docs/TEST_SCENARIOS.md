# Test Scenarios Documentation

This document describes the main test scenarios for MovieMind API. Each scenario follows the **Given-When-Then** format for clarity.

## Table of Contents

1. [Movie Metadata Synchronization](#movie-metadata-synchronization)
2. [Movie Description Selection](#movie-description-selection)
3. [TMDB ID Hiding](#tmdb-id-hiding)
4. [Person Bio Selection](#person-bio-selection)

---

## Movie Metadata Synchronization

### Scenario 1: Movie creation triggers metadata sync job

**Given:** A movie is created from TMDB data via `TmdbMovieCreationService`  
**When:** The service creates a new movie  
**Then:** `SyncMovieMetadataJob` should be dispatched with the movie ID

**Test:** `MovieMetadataSyncTest::test_movie_creation_triggers_metadata_sync_job()`

**Implementation:**
```php
// Given: Movie creation service
$service = app(\App\Services\TmdbMovieCreationService::class);

// When: Create movie from TMDB data
$movie = $service->createFromTmdb([...], $slug);

// Then: Job should be dispatched
Queue::assertPushed(\App\Jobs\SyncMovieMetadataJob::class);
```

---

### Scenario 2: Sync job synchronizes actors and crew

**Given:** A movie exists with TMDB snapshot containing credits (cast and crew)  
**When:** `SyncMovieMetadataJob` runs  
**Then:** 
- Actors should be created and attached to the movie with character names
- Crew members (directors, writers, producers) should be created and attached with roles
- People should be linked by `tmdb_id` when available

**Test:** `MovieMetadataSyncTest::test_sync_movie_metadata_job_synchronizes_actors_and_crew()`

**Example:**
- **Given:** Movie "The Matrix" with TMDB snapshot containing:
  - Cast: Keanu Reeves (Neo), Laurence Fishburne (Morpheus), Carrie-Anne Moss (Trinity)
  - Crew: Lana Wachowski (Director, Writer), Lilly Wachowski (Director)
- **When:** SyncMovieMetadataJob runs
- **Then:** 
  - 5 people should be created (3 actors + 2 crew)
  - Keanu Reeves should have character_name "Neo" and role "ACTOR"
  - Lana Wachowski should have both "DIRECTOR" and "WRITER" roles

---

### Scenario 3: Refresh endpoint does NOT re-sync actors

**Given:** A movie exists with synchronized actors and crew  
**When:** The `/refresh` endpoint is called  
**Then:** Only core movie metadata (title, release_year, director) should be updated, but actors/crew should NOT be re-synchronized

**Test:** `MovieMetadataSyncTest::test_refresh_endpoint_does_not_sync_actors()`

**Rationale:** The `/refresh` endpoint is for updating core metadata only. Actors/crew are synced once during initial creation via `SyncMovieMetadataJob`.

---

### Scenario 4: Handle duplicate persons by tmdb_id

**Given:** Multiple movies reference the same person (same `tmdb_id`)  
**When:** `SyncMovieMetadataJob` runs for each movie  
**Then:** Only one Person record should be created, and both movies should reference the same Person

**Test:** `MovieMetadataSyncTest::test_handles_duplicate_persons_by_tmdb_id()`

---

### Scenario 5: Handle empty cast and crew arrays

**Given:** A movie has TMDB snapshot with empty `cast` and `crew` arrays  
**When:** `SyncMovieMetadataJob` runs  
**Then:** The job should complete successfully without errors, and no people should be attached

**Test:** `MovieMetadataSyncTest::test_handles_empty_cast_and_crew_arrays()`

---

## Movie Description Selection

### Scenario: Select specific description by ID

**Given:** A movie exists with multiple descriptions (different `context_tag` values)  
**When:** A GET request is made with `?description_id={id}` parameter  
**Then:** 
- The response should contain `selected_description` with the requested description
- The response should contain `default_description` with the baseline description
- The response should be cached with a specific cache key

**Test:** `MoviesApiTest::test_show_movie_can_select_specific_description()`

**Example:**
- **Given:** Movie "The Matrix" has:
  - Default description (context_tag: DEFAULT)
  - Critical description (context_tag: critical)
- **When:** GET `/api/v1/movies/the-matrix-1999?description_id={critical_id}`
- **Then:** Response contains both `selected_description` (critical) and `default_description` (DEFAULT)

---

## TMDB ID Hiding

### Scenario: TMDB IDs are not exposed in API responses

**Given:** Movies and People have `tmdb_id` stored in the database  
**When:** API endpoints return movie or person data  
**Then:** The `tmdb_id` field should NOT be present in the JSON response

**Tests:** 
- `TmdbIdHiddenTest::test_movie_api_responses_do_not_contain_tmdb_id()`
- `TmdbIdHiddenTest::test_person_api_responses_do_not_contain_tmdb_id()`
- `TmdbIdHiddenTest::test_search_results_do_not_contain_tmdb_id()`

**Affected Endpoints:**
- `GET /api/v1/movies`
- `GET /api/v1/movies/{slug}`
- `GET /api/v1/movies/search`
- `GET /api/v1/people`
- `GET /api/v1/people/{slug}`
- All nested responses (e.g., `people` array in movie response)

---

## Person Bio Selection

### Scenario: Select specific bio by ID

**Given:** A person exists with multiple bios (different `context_tag` values)  
**When:** A GET request is made with `?bio_id={id}` parameter  
**Then:** 
- The response should contain `selected_bio` with the requested bio
- The response should contain `default_bio` with the baseline bio
- The response should be cached with a specific cache key

**Test:** `PeopleApiTest::test_show_person_can_select_specific_bio()`

---

## Test Helper Usage

For common test scenarios, use the test helpers in `tests/Feature/Helpers/`:

```php
use Tests\Feature\Helpers\MovieTestHelper;
use Tests\Feature\Helpers\PersonTestHelper;

// Create movie with actors
$movie = MovieTestHelper::createMovieWithActors([
    ['name' => 'Keanu Reeves', 'character' => 'Neo', 'tmdb_id' => 6384],
    ['name' => 'Laurence Fishburne', 'character' => 'Morpheus', 'tmdb_id' => 2975],
]);

// Create person with bios
$person = PersonTestHelper::createPersonWithBios([
    ['locale' => 'en-US', 'context_tag' => 'DEFAULT', 'text' => 'Default bio'],
    ['locale' => 'en-US', 'context_tag' => 'critical', 'text' => 'Critical bio'],
]);
```

---

## Last Updated

2025-12-18


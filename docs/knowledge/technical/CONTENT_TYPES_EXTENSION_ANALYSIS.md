# Content Types Extension Analysis

> **Created:** 2025-01-27  
> **Status:** Analysis Document  
> **Category:** technical  
> **Task:** TASK-042

## Executive Summary

This document analyzes the current MovieMind API system structure and provides recommendations for extending it with new content types (Documentaries, Short Films, Web Series, Podcasts, Books, Music Albums). The analysis covers database impact, API design, job patterns, and proposes common interfaces and refactoring opportunities to support scalable extension.

### ⚠️ Critical Distinction: Content Types vs. Genres/Filters

**Important Finding:** Not all "extensions" should be separate content types. Some are better implemented as genres/filters within existing types:

- **Documentaries & Short Films:** These are **genres/filters within Movie**, not separate content types. Implementation: Add fields (`subject`, `runtime`, `festival`) to `movies` table + filtering (2-3 hours each).
- **Web Series:** These might be **TvSeries with platform field**, not separate content type. Implementation: Add `platform` and `distribution_type` fields to `tv_series` table + filtering (2-3 hours).
- **Podcasts, Books, Music Albums:** These ARE **true separate content types** with fundamentally different structure (4-6 weeks each).

**Impact:** This distinction reduces implementation time from 7-11 months to 6-8 months by avoiding unnecessary table/model/controller/job proliferation for Documentaries, Short Films, and potentially Web Series.

### Key Findings

1. **Current Structure:** The system supports Movie, Person, TvSeries, and TvShow entities with similar patterns (models, repositories, controllers, jobs, descriptions).

2. **Common Patterns Identified:**
   - All content types use UUIDv7 primary keys
   - All have slug-based identification with year disambiguation
   - All support AI-generated descriptions with locale and context_tag
   - All have relationships with Person (actors, directors, hosts, etc.)
   - All follow similar job patterns (RealGenerate*Job, MockGenerate*Job)
   - All use similar controller patterns (index, show, search, related)

3. **Important Distinction: Content Type vs. Genre/Filter**
   - **Content Types:** Fundamentally different entities (Movie vs. TvSeries vs. Person)
   - **Genres/Filters:** Variations within the same content type (Documentary vs. Action within Movies)
   - **Key Insight:** Documentaries and Short Films are **genres/filters within Movie**, not separate content types
   - **Similar Consideration:** Web Series might be TvSeries with `platform` field rather than separate content type

4. **Recommended Approach:**
   - **Documentaries & Short Films:** Add fields to Movie (subject, runtime, festival) + genre filtering - **NOT separate content types**
   - **Web Series:** Add fields to TvSeries (platform, distribution_type) - **NOT separate content type** (unless fundamentally different structure)
   - **Podcasts, Books, Music Albums:** These ARE separate content types (fundamentally different from movies/series)
   - **Medium-term:** Introduce common interfaces/traits to reduce duplication
   - **Long-term:** Consider polymorphic relationships for descriptions and people if 10+ content types are needed

5. **Priority Extensions (Revised):**
   - **High Priority:** Documentaries & Short Films as Movie filters (2-3 hours each) - **RECOMMENDED APPROACH**
   - **Medium Priority:** Web Series as TvSeries field extension (2-3 hours) - **RECOMMENDED APPROACH**
   - **Low Priority:** Podcasts, Books, Music Albums as separate content types (40-50 hours each) - **ONLY TRUE EXTENSIONS**

---

## Current System Analysis

### Entity Types Overview

The system currently supports four main entity types:

1. **Movie** - Feature films with release_year, director, genres
2. **Person** - People (actors, directors, etc.) with birth_date, birthplace
3. **TvSeries** - TV series with first_air_date, last_air_date, seasons, episodes
4. **TvShow** - TV shows (talk shows, reality, etc.) with show_type

### Common Fields Across Content Types

All content types share these common fields:

| Field | Type | Purpose |
|-------|------|---------|
| `id` | UUIDv7 | Primary key |
| `slug` | string | Unique identifier (title-year format) |
| `title` | string | Display title |
| `default_description_id` | UUID | Reference to default AI description |
| `tmdb_id` | integer | TMDb API integration (nullable) |
| `genres` | JSON array | Content genres |
| `created_at` | timestamp | Record creation time |
| `updated_at` | timestamp | Record update time |

### Unique Fields Per Entity Type

#### Movie
- `release_year` (smallInteger) - Year of release
- `director` (string) - Director name(s)

#### Person
- `name` (string) - Full name
- `birth_date` (date) - Birth date
- `birthplace` (string) - Birth location
- `default_bio_id` (UUID) - Reference to default biography

#### TvSeries
- `first_air_date` (date) - First episode air date
- `last_air_date` (date) - Last episode air date (nullable)
- `number_of_seasons` (smallInteger) - Total seasons
- `number_of_episodes` (integer) - Total episodes

#### TvShow
- `first_air_date` (date) - First episode air date
- `last_air_date` (date) - Last episode air date (nullable)
- `number_of_seasons` (smallInteger) - Total seasons
- `number_of_episodes` (integer) - Total episodes
- `show_type` (string) - TALK_SHOW, REALITY, NEWS, DOCUMENTARY, VARIETY, GAME_SHOW

### Relationship Patterns

All content types (except Person) have similar relationship patterns:

1. **Descriptions:** `HasMany` relationship to `*_descriptions` table
   - Movie → MovieDescription
   - TvSeries → TvSeriesDescription
   - TvShow → TvShowDescription
   - Person → PersonBio (different naming, same pattern)

2. **People:** `BelongsToMany` relationship via pivot tables
   - Movie → `movie_person` → Person
   - TvSeries → `tv_series_person` → Person
   - TvShow → `tv_show_person` → Person
   - Pivot fields: `role`, `character_name`, `job`, `billing_order`

3. **Related Content:** `BelongsToMany` relationship via `*_relationships` tables
   - Movie → `movie_relationships` → Movie (sequels, prequels, remakes)
   - TvSeries → `tv_series_relationships` → TvSeries
   - TvShow → `tv_show_relationships` → TvShow
   - Pivot fields: `relationship_type`, `order`

### Slug Generation Patterns

All entities use similar slug generation with year-based disambiguation:

**Movie:**
- Format: `title-slug-YYYY` or `title-slug-YYYY-director-slug` or `title-slug-YYYY-N`
- Example: `the-matrix-1999`, `the-prestige-2006-christopher-nolan`, `heat-1995-2`

**Person:**
- Format: `name-slug` or `name-slug-YYYY` or `name-slug-YYYY-birthplace-slug` or `name-slug-YYYY-N`
- Example: `keanu-reeves`, `keanu-reeves-1964`, `john-smith-1980-london`

**TvSeries/TvShow:**
- Format: `title-slug-YYYY` or `title-slug-YYYY-N`
- Example: `breaking-bad-2008`, `the-office-2005-2`

### Description Storage Patterns

All content types store AI-generated descriptions in separate tables:

**Common Description Fields:**
- `id` (UUID) - Primary key
- `*_id` (UUID, FK) - Foreign key to content entity
- `locale` (string, 10) - Locale code (pl-PL, en-US)
- `text` (text) - AI-generated description text
- `context_tag` (string, 64) - modern, critical, humorous, etc.
- `origin` (string, 32) - GENERATED, TRANSLATED
- `ai_model` (string, 64) - gpt-4o-mini, etc.
- `created_at`, `updated_at` (timestamps)
- Unique constraint: `(*_id, locale, context_tag)`

**Differences:**
- MovieDescription: No versioning fields
- TvSeriesDescription/TvShowDescription: Include `version_number` and `archived_at` (versioning support, currently unused per ADR)

### Job Patterns

All generation jobs follow the same structure:

**Common Job Properties:**
- `slug` (string) - Entity slug
- `jobId` (string) - Unique job identifier
- `existing*Id` (string|null) - Existing entity ID if found
- `baselineDescriptionId` (string|null) - Baseline description for locking
- `locale` (string|null) - Target locale
- `contextTag` (string|null) - Context tag
- `tmdbData` (array|null) - TMDb metadata

**Common Job Methods:**
- `handle(OpenAiClientInterface $openAiClient)` - Main execution
- `create*Record()` - Create new entity with AI generation
- `refreshExisting*()` - Refresh existing entity description
- `promoteDefaultIfEligible()` - Set default description if eligible
- `invalidate*Caches()` - Clear cache after generation
- `updateCache()` - Update job status cache

**Job Flow:**
1. Check for existing entity
2. If exists: refresh description
3. If not exists: create entity + description via AI
4. Promote default description if eligible
5. Invalidate caches
6. Update job status

### Controller Patterns

All controllers follow RESTful patterns:

**Common Methods:**
- `index(Request $request)` - List/search entities
- `show(Request $request, string $slug)` - Get single entity
- `search(*Request $request)` - Search with filters
- `related(Request $request, string $slug)` - Get related entities
- `handleBulkRetrieve(Request $request)` - Bulk retrieve by slugs

**Common Dependencies:**
- Repository (data access)
- ResponseFormatter (response formatting)
- HateoasService (HATEOAS links)
- SearchService (search logic)
- RetrievalService (retrieval logic)
- ReportService (reporting)
- ComparisonService (comparison)

### Repository Patterns

All repositories provide similar methods:

**Common Methods:**
- `search*(?string $query, int $limit)` - Search entities
- `findBySlugWithRelations(string $slug)` - Find with all relations
- `findBySlugForJob(string $slug, ?string $existingId)` - Find for job (lighter relations)
- `findAllByTitleSlug(string $baseSlug)` - Find all with same title (disambiguation)
- `findBySlugs(array $slugs, array $include)` - Bulk find by slugs

**Common Patterns:**
- Slug parsing for ambiguous matches
- Fallback to title-only search if slug doesn't contain year
- Order by date (release_year, first_air_date, birth_date) for ambiguous matches
- Eager loading of relations based on `include` parameter

---

## Potential Extensions

### ⚠️ Important Note: Content Type vs. Genre/Filter Distinction

Before analyzing potential extensions, it's crucial to distinguish between:
- **Content Types:** Fundamentally different entities (Movie vs. TvSeries vs. Person)
- **Genres/Filters:** Variations within the same content type (Documentary vs. Action vs. Comedy within Movies)

**Key Question:** Should "Documentaries" and "Short Films" be:
1. **Separate content types** (new tables, models, controllers) - Option A
2. **Genres/filters within Movie** (use existing Movie table with genre filtering) - Option B

**Analysis Required:** We need to compare both approaches objectively to determine which is better for each extension.

**Similar Consideration:** Web Series might be TvSeries with `platform` field (online distribution) rather than a separate content type.

---

### 1. Documentaries

**Description:** Non-fiction films. Need to analyze whether they should be a separate content type or a genre/filter within Movie.

#### Option A: Separate Content Type

**Approach:** Create new `documentaries` table with separate models, controllers, repositories, jobs.

**Required Fields:**
- **Shared:** id, slug, title, genres, default_description_id, tmdb_id
- **Unique:** release_year, director, subject (string) - main topic/subject
- **Optional:** runtime (integer) - duration in minutes, distributor (string)

**Relationships:**
- **People:** Directors, producers, narrators (via `documentary_person` pivot)
- **Descriptions:** Same pattern as Movie (via `documentary_descriptions`)
- **Related:** Other documentaries on similar subjects (via `documentary_relationships`)

**Slug Generation:**
- Format: `title-slug-YYYY` or `title-slug-YYYY-director-slug` or `title-slug-YYYY-N`
- Same as Movie (documentaries are movies, just different genre focus)

**TMDb Compatibility:**
- ✅ Full compatibility (TMDb has documentary movies)
- Can use same `TmdbVerificationService`

**AI Prompt Considerations:**
- Focus on factual accuracy
- Emphasize subject matter and educational value
- Different context tags: informative, analytical, historical

**Implementation:**
- 4 new tables (documentaries, documentary_descriptions, documentary_person, documentary_relationships)
- New model: Documentary
- New controller: DocumentaryController
- New repository: DocumentaryRepository
- New jobs: RealGenerateDocumentaryJob, MockGenerateDocumentaryJob
- New action: QueueDocumentaryGenerationAction
- New services: DocumentarySearchService, DocumentaryRetrievalService, etc.
- Update GenerateController to handle DOCUMENTARY entity type

**Estimated Time:** 2-3 weeks (27-38 hours)

**Pros:**
- ✅ Clear separation of concerns
- ✅ Type-safe queries (can't accidentally mix documentaries with fiction movies)
- ✅ Independent API endpoints (`/api/v1/documentaries`)
- ✅ Can have documentary-specific business logic
- ✅ Easier to add documentary-specific features later
- ✅ Clearer data model (documentaries are explicitly separate)

**Cons:**
- ❌ Table proliferation (4 new tables)
- ❌ Code duplication (similar to Movie structure)
- ❌ Harder cross-genre queries (e.g., "all films by director X" requires union query)
- ❌ More maintenance (changes to Movie don't automatically apply to Documentary)
- ❌ More complex migrations
- ❌ Duplicate API endpoints (similar to movies endpoints)

---

#### Option B: Genre/Filter Approach

**Approach:** Use existing `Movie` table, add `subject` field, filter by genre "Documentary".

**Required Changes:**
- Add `subject` field (string, nullable) to `movies` table
- Update Movie model to include `subject` in fillable
- Use existing `genres` array/relation to mark as "Documentary"
- Add filtering in MovieController: `GET /api/v1/movies?genre=documentary` or `GET /api/v1/movies?type=documentary`
- Update AI prompts to handle documentary genre differently (check if genre contains "Documentary")

**Implementation:**
- 1 migration: add `subject` field to `movies` table
- Update Movie model (add `subject` to fillable)
- Update MovieController (add genre/type filtering)
- Update AI prompts (check for documentary genre)
- No new tables, models, controllers, or jobs needed

**Estimated Time:** 2-3 hours

**Pros:**
- ✅ No table proliferation (reuses existing Movie table)
- ✅ No code duplication (reuses existing Movie code)
- ✅ Easier cross-genre queries (e.g., "all films by director X" works naturally)
- ✅ Less maintenance (changes to Movie apply to all films)
- ✅ Simpler migrations (just add field)
- ✅ Consistent API (all films use `/api/v1/movies`)
- ✅ All films in one place (easier reporting, analytics)

**Cons:**
- ❌ Less type safety (documentaries mixed with fiction movies in same table)
- ❌ No independent API endpoints (must use `/api/v1/movies?genre=documentary`)
- ❌ Harder to add documentary-specific business logic (need conditional checks)
- ❌ Less clear data model (documentaries not explicitly separate)
- ❌ Potential for confusion (is a documentary a movie? depends on perspective)

---

#### Comparison Analysis

| Aspect | Option A: Separate Type | Option B: Genre/Filter |
|--------|------------------------|------------------------|
| **Implementation Time** | 2-3 weeks (27-38 hours) | 2-3 hours |
| **Table Count** | +4 tables | +0 tables (add field) |
| **Code Duplication** | High (duplicate Movie structure) | Low (reuse Movie code) |
| **Type Safety** | ✅ High (separate types) | ⚠️ Medium (same type, filtered) |
| **Cross-Genre Queries** | ❌ Hard (union queries) | ✅ Easy (single table) |
| **Maintenance** | ❌ High (separate codebase) | ✅ Low (shared codebase) |
| **API Clarity** | ✅ Clear (`/documentaries`) | ⚠️ Less clear (`/movies?genre=documentary`) |
| **Business Logic** | ✅ Easy (separate classes) | ❌ Harder (conditional checks) |
| **Data Model Clarity** | ✅ Clear separation | ⚠️ Less clear (mixed in table) |
| **Scalability** | ⚠️ Medium (more tables) | ✅ High (single table) |

**Key Question:** Are documentaries fundamentally different from movies, or just a genre of movies?

**Analysis:**
- Documentaries share the same structure as movies: title, release_year, director, runtime, genres
- The only unique field is `subject` (main topic) - but this could be optional metadata
- Documentaries are movies in TMDb (same API, same structure)
- Documentaries can be filtered by genre "Documentary" in existing Movie table

**Decision Criteria:**

**Choose Option A (Separate Content Type) if:**
- Documentaries need fundamentally different business logic that can't be handled with conditional checks
- Documentaries need independent API endpoints for clear separation
- Documentaries need different data model structure (not just additional fields)
- Type safety is critical (can't risk mixing documentaries with fiction movies)
- You need documentary-specific features that would complicate Movie model

**Choose Option B (Genre/Filter Approach) if:**
- Documentaries are structurally the same as movies (same fields, same relationships)
- Simplicity and maintainability are priorities
- Cross-genre queries are important (e.g., "all films by director X")
- You want to avoid code duplication and table proliferation
- Documentary-specific fields can be optional metadata (`subject` field)

**Conclusion:** Based on the analysis, **Option B appears more appropriate** for most use cases because documentaries ARE movies (same structure, same TMDb API). However, the final decision should be based on specific business requirements and whether documentaries need fundamentally different handling.

---

### 2. Short Films

**Description:** Films with runtime < 40 minutes. Need to analyze whether they should be a separate content type or a filter within Movie.

#### Option A: Separate Content Type

**Approach:** Create new `short_films` table with separate models, controllers, repositories, jobs.

**Required Fields:**
- **Shared:** id, slug, title, genres, default_description_id, tmdb_id
- **Unique:** release_year, director, runtime (integer, required) - must be < 40 minutes
- **Optional:** festival (string) - film festival where premiered, distributor (string)

**Relationships:**
- **People:** Directors, actors (via `short_film_person` pivot)
- **Descriptions:** Same pattern as Movie (via `short_film_descriptions`)
- **Related:** Other short films by same director (via `short_film_relationships`)

**Slug Generation:**
- Format: `title-slug-YYYY` or `title-slug-YYYY-director-slug` or `title-slug-YYYY-N`
- Same as Movie

**TMDb Compatibility:**
- ✅ Full compatibility (TMDb has short films)
- Can use same `TmdbVerificationService`

**AI Prompt Considerations:**
- Emphasize brevity and concise storytelling
- Mention runtime in description
- Different context tags: concise, experimental, festival

**Implementation:**
- 4 new tables (short_films, short_film_descriptions, short_film_person, short_film_relationships)
- New model: ShortFilm
- New controller: ShortFilmController
- New repository: ShortFilmRepository
- New jobs: RealGenerateShortFilmJob, MockGenerateShortFilmJob
- New action: QueueShortFilmGenerationAction
- New services: ShortFilmSearchService, ShortFilmRetrievalService, etc.
- Update GenerateController to handle SHORT_FILM entity type

**Estimated Time:** 2-3 weeks (27-38 hours)

**Pros:**
- ✅ Clear separation (short films explicitly separate from feature films)
- ✅ Type-safe queries (can't accidentally mix short films with feature films)
- ✅ Independent API endpoints (`/api/v1/short-films`)
- ✅ Can enforce runtime < 40 minutes at database level
- ✅ Can have short film-specific business logic
- ✅ Clearer data model

**Cons:**
- ❌ Table proliferation (4 new tables)
- ❌ Code duplication (similar to Movie structure)
- ❌ Harder cross-type queries (e.g., "all films by director X" requires union query)
- ❌ More maintenance (changes to Movie don't automatically apply)
- ❌ More complex migrations
- ❌ Runtime is just a number - why separate table for that?

---

#### Option B: Filter Approach

**Approach:** Use existing `Movie` table, add `runtime` and `festival` fields, filter by runtime < 40.

**Required Changes:**
- Add `runtime` field (integer, nullable) to `movies` table
- Add `festival` field (string, nullable) to `movies` table
- Update Movie model to include `runtime` and `festival` in fillable
- Add validation: runtime must be < 40 minutes for short films (optional, can be enforced in business logic)
- Add filtering in MovieController: `GET /api/v1/movies?max_runtime=40` or `GET /api/v1/movies?type=short_film`
- Can combine with genre filtering: `Movie::where('runtime', '<', 40)->whereJsonContains('genres', 'Short Film')`

**Implementation:**
- 1 migration: add `runtime` and `festival` fields to `movies` table
- Update Movie model (add fields to fillable)
- Update MovieController (add runtime/type filtering)
- Update AI prompts (check for runtime < 40 or genre "Short Film")
- No new tables, models, controllers, or jobs needed

**Estimated Time:** 2-3 hours

**Pros:**
- ✅ No table proliferation (reuses existing Movie table)
- ✅ No code duplication (reuses existing Movie code)
- ✅ Easier cross-type queries (e.g., "all films by director X" works naturally)
- ✅ Less maintenance (changes to Movie apply to all films)
- ✅ Simpler migrations (just add fields)
- ✅ Consistent API (all films use `/api/v1/movies`)
- ✅ Runtime field useful for ALL films (not just short films)
- ✅ Can filter by any runtime range (not just < 40)

**Cons:**
- ❌ Less type safety (short films mixed with feature films in same table)
- ❌ No independent API endpoints (must use `/api/v1/movies?max_runtime=40`)
- ❌ Harder to enforce runtime < 40 at database level (need application-level validation)
- ❌ Less clear data model (short films not explicitly separate)
- ❌ Potential for confusion (is a short film a movie? yes, but runtime-based distinction)

---

#### Comparison Analysis

| Aspect | Option A: Separate Type | Option B: Filter Approach |
|--------|------------------------|---------------------------|
| **Implementation Time** | 2-3 weeks (27-38 hours) | 2-3 hours |
| **Table Count** | +4 tables | +0 tables (add fields) |
| **Code Duplication** | High (duplicate Movie structure) | Low (reuse Movie code) |
| **Type Safety** | ✅ High (separate types) | ⚠️ Medium (same type, filtered) |
| **Cross-Type Queries** | ❌ Hard (union queries) | ✅ Easy (single table) |
| **Maintenance** | ❌ High (separate codebase) | ✅ Low (shared codebase) |
| **API Clarity** | ✅ Clear (`/short-films`) | ⚠️ Less clear (`/movies?max_runtime=40`) |
| **Runtime Enforcement** | ✅ Database level (required field) | ⚠️ Application level (validation) |
| **Runtime Field Utility** | ❌ Only for short films | ✅ Useful for all films |
| **Data Model Clarity** | ✅ Clear separation | ⚠️ Less clear (mixed in table) |

**Key Question:** Is runtime < 40 minutes a fundamental difference that requires separate content type, or just a filter criterion?

**Analysis:**
- Short films share the same structure as movies: title, release_year, director, genres
- The only difference is runtime < 40 minutes - this is a filter criterion, not a structural difference
- Short films are movies in TMDb (same API, same structure)
- Runtime is useful metadata for ALL films (not just short films)
- The distinction is quantitative (runtime number), not qualitative (different structure)

**Decision Criteria:**

**Choose Option A (Separate Content Type) if:**
- Short films need fundamentally different business logic that can't be handled with conditional checks
- Short films need independent API endpoints for clear separation
- Runtime < 40 minutes must be enforced at database level (required field)
- Type safety is critical (can't risk mixing short films with feature films)
- You need short film-specific features that would complicate Movie model

**Choose Option B (Filter Approach) if:**
- Short films are structurally the same as movies (same fields, same relationships)
- Runtime is just metadata (useful for all films, not just short films)
- Simplicity and maintainability are priorities
- Cross-type queries are important (e.g., "all films by director X")
- You want to avoid code duplication and table proliferation
- Runtime validation can be handled at application level

**Conclusion:** Based on the analysis, **Option B appears more appropriate** for most use cases because short films ARE movies (same structure, same TMDb API) and runtime is just a number. However, the final decision should be based on specific business requirements and whether short films need fundamentally different handling.

---

### 3. Web Series

**Description:** Series distributed online (YouTube, Netflix, etc.) rather than traditional TV. Need to analyze whether they should be a separate content type or TvSeries with platform field.

#### Option A: Separate Content Type

**Approach:** Create new `web_series` table with separate models, controllers, repositories, jobs.

**Required Fields:**
- **Shared:** id, slug, title, genres, default_description_id, tmdb_id
- **Unique:** first_air_date, last_air_date, number_of_seasons, number_of_episodes, platform (string) - YouTube, Netflix, etc.
- **Optional:** average_episode_runtime (integer) - average minutes per episode, network (string) - if applicable

**Relationships:**
- **People:** Creators, actors, hosts (via `web_series_person` pivot)
- **Descriptions:** Same pattern as TvSeries (via `web_series_descriptions`)
- **Related:** Other web series by same creator (via `web_series_relationships`)

**Slug Generation:**
- Format: `title-slug-YYYY` or `title-slug-YYYY-N`
- Same as TvSeries

**TMDb Compatibility:**
- ⚠️ Partial compatibility (TMDb has some web series, but not all)
- May need alternative verification source (YouTube API, etc.)

**AI Prompt Considerations:**
- Emphasize online distribution and platform
- Mention episodic nature and binge-watching appeal
- Different context tags: binge-worthy, viral, cult-favorite

**Implementation:**
- 4 new tables (web_series, web_series_descriptions, web_series_person, web_series_relationships)
- New model: WebSeries
- New controller: WebSeriesController
- New repository: WebSeriesRepository
- New jobs: RealGenerateWebSeriesJob, MockGenerateWebSeriesJob
- New action: QueueWebSeriesGenerationAction
- New services: WebSeriesSearchService, WebSeriesRetrievalService, etc.
- Update GenerateController to handle WEB_SERIES entity type
- May need alternative verification service (YouTube API, etc.)

**Estimated Time:** 3-4 weeks (30-40 hours, more if alternative verification needed)

**Pros:**
- ✅ Clear separation (web series explicitly separate from TV series)
- ✅ Type-safe queries (can't accidentally mix web series with TV series)
- ✅ Independent API endpoints (`/api/v1/web-series`)
- ✅ Can have web series-specific business logic
- ✅ Can handle different verification sources (YouTube API vs. TMDb)
- ✅ Clearer data model
- ✅ Can add web series-specific fields (view counts, subscriber counts, etc.)

**Cons:**
- ❌ Table proliferation (4 new tables)
- ❌ Code duplication (similar to TvSeries structure)
- ❌ Harder cross-type queries (e.g., "all series by creator X" requires union query)
- ❌ More maintenance (changes to TvSeries don't automatically apply)
- ❌ More complex migrations
- ❌ Platform is just a string - why separate table for that?

---

#### Option B: Field Extension Approach

**Approach:** Use existing `TvSeries` table, add `platform` and `distribution_type` fields, filter by platform.

**Required Changes:**
- Add `platform` field (string, nullable) to `tv_series` table - YouTube, Netflix, Amazon Prime, etc.
- Add `distribution_type` enum field (TRADITIONAL_TV, ONLINE, BOTH) to `tv_series` table - default: TRADITIONAL_TV
- Update TvSeries model to include new fields
- Add filtering in TvSeriesController: `GET /api/v1/tv-series?platform=youtube` or `GET /api/v1/tv-series?distribution_type=online`
- May need alternative verification service for online-only series (YouTube API, etc.)

**Implementation:**
- 1 migration: add `platform` and `distribution_type` fields to `tv_series` table
- Update TvSeries model (add fields to fillable)
- Update TvSeriesController (add platform/distribution_type filtering)
- Update AI prompts (check for platform/distribution_type)
- May need alternative verification service (YouTube API, etc.) - but can be conditional based on platform field
- No new tables, models, controllers, or jobs needed

**Estimated Time:** 2-3 hours (or more if alternative verification needed)

**Pros:**
- ✅ No table proliferation (reuses existing TvSeries table)
- ✅ No code duplication (reuses existing TvSeries code)
- ✅ Easier cross-type queries (e.g., "all series by creator X" works naturally)
- ✅ Less maintenance (changes to TvSeries apply to all series)
- ✅ Simpler migrations (just add fields)
- ✅ Consistent API (all series use `/api/v1/tv-series`)
- ✅ Platform field useful for ALL series (traditional TV can also have platform info)
- ✅ Can filter by any platform (not just online)

**Cons:**
- ❌ Less type safety (web series mixed with TV series in same table)
- ❌ No independent API endpoints (must use `/api/v1/tv-series?platform=youtube`)
- ❌ Harder to add web series-specific business logic (need conditional checks)
- ❌ Less clear data model (web series not explicitly separate)
- ❌ Alternative verification (YouTube API) might be harder to integrate (conditional logic)
- ❌ Can't easily add web series-specific fields (view counts, subscriber counts) without affecting TV series

---

#### Comparison Analysis

| Aspect | Option A: Separate Type | Option B: Field Extension |
|--------|------------------------|--------------------------|
| **Implementation Time** | 3-4 weeks (30-40 hours) | 2-3 hours |
| **Table Count** | +4 tables | +0 tables (add fields) |
| **Code Duplication** | High (duplicate TvSeries structure) | Low (reuse TvSeries code) |
| **Type Safety** | ✅ High (separate types) | ⚠️ Medium (same type, filtered) |
| **Cross-Type Queries** | ❌ Hard (union queries) | ✅ Easy (single table) |
| **Maintenance** | ❌ High (separate codebase) | ✅ Low (shared codebase) |
| **API Clarity** | ✅ Clear (`/web-series`) | ⚠️ Less clear (`/tv-series?platform=youtube`) |
| **Verification Sources** | ✅ Easy (separate services) | ⚠️ Harder (conditional logic) |
| **Web-Specific Fields** | ✅ Easy (separate table) | ❌ Harder (affects all series) |
| **Data Model Clarity** | ✅ Clear separation | ⚠️ Less clear (mixed in table) |

**Key Question:** Is online distribution a fundamental difference that requires separate content type, or just a metadata field?

**Analysis:**
- Web Series share the same structure as TvSeries: title, first_air_date, seasons, episodes, genres
- The only difference is `platform` (distribution channel) - this is metadata, not structural difference
- Some web series are in TMDb (Netflix originals), some are not (YouTube series)
- Platform is useful metadata for ALL series (traditional TV also has platforms: HBO, ABC, etc.)
- The distinction is about distribution, not content structure

**Decision Criteria:**

**Choose Option A (Separate Content Type) if:**
- Web series need significantly different metadata (view counts, subscriber counts, engagement metrics)
- Web series have different episode structure (variable-length episodes, no seasons concept)
- Verification sources are completely different (YouTube API vs. TMDb) and need separate handling
- Web series need independent business logic that can't be handled with conditional checks
- Type safety is critical (can't risk mixing web series with TV series)
- You need web series-specific features that would complicate TvSeries model

**Choose Option B (Field Extension Approach) if:**
- Web series have same structure as TV series (seasons, episodes, same relationships)
- Platform is just metadata (not structural difference)
- No need for web-specific fields (view counts, subscriber counts, etc.)
- Simplicity and maintainability are priorities
- Cross-type queries are important (e.g., "all series by creator X")
- You want to avoid code duplication and table proliferation
- Verification can be handled conditionally based on platform field

**Conclusion:** The decision depends on actual requirements. **If web series are just "TvSeries distributed online"** (same structure, just different platform), **Option B is more appropriate**. **If they have fundamentally different structure/metadata** (view counts, different episode structure, different verification), **Option A is more appropriate**. Evaluate based on specific business needs.

---

### 4. Podcasts

**Description:** Audio content with episodes, hosts, and topics.

**Required Fields:**
- **Shared:** id, slug, title, genres (topics/themes), default_description_id
- **Unique:** first_episode_date (date), last_episode_date (date, nullable), number_of_episodes (integer), average_episode_runtime (integer) - minutes
- **Optional:** network (string) - podcast network, frequency (string) - weekly, daily, etc., language (string) - primary language

**Relationships:**
- **People:** Hosts, guests, producers (via `podcast_person` pivot)
  - Roles: HOST, GUEST, PRODUCER, CREATOR
- **Descriptions:** Same pattern as TvSeries (via `podcast_descriptions`)
- **Related:** Other podcasts by same host/network (via `podcast_relationships`)

**Slug Generation:**
- Format: `title-slug-YYYY` or `title-slug-YYYY-N`
- Based on first_episode_date year

**TMDb Compatibility:**
- ❌ No compatibility (TMDb doesn't have podcasts)
- Need alternative verification (Apple Podcasts API, Spotify API, etc.)

**AI Prompt Considerations:**
- Focus on audio format and episodic nature
- Emphasize hosts and topics/themes
- Different context tags: informative, entertaining, thought-provoking

**Implementation Complexity:** High (no TMDb support, need alternative verification, different metadata focus)

---

### 5. Books

**Description:** Written content with authors, publishers, and publication dates.

**Required Fields:**
- **Shared:** id, slug, title, genres (genres/themes), default_description_id
- **Unique:** publication_date (date), isbn (string, 13 or 10 digits), publisher (string), page_count (integer)
- **Optional:** edition (string) - 1st, 2nd, etc., language (string) - original language, series (string) - book series name

**Relationships:**
- **People:** Authors, co-authors, illustrators (via `book_person` pivot)
  - Roles: AUTHOR, CO_AUTHOR, ILLUSTRATOR, TRANSLATOR
- **Descriptions:** Same pattern as Movie (via `book_descriptions`)
- **Related:** Other books in same series, by same author (via `book_relationships`)

**Slug Generation:**
- Format: `title-slug-YYYY` or `title-slug-YYYY-author-slug` or `title-slug-YYYY-N`
- Based on publication_date year
- Author disambiguation similar to Movie director disambiguation

**TMDb Compatibility:**
- ❌ No compatibility (TMDb doesn't have books)
- Need alternative verification (Google Books API, Open Library API, etc.)

**AI Prompt Considerations:**
- Focus on written narrative and literary elements
- Emphasize author's style and themes
- Different context tags: literary, popular, academic

**Implementation Complexity:** High (no TMDb support, need alternative verification, different metadata structure)

---

### 6. Music Albums

**Description:** Audio content with artists, tracks, and release dates.

**Required Fields:**
- **Shared:** id, slug, title, genres (music genres), default_description_id
- **Unique:** release_date (date), label (string) - record label, total_tracks (integer), total_duration (integer) - total minutes
- **Optional:** format (string) - LP, CD, digital, etc., type (string) - album, EP, single, compilation

**Relationships:**
- **People:** Artists, producers, featured artists (via `music_album_person` pivot)
  - Roles: ARTIST, PRODUCER, FEATURED_ARTIST, COMPOSER
- **Descriptions:** Same pattern as Movie (via `music_album_descriptions`)
- **Related:** Other albums by same artist, in same series (via `music_album_relationships`)

**Slug Generation:**
- Format: `title-slug-YYYY` or `title-slug-YYYY-artist-slug` or `title-slug-YYYY-N`
- Based on release_date year
- Artist disambiguation similar to Movie director disambiguation

**TMDb Compatibility:**
- ❌ No compatibility (TMDb doesn't have music)
- Need alternative verification (Spotify API, MusicBrainz API, etc.)

**AI Prompt Considerations:**
- Focus on musical style and sound
- Emphasize artist's evolution and influences
- Different context tags: critical, popular, influential

**Implementation Complexity:** High (no TMDb support, need alternative verification, different metadata structure)

---

## Impact Analysis

### Database Impact

#### New Tables Required

For each new content type, we need:

1. **Main Entity Table** (e.g., `documentaries`, `short_films`, `podcasts`)
   - Similar structure to existing tables
   - UUID primary key, slug, title, genres, default_description_id, tmdb_id
   - Type-specific fields (release_year, runtime, platform, etc.)

2. **Description Table** (e.g., `documentary_descriptions`, `podcast_descriptions`)
   - Same structure as existing description tables
   - Foreign key to main entity
   - Unique constraint on (entity_id, locale, context_tag)

3. **Person Pivot Table** (e.g., `documentary_person`, `podcast_person`)
   - Same structure as existing person pivot tables
   - Foreign keys to entity and person
   - Role, character_name, job, billing_order fields

4. **Relationships Table** (e.g., `documentary_relationships`, `podcast_relationships`)
   - Same structure as existing relationship tables
   - Foreign keys to two entities
   - Relationship type and order fields

**Estimated Tables per Extension:** 4 tables

**Total New Tables for 6 Extensions:** 24 tables

#### Shared Tables Considerations

**Current Approach (Separate Tables):**
- ✅ Pros: Clear separation, easy to understand, type-safe queries
- ❌ Cons: Table proliferation, similar migrations, potential duplication

**Alternative Approach (Polymorphic):**
- `content_descriptions` with `describable_type` and `describable_id`
- `content_people` with `contentable_type` and `contentable_id`
- `content_relationships` with `contentable_type` and `contentable_id`

**Polymorphic Pros:**
- Single table for all descriptions
- Easier cross-type queries
- Fewer migrations

**Polymorphic Cons:**
- More complex foreign key constraints
- Potential performance issues (indexes on polymorphic columns)
- Harder to understand for developers
- Migration complexity (need to migrate existing tables)

**Recommendation:** Keep separate tables for now (current approach). Consider polymorphic if we reach 10+ content types.

#### Migration Complexity

**Per Extension:**
- 4 migrations (main table, descriptions, person pivot, relationships)
- Similar structure to existing migrations
- Estimated time: 1-2 hours per extension

**Total Migration Time:** 6-12 hours for all 6 extensions

#### Index Requirements

All tables need similar indexes:
- Primary key on `id` (UUID)
- Unique index on `slug`
- Index on `default_description_id`
- Index on `tmdb_id` (if applicable)
- Foreign key indexes on pivot tables
- Composite indexes on (entity_id, locale, context_tag) for descriptions

**No significant performance concerns** - indexes are standard and well-understood.

---

### API Impact

#### New Endpoints Required

For each new content type, we need:

1. **List/Search Endpoint:** `GET /api/v1/{type}?q=...`
2. **Get Single:** `GET /api/v1/{type}/{slug}`
3. **Search:** `GET /api/v1/{type}/search?q=...`
4. **Related:** `GET /api/v1/{type}/{slug}/related`
5. **Bulk Retrieve:** `GET /api/v1/{type}?slugs=...`
6. **Report:** `POST /api/v1/{type}/{slug}/report`
7. **Compare:** `POST /api/v1/{type}/compare`

**Estimated Endpoints per Extension:** 7 endpoints

**Total New Endpoints for 6 Extensions:** 42 endpoints

#### Unified vs. Separate Endpoints

**Current Approach (Separate Endpoints):**
- `/api/v1/movies/{slug}`
- `/api/v1/tv-series/{slug}`
- `/api/v1/podcasts/{slug}`

**Alternative Approach (Unified):**
- `/api/v1/content/{type}/{slug}` (e.g., `/api/v1/content/movie/the-matrix-1999`)

**Unified Pros:**
- Single controller for all content types
- Easier to add new types (just add to enum)
- Consistent API structure

**Unified Cons:**
- Breaking change for existing clients
- More complex routing logic
- Harder to version different types independently

**Recommendation:** Keep separate endpoints (current approach). Unified endpoints would require breaking changes and don't provide significant benefits.

#### OpenAPI Schema Updates

For each extension:
- New schema definitions for entity, description, person relationships
- New endpoint definitions
- New request/response examples

**Estimated Time:** 2-3 hours per extension

**Total Time:** 12-18 hours for all 6 extensions

#### Response Format Consistency

All endpoints should follow the same response format:
- `data` - Entity data
- `_links` - HATEOAS links
- `_meta` - Metadata (disambiguation, etc.)

**Current format is consistent** - new endpoints should follow the same pattern.

#### HATEOAS Link Structure

All endpoints should provide:
- `self` - Link to current resource
- `people` - Array of person links
- `generate` - Link to generation endpoint
- `related` - Link to related content

**Current structure is consistent** - new endpoints should follow the same pattern.

---

### Jobs Impact

#### New Generation Jobs Required

For each new content type, we need:

1. **RealGenerate*Job** - Production job with AI API calls
2. **MockGenerate*Job** - Test job with mock responses

**Estimated Jobs per Extension:** 2 jobs

**Total New Jobs for 6 Extensions:** 12 jobs

#### Common Job Base Class

**Current Approach (Separate Jobs):**
- Each job is independent
- Similar code but no shared base class

**Alternative Approach (Base Class):**
```php
abstract class BaseGenerateContentJob implements ShouldQueue
{
    // Common properties: slug, jobId, locale, contextTag
    // Common methods: handle(), updateCache(), invalidateCaches()
    // Abstract methods: createRecord(), refreshExisting(), etc.
}
```

**Base Class Pros:**
- Reduce code duplication
- Easier to maintain common logic
- Consistent error handling

**Base Class Cons:**
- More complex inheritance hierarchy
- Harder to customize per type
- Potential for over-abstraction

**Recommendation:** Create base class after 3+ extensions are added. Start with separate jobs for first 2-3 extensions to understand patterns better.

#### AI Prompt Templates

Each content type needs customized AI prompts:

**Current Prompts:**
- Movie: "Write a concise description of the movie {title} ({year})..."
- Person: "Write a concise biography of {name}..."
- TvSeries: "Write a concise description of the TV series {title}..."

**New Prompts Needed:**
- Documentary: "Write a concise, factual description of the documentary {title} ({year}) about {subject}..."
- Short Film: "Write a concise description of the short film {title} ({year}, {runtime} minutes)..."
- Podcast: "Write a concise description of the podcast {title}, hosted by {hosts}..."
- Book: "Write a concise description of the book {title} ({year}) by {author}..."
- Music Album: "Write a concise description of the album {title} ({year}) by {artist}..."

**Estimated Time:** 1-2 hours per extension to create and test prompts

**Total Time:** 6-12 hours for all 6 extensions

#### Queue Management

Current queue management uses `JobStatusService` with entity type:
- `JobStatusService::acquireGenerationSlot('MOVIE', $slug, ...)`
- `JobStatusService::findActiveJobForSlug('MOVIE', $slug, ...)`

**Impact:** Need to add new entity types to `JobStatusService`:
- `DOCUMENTARY`, `SHORT_FILM`, `WEB_SERIES`, `PODCAST`, `BOOK`, `MUSIC_ALBUM`

**Estimated Time:** 1 hour to update `JobStatusService` and related enums

---

### Services Impact

#### Repository Pattern

**Current Approach (Separate Repositories):**
- `MovieRepository`, `PersonRepository`, `TvSeriesRepository`, `TvShowRepository`
- Each has similar methods: `search*()`, `findBySlugWithRelations()`, `findBySlugForJob()`, etc.

**Alternative Approach (Base Repository):**
```php
abstract class BaseContentRepository
{
    abstract protected function getModelClass(): string;
    
    public function search(?string $query, int $limit = 50): Collection
    {
        return $this->getModelClass()::query()
            ->when($query, function ($builder) use ($query) {
                // Common search logic
            })
            ->limit($limit)
            ->get();
    }
    
    // Common methods with model-agnostic logic
}
```

**Base Repository Pros:**
- Reduce code duplication
- Easier to maintain common logic
- Consistent search behavior

**Base Repository Cons:**
- More complex inheritance
- Harder to customize per type
- Potential for over-abstraction

**Recommendation:** Create base repository after 3+ extensions are added. Start with separate repositories for first 2-3 extensions.

#### Controller Pattern

**Current Approach (Separate Controllers):**
- `MovieController`, `PersonController`, `TvSeriesController`, `TvShowController`
- Each has similar methods: `index()`, `show()`, `search()`, `related()`

**Alternative Approach (Base Controller):**
```php
abstract class BaseContentController extends Controller
{
    abstract protected function getRepository(): BaseContentRepository;
    abstract protected function getResponseFormatter(): BaseResponseFormatter;
    
    public function index(Request $request): JsonResponse
    {
        // Common index logic
    }
    
    // Common methods with type-agnostic logic
}
```

**Base Controller Pros:**
- Reduce code duplication
- Easier to maintain common logic
- Consistent API behavior

**Base Controller Cons:**
- More complex inheritance
- Harder to customize per type
- Potential for over-abstraction

**Recommendation:** Create base controller after 3+ extensions are added. Start with separate controllers for first 2-3 extensions.

#### Response Formatters

**Current Approach (Separate Formatters):**
- `MovieResponseFormatter`, `PersonResponseFormatter`, `TvSeriesResponseFormatter`
- Each has similar methods: `formatSuccess()`, `formatError()`, `formatNotFound()`

**Alternative Approach (Base Formatter):**
```php
abstract class BaseContentResponseFormatter
{
    abstract protected function getEntityType(): string;
    
    public function formatSuccess($entity, string $slug): JsonResponse
    {
        // Common formatting logic
    }
    
    // Common methods with type-agnostic logic
}
```

**Base Formatter Pros:**
- Reduce code duplication
- Easier to maintain common logic
- Consistent response format

**Base Formatter Cons:**
- More complex inheritance
- Harder to customize per type
- Potential for over-abstraction

**Recommendation:** Create base formatter after 3+ extensions are added. Start with separate formatters for first 2-3 extensions.

#### Validation Services

**Current Approach:**
- `SlugValidator::validateMovieSlug($slug)`
- `SlugValidator::validatePersonSlug($slug)`
- `SlugValidator::validateTvSeriesSlug($slug)`
- `SlugValidator::validateTvShowSlug($slug)`

**Impact:** Need to add new validation methods:
- `SlugValidator::validateDocumentarySlug($slug)`
- `SlugValidator::validateShortFilmSlug($slug)`
- `SlugValidator::validatePodcastSlug($slug)`
- etc.

**Estimated Time:** 30 minutes per extension to add validation method

**Total Time:** 3 hours for all 6 extensions

---

## Common Interfaces and Refactoring

### Potential Interfaces/Traits

#### 1. DescribableContent Interface

**Purpose:** Define contract for entities with AI-generated descriptions.

```php
interface DescribableContent
{
    public function descriptions(): HasMany;
    public function defaultDescription(): HasOne;
    public function getDefaultDescriptionId(): ?string;
    public function setDefaultDescriptionId(?string $id): void;
}
```

**Benefits:**
- Type safety for description-related operations
- Consistent API across all content types
- Easier to write generic code

**Implementation:**
- Movie, TvSeries, TvShow, Documentary, ShortFilm, WebSeries, Podcast, Book, MusicAlbum all implement this

**Estimated Time:** 2-3 hours to create interface and update all models

---

#### 2. Sluggable Trait

**Purpose:** Provide common slug generation and parsing logic.

```php
trait Sluggable
{
    abstract public static function generateSlug(...): string;
    abstract public static function parseSlug(string $slug): array;
    
    // Common helper methods for slug operations
    protected static function baseSlugFromTitle(string $title): string
    {
        return Str::slug($title);
    }
    
    protected static function checkSlugExists(string $slug, ?string $excludeId = null): bool
    {
        $query = static::where('slug', $slug);
        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }
        return $query->exists();
    }
}
```

**Benefits:**
- Reduce duplication in slug generation logic
- Consistent slug handling across all content types
- Easier to maintain slug generation rules

**Implementation:**
- All content types use this trait
- Each type implements `generateSlug()` and `parseSlug()` with type-specific logic

**Estimated Time:** 3-4 hours to create trait and refactor existing models

---

#### 3. HasPeople Interface

**Purpose:** Define contract for entities with person relationships.

```php
interface HasPeople
{
    public function people(): BelongsToMany;
    public function getPeopleByRole(string $role): Collection;
    public function addPerson(Person $person, string $role, ?string $characterName = null): void;
}
```

**Benefits:**
- Type safety for person-related operations
- Consistent API across all content types
- Easier to write generic code for person relationships

**Implementation:**
- Movie, TvSeries, TvShow, Documentary, ShortFilm, WebSeries, Podcast, Book, MusicAlbum all implement this

**Estimated Time:** 2-3 hours to create interface and update all models

---

#### 4. HasGenres Interface

**Purpose:** Define contract for entities with genres.

```php
interface HasGenres
{
    public function getGenres(): array;
    public function setGenres(array $genres): void;
    public function hasGenre(string $genre): bool;
}
```

**Benefits:**
- Type safety for genre operations
- Consistent API across all content types
- Easier to write generic code for genre filtering

**Implementation:**
- All content types (except Person) implement this

**Estimated Time:** 1-2 hours to create interface and update all models

---

#### 5. TmdBVerifiable Interface

**Purpose:** Define contract for entities verifiable via TMDb.

```php
interface TmdBVerifiable
{
    public function getTmdbId(): ?int;
    public function setTmdbId(?int $tmdbId): void;
    public function hasTmdbId(): bool;
    public function tmdbSnapshot(): HasOne;
}
```

**Benefits:**
- Type safety for TMDb operations
- Consistent API across TMDb-compatible content types
- Easier to write generic code for TMDb verification

**Implementation:**
- Movie, TvSeries, TvShow, Documentary, ShortFilm implement this
- Podcast, Book, MusicAlbum do NOT implement this (no TMDb support)

**Estimated Time:** 1-2 hours to create interface and update compatible models

---

### Refactoring Opportunities

#### 1. Base Model Class

**Current Approach:** Each model is independent (Movie, Person, TvSeries, TvShow).

**Proposed Approach:**
```php
abstract class Content extends Model
{
    use HasFactory, HasUuids, Sluggable;
    use DescribableContent, HasPeople, HasGenres;
    
    protected $fillable = [
        'title', 'slug', 'genres', 'default_description_id', 'tmdb_id',
    ];
    
    protected $casts = [
        'genres' => 'array',
    ];
    
    // Common methods
}
```

**Benefits:**
- Reduce duplication in model definitions
- Consistent structure across all content types
- Easier to add common functionality

**Drawbacks:**
- More complex inheritance hierarchy
- Harder to customize per type
- Potential for over-abstraction

**Recommendation:** Consider base class after 5+ content types are added. Start with interfaces/traits for first few extensions.

---

#### 2. Base Controller Class

**Current Approach:** Each controller is independent.

**Proposed Approach:**
```php
abstract class BaseContentController extends Controller
{
    abstract protected function getRepository(): BaseContentRepository;
    abstract protected function getResponseFormatter(): BaseContentResponseFormatter;
    abstract protected function getSearchService(): BaseContentSearchService;
    abstract protected function getRetrievalService(): BaseContentRetrievalService;
    
    public function index(Request $request): JsonResponse
    {
        // Common index logic
    }
    
    public function show(Request $request, string $slug): JsonResponse
    {
        // Common show logic
    }
    
    // Common methods
}
```

**Benefits:**
- Reduce duplication in controller code
- Consistent API behavior across all content types
- Easier to maintain common logic

**Drawbacks:**
- More complex inheritance
- Harder to customize per type
- Potential for over-abstraction

**Recommendation:** Create base controller after 3+ extensions are added. Start with separate controllers for first 2-3 extensions.

---

#### 3. Base Repository Class

**Current Approach:** Each repository is independent.

**Proposed Approach:**
```php
abstract class BaseContentRepository
{
    abstract protected function getModelClass(): string;
    
    public function search(?string $query, int $limit = 50): Collection
    {
        return $this->getModelClass()::query()
            ->when($query, function ($builder) use ($query) {
                $this->applySearchFilters($builder, $query);
            })
            ->with($this->getDefaultRelations())
            ->limit($limit)
            ->get();
    }
    
    abstract protected function applySearchFilters($builder, string $query): void;
    abstract protected function getDefaultRelations(): array;
    
    // Common methods
}
```

**Benefits:**
- Reduce duplication in repository code
- Consistent data access patterns
- Easier to maintain common logic

**Drawbacks:**
- More complex inheritance
- Harder to customize per type
- Potential for over-abstraction

**Recommendation:** Create base repository after 3+ extensions are added. Start with separate repositories for first 2-3 extensions.

---

#### 4. Base Generation Job Class

**Current Approach:** Each job is independent.

**Proposed Approach:**
```php
abstract class BaseGenerateContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public int $tries = 3;
    public int $timeout = 120;
    
    public function __construct(
        public string $slug,
        public string $jobId,
        public ?string $existingId = null,
        public ?string $baselineDescriptionId = null,
        public ?string $locale = null,
        public ?string $contextTag = null,
        public ?array $tmdbData = null
    ) {}
    
    public function handle(OpenAiClientInterface $openAiClient): void
    {
        // Common handle logic
        $existing = $this->findExisting();
        if ($existing) {
            $this->refreshExisting($existing, $openAiClient);
            return;
        }
        $result = $this->createRecord($openAiClient);
        // Common post-creation logic
    }
    
    abstract protected function findExisting(): ?Model;
    abstract protected function refreshExisting(Model $entity, OpenAiClientInterface $openAiClient): void;
    abstract protected function createRecord(OpenAiClientInterface $openAiClient): ?array;
    // Abstract methods for type-specific logic
}
```

**Benefits:**
- Reduce duplication in job code
- Consistent job behavior across all content types
- Easier to maintain common logic (error handling, caching, etc.)

**Drawbacks:**
- More complex inheritance
- Harder to customize per type
- Potential for over-abstraction

**Recommendation:** Create base job class after 3+ extensions are added. Start with separate jobs for first 2-3 extensions.

---

#### 5. Unified Description Model (Polymorphic)

**Current Approach:** Separate description models (MovieDescription, TvSeriesDescription, etc.).

**Proposed Approach:**
```php
class ContentDescription extends Model
{
    protected $fillable = [
        'describable_type', 'describable_id', 'locale', 'text',
        'context_tag', 'origin', 'ai_model',
    ];
    
    public function describable(): MorphTo
    {
        return $this->morphTo();
    }
}
```

**Migration:**
```php
Schema::create('content_descriptions', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('describable_type'); // Movie, TvSeries, Documentary, etc.
    $table->uuid('describable_id');
    $table->string('locale', 10);
    $table->text('text');
    $table->string('context_tag', 64)->nullable();
    $table->string('origin', 32)->default('GENERATED');
    $table->string('ai_model', 64)->nullable();
    $table->timestamps();
    $table->unique(['describable_type', 'describable_id', 'locale', 'context_tag']);
    $table->index(['describable_type', 'describable_id']);
});
```

**Benefits:**
- Single table for all descriptions
- Easier cross-type queries (e.g., "all descriptions in pl-PL")
- Fewer migrations
- Easier to add new content types (no new description table needed)

**Drawbacks:**
- More complex foreign key constraints (polymorphic)
- Potential performance issues (indexes on polymorphic columns)
- Harder to understand for developers
- Migration complexity (need to migrate existing tables)
- Loss of type safety (can't use foreign key constraints)

**Recommendation:** Keep separate description tables for now. Consider polymorphic approach if we reach 10+ content types and need cross-type queries frequently.

---

### Polymorphic Relationships Analysis

#### Current Approach (Separate Tables)

**Pros:**
- ✅ Clear separation of concerns
- ✅ Type-safe foreign keys
- ✅ Easy to understand
- ✅ Good performance (direct foreign keys)
- ✅ Easy to add indexes

**Cons:**
- ❌ Table proliferation (4 tables per content type)
- ❌ Similar migrations for each type
- ❌ Potential code duplication

#### Polymorphic Approach

**Pros:**
- ✅ Single table for all descriptions
- ✅ Easier cross-type queries
- ✅ Fewer migrations
- ✅ Easier to add new types

**Cons:**
- ❌ More complex foreign key constraints
- ❌ Potential performance issues
- ❌ Harder to understand
- ❌ Migration complexity
- ❌ Loss of type safety

#### Recommendation

**Keep separate tables for now** (current approach). Reasons:

1. **Type Safety:** Foreign key constraints ensure data integrity
2. **Performance:** Direct foreign keys are faster than polymorphic
3. **Clarity:** Separate tables are easier to understand
4. **Migration:** Current approach is simpler to implement

**Consider polymorphic if:**
- We reach 10+ content types
- We need frequent cross-type queries
- Table proliferation becomes a maintenance burden

---

## Recommendations

### Implementation Strategy

#### Phase 1: Movie Extensions (Week 1-3)

**Priority:** Documentaries, Short Films

**Approach (depends on decision):**

**If Option B (Genre/Filter) is chosen:**
- **Documentaries:** Add `subject` field to `movies` table, filter by genre "Documentary"
- **Short Films:** Add `runtime` and `festival` fields to `movies` table, filter by `runtime < 40`
- Update MovieController to support filtering: `?genre=documentary`, `?type=short_film`, `?max_runtime=40`
- Update AI prompts to handle documentary/short film genres differently
- No new tables, models, controllers, or jobs needed
- **Estimated Time:** 2-3 hours per extension (total: 4-6 hours for both)

**If Option A (Separate Content Type) is chosen:**
- Follow existing Movie patterns exactly
- Create separate tables, models, controllers, repositories, jobs
- Reuse existing services (TmdbVerificationService, etc.)
- Add to GenerateController with new entity types
- **Estimated Time:** 2-3 weeks per extension (total: 4-6 weeks for both)

**Decision Required:** Use decision criteria in Documentaries and Short Films sections to choose approach.

---

#### Phase 2: TvSeries Extension (Week 2-4)

**Priority:** Web Series

**Approach (depends on decision):**

**If Option B (Field Extension) is chosen:**
- Add `platform` field (string, nullable) to `tv_series` table
- Add `distribution_type` enum (TRADITIONAL_TV, ONLINE, BOTH) to `tv_series` table
- Update TvSeriesController to support filtering: `?platform=youtube`, `?distribution_type=online`
- May need alternative verification source (YouTube API, etc.) for online-only series
- No new tables, models, controllers, or jobs needed (unless verification is fundamentally different)
- **Estimated Time:** 2-3 hours

**If Option A (Separate Content Type) is chosen:**
- Follow existing TvSeries patterns
- May need alternative verification source (YouTube API, etc.)
- Create separate tables, models, controllers, repositories, jobs
- **Estimated Time:** 3-4 weeks

**Decision Required:** Use decision criteria in Web Series section to choose approach.

---

#### Phase 3: True Content Type Extensions (Months 2-6)

**Priority:** Podcasts, Books, Music Albums

**Rationale:**
- These ARE fundamentally different content types (not variations of movies/series)
- Different metadata structure (high implementation complexity)
- No TMDb compatibility (need alternative verification)
- Lower immediate demand

**Approach:**
- Follow existing patterns but with more customization
- Need alternative verification sources:
  - Podcasts: Apple Podcasts API, Spotify API
  - Books: Google Books API, Open Library API
  - Music: Spotify API, MusicBrainz API
- Create separate tables, models, controllers, repositories, jobs

**Estimated Time:** 4-6 weeks per extension

---

### Refactoring Strategy

#### Step 1: Add Interfaces/Traits (After 2-3 Extensions)

**When:** After Documentaries and Short Films are added

**Actions:**
1. Create `DescribableContent` interface
2. Create `Sluggable` trait
3. Create `HasPeople` interface
4. Create `HasGenres` interface
5. Create `TmdBVerifiable` interface
6. Update all existing models to use interfaces/traits

**Estimated Time:** 1-2 weeks

**Benefits:**
- Type safety
- Consistent API
- Easier to write generic code

---

#### Step 2: Create Base Classes (After 3-4 Extensions)

**When:** After Web Series is added

**Actions:**
1. Create `BaseContentRepository` abstract class
2. Create `BaseContentController` abstract class
3. Create `BaseContentResponseFormatter` abstract class
4. Refactor existing repositories/controllers/formatters to extend base classes

**Estimated Time:** 2-3 weeks

**Benefits:**
- Reduce code duplication
- Easier maintenance
- Consistent behavior

---

#### Step 3: Create Base Job Class (After 3-4 Extensions)

**When:** After Web Series is added

**Actions:**
1. Create `BaseGenerateContentJob` abstract class
2. Refactor existing jobs to extend base class
3. Extract common logic (error handling, caching, etc.)

**Estimated Time:** 1-2 weeks

**Benefits:**
- Reduce code duplication
- Consistent job behavior
- Easier maintenance

---

#### Step 4: Consider Polymorphic Relationships (After 10+ Extensions)

**When:** If we reach 10+ content types

**Actions:**
1. Evaluate if table proliferation is a problem
2. Assess if cross-type queries are needed frequently
3. If yes, migrate to polymorphic `content_descriptions` table
4. Migrate to polymorphic `content_people` table
5. Migrate to polymorphic `content_relationships` table

**Estimated Time:** 4-6 weeks (complex migration)

**Benefits:**
- Single table for all descriptions
- Easier cross-type queries
- Fewer migrations for new types

---

### Alternatives

#### Alternative 1: Microservices Architecture

**Approach:** Split each content type into separate microservices.

**Pros:**
- Independent scaling per content type
- Technology flexibility per service
- Team autonomy

**Cons:**
- High operational complexity
- Network latency between services
- Data consistency challenges
- Over-engineering for current scale

**Recommendation:** ❌ Not recommended for current scale. Consider if we reach 20+ content types and need independent scaling.

---

#### Alternative 2: Plugin Architecture

**Approach:** Create plugin system where new content types are loaded as plugins.

**Pros:**
- Easy to add new types without core changes
- Third-party extensions possible
- Modular architecture

**Cons:**
- High initial complexity
- Plugin API design challenges
- Version compatibility issues
- Over-engineering for current needs

**Recommendation:** ❌ Not recommended. Current approach is simpler and sufficient.

---

#### Alternative 3: Configuration-Driven Content Types

**Approach:** Define content types in configuration files, generate code automatically.

**Pros:**
- Very fast to add new types
- Consistent structure
- Less code to maintain

**Cons:**
- Code generation complexity
- Harder to customize per type
- Debugging challenges
- Over-engineering

**Recommendation:** ❌ Not recommended. Manual implementation provides better control and customization.

---

## Migration Path

### Step-by-Step Approach for Adding New Types

#### Step 1: Database Schema

1. Create main entity table migration
   ```php
   Schema::create('documentaries', function (Blueprint $table) {
       $table->uuid('id')->primary();
       $table->string('title');
       $table->string('slug')->unique();
       $table->smallInteger('release_year')->nullable();
       $table->string('director')->nullable();
       $table->string('subject')->nullable(); // Unique field
       $table->json('genres')->nullable();
       $table->uuid('default_description_id')->nullable()->index();
       $table->unsignedInteger('tmdb_id')->nullable()->index();
       $table->timestamps();
   });
   ```

2. Create description table migration
   ```php
   Schema::create('documentary_descriptions', function (Blueprint $table) {
       $table->uuid('id')->primary();
       $table->foreignUuid('documentary_id')->constrained('documentaries')->cascadeOnDelete();
       $table->string('locale', 10);
       $table->text('text');
       $table->string('context_tag', 64)->nullable();
       $table->string('origin', 32)->default('GENERATED');
       $table->string('ai_model', 64)->nullable();
       $table->timestamps();
       $table->unique(['documentary_id', 'locale', 'context_tag']);
   });
   ```

3. Create person pivot table migration
   ```php
   Schema::create('documentary_person', function (Blueprint $table) {
       $table->foreignUuid('documentary_id')->constrained('documentaries')->cascadeOnDelete();
       $table->foreignUuid('person_id')->constrained('people')->cascadeOnDelete();
       $table->string('role', 16);
       $table->string('character_name')->nullable();
       $table->string('job')->nullable();
       $table->unsignedSmallInteger('billing_order')->nullable();
       $table->primary(['documentary_id', 'person_id', 'role']);
       $table->index(['role', 'billing_order']);
   });
   ```

4. Create relationships table migration
   ```php
   Schema::create('documentary_relationships', function (Blueprint $table) {
       $table->uuid('id')->primary();
       $table->foreignUuid('documentary_id')->constrained('documentaries')->cascadeOnDelete();
       $table->foreignUuid('related_documentary_id')->constrained('documentaries')->cascadeOnDelete();
       $table->string('relationship_type', 32);
       $table->unsignedSmallInteger('order')->nullable();
       $table->timestamps();
       $table->index(['documentary_id', 'relationship_type']);
   });
   ```

**Estimated Time:** 1-2 hours

---

#### Step 2: Models

1. Create Documentary model
   ```php
   class Documentary extends Model implements DescribableContent, HasPeople, HasGenres, TmdBVerifiable
   {
       use HasFactory, HasUuids, Sluggable;
       
       protected $fillable = [
           'title', 'slug', 'release_year', 'director', 'subject',
           'genres', 'default_description_id', 'tmdb_id',
       ];
       
       protected $casts = [
           'genres' => 'array',
       ];
       
       public static function generateSlug(string $title, ?int $releaseYear = null, ...): string
       {
           // Similar to Movie::generateSlug()
       }
       
       public function descriptions(): HasMany
       {
           return $this->hasMany(DocumentaryDescription::class);
       }
       
       // Other relationships...
   }
   ```

2. Create DocumentaryDescription model
   ```php
   class DocumentaryDescription extends Model
   {
       protected $fillable = [
           'documentary_id', 'locale', 'text', 'context_tag',
           'origin', 'ai_model',
       ];
   }
   ```

**Estimated Time:** 2-3 hours

---

#### Step 3: Repositories

1. Create DocumentaryRepository
   ```php
   class DocumentaryRepository extends BaseContentRepository // or standalone initially
   {
       protected function getModelClass(): string
       {
           return Documentary::class;
       }
       
       public function searchDocumentaries(?string $query, int $limit = 50): Collection
       {
           return Documentary::query()
               ->when($query, function ($builder) use ($query) {
                   $builder->whereRaw('LOWER(title) LIKE LOWER(?)', ["%$query%"])
                       ->orWhereRaw('LOWER(director) LIKE LOWER(?)', ["%$query%"])
                       ->orWhereRaw('LOWER(subject) LIKE LOWER(?)', ["%$query%"]) // Unique field
                       ->orWhereRaw('LOWER(genres::text) LIKE LOWER(?)', ["%$query%"]);
               })
               ->with(['defaultDescription', 'people'])
               ->withCount('descriptions')
               ->limit($limit)
               ->get();
       }
       
       // Other methods similar to MovieRepository...
   }
   ```

**Estimated Time:** 2-3 hours

---

#### Step 4: Controllers

1. Create DocumentaryController
   ```php
   class DocumentaryController extends BaseContentController // or standalone initially
   {
       public function __construct(
           private readonly DocumentaryRepository $repository,
           private readonly HateoasService $hateoas,
           private readonly DocumentaryResponseFormatter $responseFormatter,
           // Other services...
       ) {}
       
       public function index(Request $request): JsonResponse
       {
           // Similar to MovieController::index()
       }
       
       // Other methods...
   }
   ```

**Estimated Time:** 3-4 hours

---

#### Step 5: Jobs

1. Create RealGenerateDocumentaryJob
   ```php
   class RealGenerateDocumentaryJob extends BaseGenerateContentJob // or standalone initially
   {
       protected function findExisting(): ?Model
       {
           return app(DocumentaryRepository::class)
               ->findBySlugForJob($this->slug, $this->existingDocumentaryId);
       }
       
       protected function createRecord(OpenAiClientInterface $openAiClient): ?array
       {
           // Similar to RealGenerateMovieJob::createMovieRecord()
           // But with documentary-specific AI prompt
       }
       
       // Other methods...
   }
   ```

2. Create MockGenerateDocumentaryJob (for testing)

**Estimated Time:** 4-5 hours

---

#### Step 6: Actions

1. Create QueueDocumentaryGenerationAction
   ```php
   class QueueDocumentaryGenerationAction
   {
       public function handle(
           string $slug,
           ?float $confidence = null,
           ?Documentary $existingDocumentary = null,
           ?string $locale = null,
           ?string $contextTag = null,
           ?array $tmdbData = null
       ): array {
           // Similar to QueueMovieGenerationAction::handle()
       }
   }
   ```

**Estimated Time:** 2-3 hours

---

#### Step 7: Services

1. Create DocumentarySearchService
2. Create DocumentaryRetrievalService
3. Create DocumentaryResponseFormatter
4. Create DocumentaryReportService
5. Create DocumentaryComparisonService

**Estimated Time:** 5-6 hours

---

#### Step 8: Routes

1. Add routes to `routes/api.php`
   ```php
   Route::prefix('v1')->group(function () {
       Route::get('documentaries', [DocumentaryController::class, 'index']);
       Route::get('documentaries/{slug}', [DocumentaryController::class, 'show']);
       Route::get('documentaries/search', [DocumentaryController::class, 'search']);
       Route::get('documentaries/{slug}/related', [DocumentaryController::class, 'related']);
       // Other routes...
   });
   ```

2. Update GenerateController to handle DOCUMENTARY entity type

**Estimated Time:** 1 hour

---

#### Step 9: Validation

1. Add `SlugValidator::validateDocumentarySlug($slug)`
2. Update GenerateRequest to accept DOCUMENTARY entity type

**Estimated Time:** 1 hour

---

#### Step 10: Tests

1. Create DocumentaryControllerTest (feature tests)
2. Create RealGenerateDocumentaryJobTest (unit tests)
3. Create DocumentaryRepositoryTest (unit tests)
4. Update existing tests if needed

**Estimated Time:** 4-6 hours

---

#### Step 11: Documentation

1. Update OpenAPI schema
2. Update Postman collection
3. Update README if needed

**Estimated Time:** 2-3 hours

---

### Total Time Estimate per Extension

**Documentaries/Short Films (Low Complexity):**
- Database: 1-2 hours
- Models: 2-3 hours
- Repositories: 2-3 hours
- Controllers: 3-4 hours
- Jobs: 4-5 hours
- Actions: 2-3 hours
- Services: 5-6 hours
- Routes: 1 hour
- Validation: 1 hour
- Tests: 4-6 hours
- Documentation: 2-3 hours
- **Total: 27-38 hours (~1.5-2 weeks)**

**Web Series (Medium Complexity):**
- Similar to above but may need alternative verification
- **Total: 30-40 hours (~2 weeks)**

**Podcasts/Books/Music Albums (High Complexity):**
- Similar to above but need alternative verification sources
- More unique fields and relationships
- **Total: 40-50 hours (~2.5-3 weeks)**

---

## Trade-offs

### Separate Tables vs. Polymorphic

| Aspect | Separate Tables | Polymorphic |
|--------|----------------|-------------|
| **Type Safety** | ✅ Strong (foreign keys) | ❌ Weak (no foreign keys) |
| **Performance** | ✅ Fast (direct FKs) | ⚠️ Slower (polymorphic queries) |
| **Clarity** | ✅ Easy to understand | ❌ More complex |
| **Migration** | ✅ Simple | ❌ Complex |
| **Cross-type Queries** | ❌ Harder | ✅ Easier |
| **Table Count** | ❌ Many tables | ✅ Few tables |
| **Maintenance** | ⚠️ More migrations | ✅ Fewer migrations |

**Recommendation:** Separate tables for now, consider polymorphic at 10+ types.

---

### Separate Controllers vs. Base Controller

| Aspect | Separate Controllers | Base Controller |
|--------|---------------------|-----------------|
| **Customization** | ✅ Easy | ❌ Harder |
| **Code Duplication** | ❌ High | ✅ Low |
| **Maintenance** | ❌ More files | ✅ Fewer files |
| **Complexity** | ✅ Simple | ❌ More complex |
| **Type Safety** | ✅ Strong | ⚠️ Weaker |

**Recommendation:** Start separate, create base after 3+ extensions.

---

### Separate Jobs vs. Base Job

| Aspect | Separate Jobs | Base Job |
|--------|--------------|----------|
| **Customization** | ✅ Easy | ❌ Harder |
| **Code Duplication** | ❌ High | ✅ Low |
| **Maintenance** | ❌ More files | ✅ Fewer files |
| **Complexity** | ✅ Simple | ❌ More complex |
| **Error Handling** | ❌ Inconsistent | ✅ Consistent |

**Recommendation:** Start separate, create base after 3+ extensions.

---

### Manual Implementation vs. Code Generation

| Aspect | Manual | Code Generation |
|--------|--------|-----------------|
| **Control** | ✅ Full control | ❌ Limited |
| **Customization** | ✅ Easy | ❌ Harder |
| **Speed** | ❌ Slower | ✅ Faster |
| **Complexity** | ✅ Simple | ❌ Complex |
| **Debugging** | ✅ Easy | ❌ Harder |

**Recommendation:** Manual implementation for better control and customization.

---

## Conclusion

The MovieMind API system is well-structured to support extensions with new content types. The current patterns (separate tables, models, controllers, repositories, jobs) provide a solid foundation that can be extended incrementally.

### Key Recommendations

1. **Distinguish Content Types from Genres/Filters:**
   - **Documentaries and Short Films:** Analyze whether they should be separate content types or genres/filters within Movie (see detailed comparison above)
   - **Web Series:** Analyze whether they should be separate content types or TvSeries with platform field (see detailed comparison above)
   - **Podcasts, Books, Music Albums:** These are **true separate content types** (fundamentally different structure)

2. **Decision Process:**
   - For each extension, compare Option A (Separate Content Type) vs. Option B (Genre/Filter or Field Extension)
   - Use decision criteria provided in each section to make informed choice
   - Consider: implementation time, code duplication, type safety, cross-type queries, maintenance burden

3. **Start Simple:** 
   - If Option B is chosen: Add fields to existing tables + filtering (2-3 hours)
   - If Option A is chosen: Follow existing patterns (2-3 weeks per extension)

4. **Refactor Gradually:** Introduce interfaces/traits after 2-3 true extensions, base classes after 3-4 extensions

5. **Prioritize Based on Analysis:**
   - Documentaries and Short Films: Evaluate based on decision criteria (2-3 hours if Option B, 2-3 weeks if Option A)
   - Web Series: Evaluate based on decision criteria (2-3 hours if Option B, 3-4 weeks if Option A)
   - True content types (Podcasts, Books, Music Albums): 4-6 weeks each (always Option A)

6. **Keep Separate Tables:** Maintain current approach for true content types unless we reach 10+ types

7. **Manual Implementation:** Avoid code generation for better control and customization

### Estimated Timeline

**Timeline depends on decisions made for Documentaries, Short Films, and Web Series:**

**If Option B is chosen for all (Documentaries, Short Films, Web Series):**
- **Phase 1 (Documentaries, Short Films as Movie filters):** 1 week (4-6 hours)
- **Phase 2 (Web Series as TvSeries field extension):** 1 week (2-3 hours)
- **Phase 3 (Podcasts, Books, Music Albums as separate content types):** 4-6 months (12-18 weeks total)
- **Refactoring (Interfaces, Base Classes):** 1-2 months (parallel with Phase 3)
- **Total Estimated Time:** 6-8 months

**If Option A is chosen for all (Documentaries, Short Films, Web Series):**
- **Phase 1 (Documentaries, Short Films as separate content types):** 4-6 weeks
- **Phase 2 (Web Series as separate content type):** 3-4 weeks
- **Phase 3 (Podcasts, Books, Music Albums as separate content types):** 4-6 months (12-18 weeks total)
- **Refactoring (Interfaces, Base Classes):** 1-2 months (parallel with Phase 3)
- **Total Estimated Time:** 7-11 months

**Mixed approach (some Option A, some Option B):**
- Timeline will vary based on specific decisions made
- Use individual estimates from each extension's analysis section

---

**Document Status:** Complete  
**Last Updated:** 2025-01-27  
**Next Review:** After first extension implementation


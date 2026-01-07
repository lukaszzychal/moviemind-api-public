# 📋 Task Backlog – MovieMind API

**Last updated:** 2025-11-30  
**Status:** 🔄 Active

---

## 📝 Task format
Every entry follows this structure:
- `[STATUS]` – one of `⏳ PENDING`, `🔄 IN_PROGRESS`, `✅ COMPLETED`, `❌ CANCELLED`
- `ID` – unique task identifier
- `Title` – short summary
- `Description` – inline details or link to supporting docs
- `Priority` – 🔴 High, 🟡 Medium, 🟢 Low
- `Estimated time` – optional, in hours
- `Start time` / `End time` – timestamp with minute precision
- `Duration` – automatically calculated (end − start) for `🤖` tasks
- `Execution` – who performed it: `🤖 AI Agent`, `👨‍💻 Manual`, `⚙️ Hybrid`

---

## 🎯 Active tasks

### 🤖 Prioritisation function

> **Goal:** keep a consistent playbook for analysing task importance and recommending execution order.

1. **Collect inputs:** status, priority, dependencies, risk of blocking, resource requirements.
2. **Assess importance:**
   - 🔴 critical for stability/security → execute first.
   - 🟡 medium with downstream impact → next in queue.
   - 🟢 roadmap / optional → schedule after blocking items.
3. **Check dependencies:** bump tasks that unlock other work.
4. **Leverage synergy:** group related tasks (CI, security, docs) to reduce overhead.
5. **Output:** produce an ordered list with short *why* notes (e.g. "unblocks X", "strengthens tests", "roadmap item").

> **Sample report:**  
> 1. `TASK-007` – centralises feature flags, prerequisite for Horizon protection and AI controls.  
> 2. `TASK-013` – secures Horizon once flags are in place.  
> 3. `TASK-020` – AI audit relies on stable flags and Horizon visibility.  
> …

---

## 📊 Recommended Execution Order

### 🎯 For MVP (Minimum Viable Product)

**MVP Goal:** Working API version ready for deployment on RapidAPI with core features.

#### Phase 1: Critical for stability and security (🔴 High Priority)

1. **`TASK-037` (Phase 2-3)** - Verify movie/person existence before AI generation
   - **Why:** Prevents AI hallucinations, critical for data quality
   - **Time:** 8-12h (Phase 2) + 20-30h (Phase 3)
   - **Status:** ⏳ PENDING (Phase 1 ✅ COMPLETED)

2. **`TASK-038` (Phase 2)** - Verify AI data consistency with slug
   - **Why:** Ensures data consistency, prevents incorrect generations
   - **Time:** 6-8h
   - **Status:** ⏳ PENDING (Phase 1 ✅ COMPLETED)

3. **`TASK-013`** - Horizon access configuration
   - **Why:** Security - secures Horizon dashboard in production
   - **Time:** 1-2h
   - **Status:** ✅ COMPLETED (2025-12-14)

#### Phase 2: Functional improvements (🟡 Medium Priority)

4. **`TASK-022`** - People list endpoint parity
   - **Why:** API parity - completes basic endpoints
   - **Time:** 2-3h
   - **Status:** ✅ COMPLETED (2025-12-14)

5. **`TASK-024`** - Execute baseline-locking improvement plan
   - **Why:** Stabilizes generation mechanism, prevents race conditions
   - **Time:** 4h
   - **Status:** ✅ COMPLETED (2025-12-16)
   - **Dependencies:** TASK-012 ✅, TASK-023 ✅

6. **`TASK-025`** - Standardise product vs developer feature flags
   - **Why:** Organizes flag management, supports development
   - **Time:** 1h
   - **Status:** ✅ COMPLETED

7. **`TASK-026`** - Investigate confidence fields for queued generation responses
   - **Why:** Improves UX - user sees generation confidence level
   - **Time:** 1-2h
   - **Status:** ✅ COMPLETED (2025-12-16)

#### Phase 3: Infrastructure and CI/CD (🟡 Medium Priority)

8. **`TASK-011`** - CI for staging (GHCR)
   - **Why:** Deployment automation, faster iterations
   - **Time:** 3h
   - **Status:** ✅ COMPLETED (2025-12-16)

9. **`TASK-015`** - Run Postman Newman tests in CI
   - **Why:** Automated API verification, higher quality
   - **Time:** 2h
   - **Status:** ✅ COMPLETED (2025-01-27)

10. **`TASK-019`** - Migrate Docker production image to Distroless
    - **Why:** Security - reduces attack surface
    - **Time:** 3-4h
    - **Status:** ✅ COMPLETED (2025-01-27) - Minimal Alpine implemented, Distroless deferred

#### Phase 4: Refactoring and cleanup (🟡 Medium Priority)

11. **`TASK-033`** - Remove Actor model and consolidate on Person
    - **Why:** Code organization, eliminates legacy
    - **Time:** 2-3h
    - **Status:** ✅ COMPLETED
    - **Dependencies:** TASK-032 ✅, TASK-022 ✅

12. **`TASK-032`** - Auto-create cast when generating movie
    - **Why:** Completes movie data, better UX
    - **Time:** 3h
    - **Status:** ✅ COMPLETED
    - **Dependencies:** TASK-022 ✅

13. **`TASK-028`** - Verify priority label sync from TASKS to Issues
    - **Why:** Improves workflow, better task management
    - **Time:** 0.5-1h
    - **Status:** ⏳ PENDING

14. **`TASK-029`** - Standardise tests around AAA or GWT
    - **Why:** Test standardization, better readability
    - **Time:** 2-3h
    - **Status:** ✅ COMPLETED

15. **`TASK-018`** - Extract PhpstanFixer as a Composer package
    - **Why:** Reusability, can be used in other projects
    - **Time:** 3-4h
    - **Status:** ✅ COMPLETED (with known issue - work suspended)
    - **Note:** Package extracted and published as `lukaszzychal/phpstan-fixer`, but removed from project due to Laravel `package:discover` error. Internal module `App\Support\PhpstanFixer` still available.
    - **Dependencies:** TASK-017 ✅

#### Phase 5: Documentation and analysis (🟡/🟢 Priority)

16. **`TASK-031`** - Versioning direction for AI descriptions
    - **Why:** Documents architectural decision
    - **Time:** 1-2h
    - **Status:** ✅ COMPLETED

17. **`TASK-040`** - Analysis of TOON vs JSON format for AI communication
    - **Why:** Cost optimization (token savings)
    - **Time:** 2-3h
    - **Status:** ✅ COMPLETED

18. **`TASK-030`** - Document the "three-line test" technique
    - **Why:** Technical documentation, supports TASK-029
    - **Time:** 1-2h
    - **Status:** ⏳ PENDING
    - **Dependencies:** TASK-029

---

### 🧪 For POC (Proof of Concept)

**POC Goal:** Minimal demo version showing AI generation functionality.

#### Minimal POC scope:

1. **`TASK-013`** - Horizon access configuration (security)
2. **`TASK-022`** - People list endpoint (basic functionality)
3. **`TASK-025`** - Standardise flags (simplifies management)

**Note:** Most POC tasks are already completed (TASK-001, TASK-002, TASK-003, TASK-012, TASK-023 ✅). POC is practically ready.

---

### 📋 Summary by Priority

#### 🔴 High Priority (Critical)
- `TASK-037` (Phase 2-3) - Verify existence before AI
- `TASK-038` (Phase 2) - Verify data consistency

#### 🟡 Medium Priority (Important)
- ~~`TASK-013` - Horizon configuration~~ ✅ COMPLETED
- ~~`TASK-022` - People list~~ ✅ COMPLETED
- ~~`TASK-024` - Baseline locking~~ ✅ COMPLETED
- ~~`TASK-025` - Flag standardization~~ ✅ COMPLETED
- ~~`TASK-026` - Confidence fields~~ ✅ COMPLETED
- ~~`TASK-011` - CI for staging~~ ✅ COMPLETED
- ~~`TASK-015` - Newman tests~~ ✅ COMPLETED
- ~~`TASK-019` - Docker Distroless~~ ✅ COMPLETED
- ~~`TASK-032` - Auto cast~~ ✅ COMPLETED
- ~~`TASK-033` - Remove Actor~~ ✅ COMPLETED
- ~~`TASK-028` - Issues sync~~ ✅ COMPLETED
- ~~`TASK-029` - Test standardization~~ ✅ COMPLETED
- ~~`TASK-018` - PhpstanFixer package~~ ✅ COMPLETED (with known issue - work suspended)
- `TASK-031` - Description versioning
- ~~`TASK-040` - TOON vs JSON analysis~~ ✅ COMPLETED
- ~~`TASK-041` - TV Series/Show (DDD)~~ ✅ COMPLETED
- ~~`TASK-043` - BREAKING CHANGE rule~~ ✅ COMPLETED
- ~~`TASK-027` - Duplicate generation events~~ ✅ COMPLETED

#### 🟢 Low Priority (Roadmap)
- `TASK-008` - Webhooks System
- `TASK-009` - Admin UI
- `TASK-010` - Analytics/Monitoring Dashboards
- `TASK-030` - Three-line test documentation

---

### ⏳ PENDING

---

#### `TASK-008` – Webhooks system (roadmap)
- **Status:** ⏳ PENDING
- **Priority:** 🟢 Low
- **Estimated time:** 8–10 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Implement billing/notification webhooks per roadmap
- **Details:**
  - Design webhook architecture
  - Implement endpoints
  - Add retry/error handling
  - Document behaviour
- **Dependencies:** none
- **Created:** 2025-01-27
- **Note:** roadmap item, low priority

---

#### `TASK-009` – Admin UI (roadmap)
- **Status:** ⏳ PENDING
- **Priority:** 🟢 Low
- **Estimated time:** 15–20 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Build admin panel for managing content (Nova/Breeze)
- **Details:**
  - Pick tooling (Laravel Nova, Filament, Breeze)
  - Implement admin area
  - Manage movies, people, flags
- **Dependencies:** none
- **Created:** 2025-01-27
- **Note:** roadmap item, low priority

---

#### `TASK-010` – Analytics / monitoring dashboards (roadmap)
- **Status:** ⏳ PENDING
- **Priority:** 🟢 Low
- **Estimated time:** 10–12 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Provide dashboards for job status, failures, and usage metrics
- **Details:**
  - Queue jobs status dashboard
  - Failed jobs monitoring
  - API usage & generation statistics
- **Dependencies:** none
- **Created:** 2025-01-27
- **Note:** roadmap item, low priority

---

#### `TASK-011` – CI for staging (GHCR)
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 3 h
- **Start time:** --
- **End time:** 2025-12-16
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** GitHub Actions workflow to build Docker image for staging and publish to GHCR
- **Details:** configure trigger (push/tag), authenticate to GHCR, tag image, set secrets
- **Dependencies:** none
- **Created:** 2025-11-07
- **Completed:** 2025-12-16

---

#### `TASK-013` – Horizon access configuration
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 1–2 h
- **Start time:** --
- **End time:** 2025-12-14
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Secure Horizon dashboard access outside local environments.
- **Details:**
  - Move the authorized email list to configuration/environment variables.
  - Add safeguards/tests ensuring Horizon isn't exposed in production by default.
  - Update operational documentation.
- **Dependencies:** none
- **Created:** 2025-11-08
- **Completed:** 2025-12-14

---

#### `TASK-019` – Migrate Docker production image to Distroless
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 3–4 h
- **Start time:** --
- **End time:** 2025-01-27
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Replace the Alpine-based production container with a Google Distroless image to shrink the attack surface.
- **Details:**
  - Select the appropriate Distroless base capable of running PHP-FPM, Nginx and Supervisor (multi-stage build).
  - Adjust `docker/php/Dockerfile` stages to copy runtime artifacts into the Distroless image.
  - Ensure Supervisor, Horizon and entrypoint scripts run without relying on a shell (vector-form `CMD`/`ENTRYPOINT`).
  - Update deployment docs (README, ops playbooks) to reflect the new image.
  - **Note:** Minimal Alpine implemented, Distroless deferred
- **Dependencies:** none
- **Created:** 2025-11-10
- **Completed:** 2025-01-27

---

#### `TASK-020` – Audit AI behaviour for non-existent films/people
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 2 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Verify what happens when generation is triggered for slugs that don't map to real-world movies or people.
- **Details:**
  - Review current generation jobs (`RealGenerateMovieJob`, `RealGeneratePersonJob`) for creation of fictional entities.
  - Propose/implement safeguards (e.g. configuration flag, source validation, enhanced logging) to prevent undesired records.
  - Add regression tests and update documentation (OpenAPI, README) to describe the behaviour explicitly.
  - **✅ Implemented:** `PreGenerationValidator` service, `hallucination_guard` feature flag, `EntityVerificationServiceInterface` with `TmdbVerificationService`, enhanced logging, and regression tests
- **Dependencies:** none
- **Created:** 2025-11-10
- **Completed:** --

---

#### `TASK-022` – People list endpoint parity
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 2–3 h
- **Start time:** --
- **End time:** 2025-12-14
- **Duration:** -- (AI agent will auto-calc when applicable)
- **Execution:** 🤖 AI Agent
- **Description:** Add `GET /api/v1/people` listing endpoint mirroring the data contract of the movie listing.
- **Details:**
  - Align filtering, sorting, and pagination parameters with the existing `List movies` endpoint.
  - Implement controller/resource logic plus feature tests covering the new collection response.
  - Update documentation artefacts (OpenAPI, Postman, Insomnia) and sample payloads.
- **Dependencies:** none
- **Created:** 2025-11-10
- **Completed:** 2025-12-14

---

#### `TASK-015` – Run Postman Newman tests in CI
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 2 h
- **Start time:** --
- **End time:** 2025-01-27
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Execute Postman collection as part of the CI pipeline.
- **Details:**
  - Add a Newman step to `.github/workflows/ci.yml`.
  - Provide required environment variables/secrets for CI.
  - Publish results (CLI/JUnit) and document the workflow.
- **Dependencies:** Requires up-to-date Postman environments.
- **Created:** 2025-11-08
- **Completed:** 2025-01-27

---

#### `TASK-018` – Extract PhpstanFixer as a Composer package
- **Status:** ✅ COMPLETED (with known issue - work suspended)
- **Priority:** 🟡 Medium
- **Estimated time:** 3–4 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Move the `App\Support\PhpstanFixer` module into a standalone Composer package reusable by other projects.
- **Details:**
  - Create a dedicated repository/package with namespace such as `Moviemind\PhpstanFixer`.
  - Provide `composer.json`, PSR-4 autoloading, and installation/setup documentation.
  - Replace in-project classes with the packaged dependency and adjust DI wiring.
  - Prepare publishing workflow (Packagist or private registry) and versioning guidelines.
  - **✅ Implemented:** Package extracted and published as `lukaszzychal/phpstan-fixer` on Packagist
  - **⚠️ Known Issue:** Laravel `package:discover` error (`Call to a member function make() on null`) - work suspended, package temporarily removed from project
  - **📝 Issue:** https://github.com/lukaszzychal/phpstan-fixer/issues/60
  - **💡 Current Status:** Package exists but not used in project due to Laravel compatibility issue. Internal `App\Support\PhpstanFixer` module still available.
- **Dependencies:** TASK-017 ✅
- **Created:** 2025-11-08
- **Completed:** --
- **Related documents:**
  - [`docs/knowledge/technical/PHPSTAN_FIXER_PACKAGE_DISCOVER_SOLUTION.md`](../../knowledge/technical/PHPSTAN_FIXER_PACKAGE_DISCOVER_SOLUTION.md)
  - [`docs/knowledge/technical/PHPSTAN_FIXER_REPRODUCTION_STEPS.md`](../../knowledge/technical/PHPSTAN_FIXER_REPRODUCTION_STEPS.md)
  - [`docs/knowledge/technical/PHPSTAN_FIXER_LIBRARY_SOLUTION_PROPOSAL.md`](../../knowledge/technical/PHPSTAN_FIXER_LIBRARY_SOLUTION_PROPOSAL.md)

---

#### `TASK-024` – Execute baseline-locking improvement plan
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 4 h
- **Start time:** --
- **End time:** 2025-12-16
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Implement and verify the follow-up actions listed in `docs/knowledge/technical/AI_BASELINE_LOCKING_PLAN.md`.
- **Details:**
  - Validate flag configuration (`ai_generation_baseline_locking`) on staging/production and prepare rollout SOP.
  - Extend test coverage (Mock/Real jobs) for the flag-on scenario, including cache/slug edge cases.
  - Add telemetry/logging to monitor baseline-locking mode in Horizon.
  - Produce rollout/rollback recommendation once staging validation is complete.
- **Dependencies:** TASK-012 ✅, TASK-023 ✅
- **Created:** 2025-11-10
- **Completed:** 2025-12-16

---

#### `TASK-025` – Standardise product vs developer feature flags
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 1 h
- **Start time:** --
- **End time:** --
- **Duration:** -- (AI agent will auto-calc when applicable)
- **Execution:** 🤖 AI Agent
- **Description:** Update `.cursor/rules/coding-standards.mdc` with guidance for two feature-flag classes (product vs developer) and align supporting docs.
- **Details:**
  - Introduce a subsection distinguishing product flags (long-term toggles for live functionality) from developer flags (temporary, default-off gates used while a feature is under construction).
  - Document the lifecycle for developer flags: create alongside new work, enable for testing only, remove once the feature ships.
  - Clarify when developer flags are mandatory (every new or high-risk feature that could destabilise production) and outline naming/documentation expectations.
  - Sync any related material in `docs/knowledge/reference/FEATURE_FLAGS*.md` (if updates are needed) and ensure PL/EN parity.
- **Dependencies:** none
- **Created:** 2025-11-10
- **Completed:** --

---

#### `TASK-026` – Investigate confidence fields for queued generation responses
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 1–2 h
- **Start time:** --
- **End time:** 2025-12-16
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Verify the `confidence` and `confidence_level` fields returned when show endpoints auto-trigger generation for missing entities.
- **Details:**
  - Reproduce the response for `GET /api/v1/movies/{slug}` and `GET /api/v1/people/{slug}` when the entity is absent and a job is queued.
  - Identify why `confidence` is `null` and `confidence_level` is `unknown` in the queued payload and determine the expected values.
  - Add regression tests (feature/unit) to cover the corrected behaviour and update API documentation if the contract changes.
- **Dependencies:** none
- **Created:** 2025-11-10
- **Completed:** 2025-12-16

---

#### `TASK-027` – Diagnose duplicated generation events (movies/people)
- **Status:** ✅ COMPLETED
- **Priority:** 🔴 High
- **Estimated time:** 2 h
- **Start time:** --
- **End time:** 2025-11-30
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Determine why movie and person generation events fire multiple times, causing duplicate jobs/descriptions.
- **Details:**
  - Reproduce the issue across `GET /api/v1/movies/{slug}`, `GET /api/v1/people/{slug}`, and `POST /api/v1/generate` flows.
  - Audit controllers, services, and job listeners for repeated dispatches of generation events.
  - Inspect queue/log outputs and craft a remediation plan with regression tests.
  - **✅ Implemented:** `findActiveJobForSlug()` and `buildExistingJobResponse()` in QueueMovieGenerationAction and QueuePersonGenerationAction to prevent duplicate jobs
- **Dependencies:** none
- **Created:** 2025-11-10
- **Completed:** 2025-11-30

---

#### `TASK-037` – Verify movie/person existence before AI generation
- **Status:** ✅ COMPLETED
- **Priority:** 🔴 High
- **Estimated time:** Phase 1: 4-6h (✅), Phase 2: 8-12h (✅), Phase 3: 20-30h (✅)
- **Start time:** 2025-12-01
- **End time:** --
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Implement verification that a movie/person actually exists before calling AI, preventing AI hallucinations.
- **Details:**
  - **✅ Phase 1 (COMPLETED):** Enhanced prompts with existence verification instructions (AI returns `{"error": "Movie/Person not found"}` when entity doesn't exist), error response handling in OpenAiClient and Jobs
  - **✅ Phase 2 (COMPLETED):** Pre-generation validation heuristics (`PreGenerationValidator`), `hallucination_guard` feature flag activated, extended heuristics (release year, birth date, slug similarity, suspicious patterns)
  - **✅ Phase 3 (COMPLETED):** TMDb API integration (`TmdbVerificationService`), cache verification results (24h TTL), monitoring via logging
- **Dependencies:** none
- **Created:** 2025-11-30
- **Completed:** --
- **Related documents:** 
  - [`docs/knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.en.md`](../../knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.en.md)
  - [`docs/knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md`](../../knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md)

---

#### `TASK-038` – Verify AI data consistency with slug
- **Status:** ✅ COMPLETED
- **Priority:** 🔴 High
- **Estimated time:** Phase 1: 3-4h (✅), Phase 2: 6-8h (✅)
- **Start time:** 2025-12-01
- **End time:** --
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Implement validation that AI-generated data actually belongs to the movie/person specified by the slug, preventing data inconsistencies.
- **Details:**
  - **✅ Phase 1 (COMPLETED):** Implement `AiDataValidator` service with validation heuristics, validate if title/name matches slug (Levenshtein + fuzzy matching), validate if release year/birth date are reasonable (1888-current year+2), reject data if inconsistency > threshold (0.6), integration with Jobs (RealGenerateMovieJob, RealGeneratePersonJob) with `hallucination_guard` feature flag
  - **✅ Phase 2 (COMPLETED):** Extended heuristics (director-genre consistency, genre-year consistency, birthplace-birthdate consistency for persons), logging and monitoring of suspicious cases (even when passed validation), threshold tuning (MIN_SIMILARITY_THRESHOLD = 0.6, LOW_SIMILARITY_LOG_THRESHOLD = 0.7)
- **Dependencies:** none (can be implemented in parallel with TASK-037)
- **Created:** 2025-11-30
- **Completed:** --
- **Related documents:** 
  - [`docs/knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.en.md`](../../knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.en.md)
  - [`docs/knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md`](../../knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md)

---

#### `TASK-040` – Analysis of TOON vs JSON format for AI communication
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 2-3 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Analysis of TOON (Token-Oriented Object Notation) format as an alternative to JSON for AI communication. TOON can save 30-60% tokens compared to JSON.
- **Details:**
  - Analyze TOON format and its application in AI communication
  - Compare TOON vs JSON in terms of token savings
  - Assess TOON usefulness for MovieMind API
  - Prepare recommendations for TOON usage in the project
  - **✅ Implemented:** TOON format support in OpenAiClient, analysis documentation, and AI metrics monitoring system
- **Dependencies:** none
- **Created:** 2025-11-30
- **Completed:** --
- **Related documents:**
  - [`docs/knowledge/technical/TOON_VS_JSON_ANALYSIS.md`](../../knowledge/technical/TOON_VS_JSON_ANALYSIS.md)
  - [`docs/knowledge/technical/TOON_VS_JSON_ANALYSIS.en.md`](../../knowledge/technical/TOON_VS_JSON_ANALYSIS.en.md)

---

#### `TASK-041` – Add TV series and programs (DDD approach)
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 30-40 hours
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Implementation of separate domain entities Series and TVShow according to Domain-Driven Design. Movie and Series/TV Show are different domain concepts - Movie has no episodes, Series has.
- **Details:**
  - Create `Series` model with `series` table:
    - Fields: `title`, `slug`, `start_year`, `end_year`, `network`, `seasons`, `episodes`, `director`, `genres`, `default_description_id`
    - Relations: `descriptions()`, `people()` (series_person), `genres()`
  - Create `TVShow` model with `tv_shows` table:
    - Fields: `title`, `slug`, `start_year`, `end_year`, `network`, `format`, `episodes`, `runtime_per_episode`, `genres`, `default_description_id`
    - Relations: `descriptions()`, `people()` (tv_show_person), `genres()`
  - Create common interfaces/trait:
    - `DescribableContent` interface (for descriptions)
    - `Sluggable` trait (for slug generation/parsing)
    - `HasPeople` interface (for relations with Person)
  - Create `SeriesDescription` and `TVShowDescription` models (or polymorphic `ContentDescription`)
  - Create `SeriesRepository` and `TVShowRepository` (shared logic through interfaces)
  - Create `SeriesController` and `TVShowController` (shared logic through interfaces)
  - Create jobs: `RealGenerateSeriesJob`, `MockGenerateSeriesJob`, `RealGenerateTVShowJob`, `MockGenerateTVShowJob`
  - Update `GenerateController` (handle SERIES, TV_SHOW)
  - Create enum `EntityType` (MOVIE, SERIES, TV_SHOW, PERSON)
  - Update OpenAPI schema
  - Migrations for tables `series`, `tv_shows`, `series_person`, `tv_show_person`, `series_descriptions`, `tv_show_descriptions`
  - Tests (automated and manual)
  - Documentation
  - **✅ Implemented:** TvSeries and TvShow models, controllers, repositories, jobs, migrations, routes, and full API support
- **Dependencies:** none
- **Created:** 2025-01-09
- **Completed:** --
- **Note:** Implemented as TASK-051 in commits, but functionality matches TASK-041 requirements
---

#### `TASK-042` – Analysis of possible extensions (types and kinds)
- **Status:** ✅ COMPLETED
- **Priority:** 🟢 Low
- **Estimated time:** 4-6 hours
- **Start time:** 2025-01-27
- **End time:** 2025-01-27
- **Duration:** ~5 hours
- **Execution:** 🤖 AI Agent
- **Description:** Analysis and documentation of possible system extensions with new content types and kinds.
- **Details:**
  - ✅ Analyzed current structure (Movie, Person, Series, TVShow) - models, migrations, relationships, patterns
  - ✅ Identified and analyzed 6 potential extensions (Documentaries, Short Films, Web Series, Podcasts, Books, Music Albums)
  - ✅ Analyzed impact on API, database, jobs, and services for each extension
  - ✅ Analyzed common interfaces and refactoring possibilities (interfaces, traits, base classes, polymorphic relationships)
  - ✅ Documented recommendations and alternatives with trade-offs
  - ✅ Created comprehensive analysis document in `docs/knowledge/technical/CONTENT_TYPES_EXTENSION_ANALYSIS.md`
- **Dependencies:** none
- **Created:** 2025-01-09
- **Completed:** 2025-01-27
- **Related documents:**
  - [`docs/knowledge/technical/CONTENT_TYPES_EXTENSION_ANALYSIS.md`](../../knowledge/technical/CONTENT_TYPES_EXTENSION_ANALYSIS.md)
---

#### `TASK-043` – Implement BREAKING CHANGE detection rule
- **Status:** ✅ COMPLETED
- **Priority:** 🔴 High
- **Estimated time:** 2-3 hours
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Add rule to cursor/rules requiring BREAKING CHANGE analysis before making changes. Rule requires treating changes as if they were in production with full data.
- **Details:**
  - Create `.cursor/rules/breaking-change-detection.mdc`
  - Rule: treat changes as if they were in production with full data
  - Require impact analysis before implementation (data impact, API impact, functionality impact)
  - Analyze alternatives and safe change process (migrations, backward compatibility, etc.)
  - Process: STOP → analysis → documentation → alternatives → safe process → approval
  - **✅ Implemented:** `.cursor/rules/030-breaking-changes.mdc` with full BREAKING CHANGE detection workflow
- **Dependencies:** none
- **Created:** 2025-01-09
- **Completed:** --
---

#### `TASK-028` – Verify priority label sync from TASKS to Issues
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 0.5–1 h
- **Start time:** 2025-01-27
- **End time:** 2025-01-27
- **Duration:** ~45m
- **Execution:** 🤖 AI Agent
- **Description:** Confirm whether the `docs/issue/TASKS.md` → GitHub Issues sync workflow can attach labels reflecting each task's priority.
- **Details:**
  - ✅ Reviewed the current sync workflow (`scripts/sync_tasks.py`).
  - ✅ Added `extract_priority()` function to extract priority from TASKS.md.
  - ✅ Implemented priority mapping:
    - 🔴 High → `priority-high` (color: #d73a4a)
    - 🟡 Medium → `priority-medium` (color: #fbca04)
    - 🟢 Low → `priority-low` (color: #0e8a16)
  - ✅ Updated `create_issue()` and `update_issue()` to automatically add priority labels.
  - ✅ Labels are automatically created during sync.
- **Dependencies:** none
- **Created:** 2025-11-10
- **Completed:** 2025-01-27

---

#### `TASK-029` – Standardise tests around AAA or GWT
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 2–3 h
- **Start time:** --
- **End time:** --
- **Duration:** -- (AI agent will auto-calc when applicable)
- **Execution:** 🤖 AI Agent
- **Description:** Analyse and unify the structure of unit/feature tests, choosing between Arrange-Act-Assert (AAA) and Given-When-Then (GWT).
- **Details:**
  - Gather reference material covering AAA and GWT (pros/cons, PHP or Laravel-oriented examples).
  - Produce a concise comparison and recommendation tailored to MovieMind API.
  - Draft a refactor plan for existing tests (file order, scope, effort).
  - Update PL/EN testing guidelines and add supporting documentation if warranted.
  - Evaluate the "three-line test" helper approach (Given/When/Then expressed via named helper methods) as a candidate pattern.
- **Dependencies:** none
- **Created:** 2025-11-10
- **Completed:** --
- **Educational documentation:**
  - ✅ Comprehensive tutorial created: [`docs/knowledge/tutorials/TEST_PATTERNS_AAA_GWT_TUTORIAL.md`](../../knowledge/tutorials/TEST_PATTERNS_AAA_GWT_TUTORIAL.md)
  - Tutorial includes: introduction to patterns, AAA vs GWT comparison, "three-line test" technique, examples from MovieMind API, migration guide, recommendations, and best practices

---

#### `TASK-030` – Document the "three-line test" technique
- **Status:** ✅ COMPLETED
- **Priority:** 🟢 Low
- **Estimated time:** 1–2 h
- **Start time:** 2025-01-07
- **End time:** 2025-01-07
- **Duration:** ~2h
- **Execution:** 🤖 AI Agent
- **Description:** Collect references and produce a knowledge document describing the practice of structuring tests with only three helper calls (Given/When/Then).
- **Details:**
  - ✅ Gathered sources including test checklist (Level L) and AAA cheat sheet from provided images
  - ✅ Created PL/EN tutorial in `docs/knowledge/tutorials/` explaining the technique, code samples, benefits, and trade-offs
  - ✅ Documented naming conventions for helper methods (`given*`, `when*`, `then*`, `and*`) and PHPUnit integration
  - ✅ Linked the document with `TASK-029` and included references to test patterns documentation
  - ✅ Analyzed and integrated information from two provided images:
    - Test Checklist at Level L (TDD, independent tests, fail-fast principles)
    - AAA Test Structure Cheat Sheet (detailed test structure with comments)
- **Dependencies:** `TASK-029` ✅
- **Created:** 2025-11-10
- **Completed:** 2025-01-07
- **Related documents:**
  - [`docs/knowledge/tutorials/THREE_LINE_TEST_TECHNIQUE.pl.md`](../../knowledge/tutorials/THREE_LINE_TEST_TECHNIQUE.pl.md)
  - [`docs/knowledge/tutorials/THREE_LINE_TEST_TECHNIQUE.en.md`](../../knowledge/tutorials/THREE_LINE_TEST_TECHNIQUE.en.md)

---

### 🔄 IN_PROGRESS

#### `TASK-023` – OpenAI integration repair
- **Status:** ✅ COMPLETED
- **Priority:** 🔴 High
- **Estimated time:** 3 h
- **Start time:** 2025-11-10 14:00
- **End time:** 2025-12-01
- **Duration:** ~20d (including TASK-037, TASK-038, TASK-039)
- **Execution:** 🤖 AI Agent
- **Description:** Restore and harden the OpenAI integration.
- **Details:**
  - ✅ Diagnose communication issues (timeouts, HTTP responses, rate limits) - fixed
  - ✅ Verify configuration secrets (`OPENAI_API_KEY`, endpoints, models) - verified and working
  - ✅ Update the services and fallbacks that mediate OpenAI traffic within the API - updated (OpenAiClient)
  - ✅ Add unit/feature tests confirming the integration works end-to-end - all tests passing (15 passed)
  - ✅ Fixed JSON Schema errors (removed oneOf, improved schemas)
  - ✅ Manually tested with AI_SERVICE=real - working correctly
- **Dependencies:** none
- **Created:** 2025-11-10
- **Completed:** 2025-12-01

#### `TASK-021` – Fix duplicated generation events
- **Status:** ✅ COMPLETED
- **Priority:** 🔴 High
- **Estimated time:** 2 h
- **Start time:** 2025-11-10 16:05
- **End time:** 2025-11-30
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Identify and eliminate the cause of multiple jobs/descriptions being created for the movie generation flow.
- **Details:**
  - Reproduce the bug and audit event sources (controller, listener, job).
  - Adjust event/job triggering so each description is generated exactly once.
  - Add regression tests (unit/feature) preventing duplicate descriptions.
  - Verify side effects (Horizon queue, database writes) and update docs if needed.
  - **✅ Implemented:** Job deduplication via `JobStatusService::findActiveJobForSlug()` and generation slot management
- **Dependencies:** none
- **Created:** 2025-11-10
- **Completed:** 2025-11-30

---

## ✅ Completed tasks

### `TASK-007` – Feature flag hardening
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 2–3 h
- **Start time:** 2025-11-10 10:36
- **End time:** 2025-11-10 11:08
- **Duration:** 00h32m (auto)
- **Execution:** 🤖 AI Agent
- **Description:** Centralise flag configuration and document admin endpoints
- **Details:**
  - Consolidate config in `config/pennant.php`
  - Produce feature-flag docs
  - Extend admin endpoints for toggling (guarded)
- **Scope completed:**
  - Introduced `BaseFeature` and updated all `app/Features/*` classes to source defaults from configuration.
  - Added `config/pennant.php` with metadata (categories, defaults, `togglable`) and hardened admin toggles in `FlagController`.
  - Expanded tests (`AdminFlagsTest`), refreshed API docs (OpenAPI, Postman) and published reference entry `docs/knowledge/reference/FEATURE_FLAGS*.md`.
- **Dependencies:** none
- **Created:** 2025-01-27

---

### `TASK-006` – Improve Postman collection
- **Status:** ✅ COMPLETED
- **Priority:** 🟢 Low
- **Estimated time:** 1–2 h
- **Start time:** 2025-11-10 09:37  
- **End time:** 2025-11-10 09:51  
- **Duration:** 00h14m (auto)
- **Execution:** 🤖 AI Agent
- **Description:** Add sample responses, per-request tests, and environment templates for local/staging usage.
- **Scope completed:**
  - Extended collection tests to assert `description_id`/`bio_id`, added collection variables, and shipped dedicated `selected` requests.
  - Refreshed example payloads and the job status response while bumping the collection version to `1.2.0`.
  - Updated documentation (`docs/postman/README.md`, `docs/postman/README.en.md`) to explain variant flows and the new variables.

### `TASK-014` – Fix movie HATEOAS links
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 1–2 h
- **Start time:** 2025-11-09 12:45  
- **End time:** 2025-11-09 13:25  
- **Duration:** 00h40m (auto)
- **Execution:** 🤖 AI Agent
- **Description:** Corrected movie `_links.people` so they match API relations and documentation.
- **Details:**
  - Sorted people links by `billing_order` in `HateoasService`.
  - Updated Postman collection and server status docs to reflect the array of person links.
  - Expanded `HateoasTest` feature coverage to assert `_links.people` structure.
- **Dependencies:** none
- **Created:** 2025-11-08

### `TASK-012` – Lock + multi-description handling for generation
- **Status:** ✅ COMPLETED
- **Priority:** 🔴 High
- **Estimated time:** 4–5 h
- **Start time:** 2025-11-10 08:37
- **End time:** 2025-11-10 09:06
- **Duration:** 00h29m (auto)
- **Execution:** 🤖 AI Agent
- **Description:** Prevent race conditions during concurrent generation and support multiple descriptions per entity.
- **Details:**
  - Added Redis-backed locks and baseline guards to movie/person generation jobs so only the first finisher updates the default description while others persist as alternates.
  - Extended `POST /api/v1/generate` responses with `existing_id` plus `description_id`/`bio_id` hints for regeneration tracking and updated unit + feature coverage.
  - Enabled `GET /api/v1/movies/{slug}` and `/api/v1/people/{slug}` to accept `description_id`/`bio_id` query params with cache isolation per variant and documented the new behaviour.
- **Dependencies:** Requires functioning queues and description storage.
- **Created:** 2025-11-08

### `TASK-002` – Verify queue workers & Horizon
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Estimated time:** 2–3 h
- **Start time:** 2025-11-09 13:40  
- **End time:** 2025-11-09 15:05  
- **Duration:** 01h25m (auto)
- **Execution:** 🤖 AI Agent
- **Description:** Hardened Horizon and queue worker configuration & documentation.
- **Details:**
  - Aligned worker retries/timeouts via `.env`-driven `config/horizon.php`.
  - Added configurable access control (`HORIZON_ALLOWED_EMAILS`, `HORIZON_AUTH_BYPASS_ENVS`).
  - Refreshed documentation and verification report (`docs/tasks/HORIZON_QUEUE_WORKERS_VERIFICATION.md`, `docs/knowledge/tutorials/HORIZON_SETUP.md`).
- **Dependencies:** none
- **Created:** 2025-01-27

### `TASK-000` – People list endpoint with role filtering
- **Status:** ✅ COMPLETED
- **Priority:** 🔴 High
- **Finished:** 2025-01-27
- **Start time:** (to fill in)  
- **End time:** (to fill in)  
- **Duration:** (difference if available)
- **Execution:** (e.g. 👨‍💻 Manual / 🤖 AI Agent / ⚙️ Hybrid)
- **Description:** Added `GET /api/v1/people` with role filters (ACTOR, DIRECTOR, etc.)
- **Details:** Implemented in `PersonController::index()` and `PersonRepository::searchPeople()`

---

### `TASK-001` – API controller refactor (SOLID)
- **Status:** ✅ COMPLETED
- **Priority:** 🔴 High
- **Finished:** 2025-11-07
- **Start time:** 2025-11-07 21:45  
- **End time:** 2025-11-07 22:30  
- **Duration:** 00h45m (auto)
- **Execution:** 🤖 AI Agent
- **Description:** Refactored controllers following SOLID and Laravel best practices
- **Details:** [Polish doc](../pl/REFACTOR_CONTROLLERS_SOLID.md) / [English summary](./REFACTOR_CONTROLLERS_SOLID.en.md)
- **Scope completed:** Added `MovieResource`, `PersonResource`, `MovieDisambiguationService`; refactored `Movie`, `Person`, `Generate`, `Jobs` controllers; updated unit tests & docs.

---

### `TASK-003` – Introduce Redis caching for endpoints
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Finished:** 2025-11-08
- **Start time:** 2025-11-08  
- **End time:** 2025-11-08  
- **Duration:** 00h25m (auto)
- **Execution:** 🤖 AI Agent
- **Description:** Added response caching for `GET /api/v1/movies/{slug}` and `GET /api/v1/people/{slug}` with proper invalidation.
- **Details:** Updated controllers, queue jobs, and feature tests to use Redis caching, TTL, and cache eviction after generation.

---

### `TASK-004` – Update README.md (Symfony → Laravel)
- **Status:** ✅ COMPLETED
- **Priority:** 🟢 Low
- **Finished:** 2025-11-08
- **Start time:** 2025-11-08  
- **End time:** 2025-11-08  
- **Duration:** 00h10m (auto)
- **Execution:** 🤖 AI Agent
- **Description:** Refreshed root README files (EN/PL) to highlight Laravel 12 stack, new Quick Start, and testing workflow.
- **Details:** Updated badges, docker compose commands, `php artisan test`, and Horizon guidance.

---

### `TASK-005` – Review & update OpenAPI spec
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Finished:** 2025-11-08
- **Start time:** 2025-11-08  
- **End time:** 2025-11-08  
- **Duration:** 00h45m (auto)
- **Execution:** 🤖 AI Agent
- **Description:** Brought `docs/openapi.yaml` up to date and linked it from `api/README.md`.
- **Details:** Added realistic response examples, expanded schemas (jobs, feature flags, generation flows), and clarified status codes.

---

### `TASK-016` – PHPStan auto-fix tool
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Finished:** 2025-11-08 20:10
- **Start time:** 2025-11-08 19:55  
- **End time:** 2025-11-08 20:10  
- **Duration:** 00h15m
- **Execution:** 🤖 AI Agent
- **Description:** Delivered the `phpstan:auto-fix` command that parses PHPStan logs and suggests/applies code fixes offline.
- **Details:**
  - Introduced the `App\Support\PhpstanFixer` module with log parser, orchestration service, and initial fix strategies (`UndefinedPivotPropertyFixer`, `MissingParamDocblockFixer`).
  - Command supports `suggest` and `apply` modes and accepts pre-generated JSON logs, presenting results in a table.
  - Added unit and feature coverage using dedicated fixtures.
- **Documentation:** [`docs/tasks/TASK_016_PHPSTAN_AUTO_FIX.md`](../../tasks/TASK_016_PHPSTAN_AUTO_FIX.md), [`docs/tasks/TASK_016_PHPSTAN_AUTO_FIX.en.md`](../../tasks/TASK_016_PHPSTAN_AUTO_FIX.en.md)

---

### `TASK-017` – Extend PHPStan fixer with additional strategies
- **Status:** ✅ COMPLETED
- **Priority:** 🟡 Medium
- **Finished:** 2025-11-08 20:55
- **Start time:** 2025-11-08 20:20  
- **End time:** 2025-11-08 20:55  
- **Duration:** 00h35m
- **Execution:** 🤖 AI Agent
- **Description:** Expanded the `PhpstanFixer` module with extra strategies and refreshed the documentation.
- **Details:**
  - Implemented `MissingReturnDocblockFixer`, `MissingPropertyDocblockFixer`, and `CollectionGenericDocblockFixer`.
  - Updated the command wiring/DI, produced extended PHPStan JSON fixtures, and added unit + feature coverage.
  - Revised task documentation (`docs/tasks/TASK_016_PHPSTAN_AUTO_FIX*.md`) to reflect the completed checklist.
- **Documentation:** [`docs/tasks/TASK_016_PHPSTAN_AUTO_FIX.md`](../../tasks/TASK_016_PHPSTAN_AUTO_FIX.md), [`docs/tasks/TASK_016_PHPSTAN_AUTO_FIX.en.md`](../../tasks/TASK_016_PHPSTAN_AUTO_FIX.en.md)

---
## 📚 Templates

See [`TASK_TEMPLATE.pl.md`](../pl/TASK_TEMPLATE.md) or [`TASK_TEMPLATE.md`](./TASK_TEMPLATE.md) for the canonical structure.

---

## 🔄 Working with the AI agent

1. Open the backlog (PL or EN).  
2. Pick a `⏳ PENDING` item and set it to `🔄 IN_PROGRESS`.  
3. Read the detailed doc (if linked).  
4. Implement the task.  
5. When finished, mark `✅ COMPLETED`, fill timestamps, move to “Completed”, and update “Last updated”.

---

## 📊 Stats

- **Active:** 15  
- **Completed:** 8  
- **Cancelled:** 0  
- **In progress:** 0

---

**Last updated:** 2025-01-27 (TASK-011, TASK-013, TASK-015, TASK-018, TASK-019, TASK-020, TASK-022, TASK-024, TASK-025, TASK-026, TASK-027, TASK-029, TASK-031, TASK-032, TASK-033, TASK-037, TASK-038, TASK-040, TASK-041, TASK-043: status update to COMPLETED)

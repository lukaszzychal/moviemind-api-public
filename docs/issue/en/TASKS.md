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
5. **Output:** produce an ordered list with short *why* notes (e.g. “unblocks X”, “strengthens tests”, “roadmap item”).

> **Sample report:**  
> 1. `TASK-007` – centralises feature flags, prerequisite for Horizon protection and AI controls.  
> 2. `TASK-013` – secures Horizon once flags are in place.  
> 3. `TASK-020` – AI audit relies on stable flags and Horizon visibility.  
> …

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
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 3 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** GitHub Actions workflow to build Docker image for staging and publish to GHCR
- **Details:** configure trigger (push/tag), authenticate to GHCR, tag image, set secrets
- **Dependencies:** none
- **Created:** 2025-11-07

---

#### `TASK-013` – Horizon access configuration
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 1–2 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Secure Horizon dashboard access outside local environments.
- **Details:**
  - Move the authorized email list to configuration/environment variables.
  - Add safeguards/tests ensuring Horizon isn’t exposed in production by default.
  - Update operational documentation.
- **Dependencies:** none
- **Created:** 2025-11-08

---

#### `TASK-019` – Migrate Docker production image to Distroless
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 3–4 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Replace the Alpine-based production container with a Google Distroless image to shrink the attack surface.
- **Details:**
  - Select the appropriate Distroless base capable of running PHP-FPM, Nginx and Supervisor (multi-stage build).
  - Adjust `docker/php/Dockerfile` stages to copy runtime artifacts into the Distroless image.
  - Ensure Supervisor, Horizon and entrypoint scripts run without relying on a shell (vector-form `CMD`/`ENTRYPOINT`).
  - Update deployment docs (Railway, README, ops playbooks) to reflect the new image.
- **Dependencies:** none
- **Created:** 2025-11-10

---

#### `TASK-020` – Audit AI behaviour for non-existent films/people
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 2 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Verify what happens when generation is triggered for slugs that don’t map to real-world movies or people.
- **Details:**
  - Review current generation jobs (`RealGenerateMovieJob`, `RealGeneratePersonJob`) for creation of fictional entities.
  - Propose/implement safeguards (e.g. configuration flag, source validation, enhanced logging) to prevent undesired records.
  - Add regression tests and update documentation (OpenAPI, README) to describe the behaviour explicitly.
- **Dependencies:** none
- **Created:** 2025-11-10

---

#### `TASK-022` – People list endpoint parity
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 2–3 h
- **Start time:** --
- **End time:** --
- **Duration:** -- (AI agent will auto-calc when applicable)
- **Execution:** TBD
- **Description:** Add `GET /api/v1/people` listing endpoint mirroring the data contract of the movie listing.
- **Details:**
  - Align filtering, sorting, and pagination parameters with the existing `List movies` endpoint.
  - Implement controller/resource logic plus feature tests covering the new collection response.
  - Update documentation artefacts (OpenAPI, Postman, Insomnia) and sample payloads.
- **Dependencies:** none
- **Created:** 2025-11-10

---

#### `TASK-015` – Run Postman Newman tests in CI
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 2 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Execute Postman collection as part of the CI pipeline.
- **Details:**
  - Add a Newman step to `.github/workflows/ci.yml`.
  - Provide required environment variables/secrets for CI.
  - Publish results (CLI/JUnit) and document the workflow.
- **Dependencies:** Requires up-to-date Postman environments.
- **Created:** 2025-11-08

---

#### `TASK-018` – Extract PhpstanFixer as a Composer package
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 3–4 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Move the `App\Support\PhpstanFixer` module into a standalone Composer package reusable by other projects.
- **Details:**
  - Create a dedicated repository/package with namespace such as `Moviemind\PhpstanFixer`.
  - Provide `composer.json`, PSR-4 autoloading, and installation/setup documentation.
  - Replace in-project classes with the packaged dependency and adjust DI wiring.
  - Prepare publishing workflow (Packagist or private registry) and versioning guidelines.
- **Dependencies:** TASK-017
- **Created:** 2025-11-08

---

#### `TASK-024` – Execute baseline-locking improvement plan
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 4 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Implement and verify the follow-up actions listed in `docs/knowledge/technical/AI_BASELINE_LOCKING_PLAN.md`.
- **Details:**
  - Validate flag configuration (`ai_generation_baseline_locking`) on staging/production and prepare rollout SOP.
  - Extend test coverage (Mock/Real jobs) for the flag-on scenario, including cache/slug edge cases.
  - Add telemetry/logging to monitor baseline-locking mode in Horizon.
  - Produce rollout/rollback recommendation once staging validation is complete.
- **Dependencies:** TASK-012, TASK-023
- **Created:** 2025-11-10

---

#### `TASK-025` – Standardise product vs developer feature flags
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 1 h
- **Start time:** --
- **End time:** --
- **Duration:** -- (AI agent will auto-calc when applicable)
- **Execution:** TBD
- **Description:** Update `.cursor/rules/coding-standards.mdc` with guidance for two feature-flag classes (product vs developer) and align supporting docs.
- **Details:**
  - Introduce a subsection distinguishing product flags (long-term toggles for live functionality) from developer flags (temporary, default-off gates used while a feature is under construction).
  - Document the lifecycle for developer flags: create alongside new work, enable for testing only, remove once the feature ships.
  - Clarify when developer flags are mandatory (every new or high-risk feature that could destabilise production) and outline naming/documentation expectations.
  - Sync any related material in `docs/knowledge/reference/FEATURE_FLAGS*.md` (if updates are needed) and ensure PL/EN parity.
- **Dependencies:** none
- **Created:** 2025-11-10

---

#### `TASK-026` – Investigate confidence fields for queued generation responses
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 1–2 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Verify the `confidence` and `confidence_level` fields returned when show endpoints auto-trigger generation for missing entities.
- **Details:**
  - Reproduce the response for `GET /api/v1/movies/{slug}` and `GET /api/v1/people/{slug}` when the entity is absent and a job is queued.
  - Identify why `confidence` is `null` and `confidence_level` is `unknown` in the queued payload and determine the expected values.
  - Add regression tests (feature/unit) to cover the corrected behaviour and update API documentation if the contract changes.
- **Dependencies:** none
- **Created:** 2025-11-10

---

#### `TASK-027` – Diagnose duplicated generation events (movies/people)
- **Status:** ⏳ PENDING
- **Priority:** 🔴 High
- **Estimated time:** 2 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Determine why movie and person generation events fire multiple times, causing duplicate jobs/descriptions.
- **Details:**
  - Reproduce the issue across `GET /api/v1/movies/{slug}`, `GET /api/v1/people/{slug}`, and `POST /api/v1/generate` flows.
  - Audit controllers, services, and job listeners for repeated dispatches of generation events.
  - Inspect queue/log outputs and craft a remediation plan with regression tests.
- **Dependencies:** none
- **Created:** 2025-11-10

---

#### `TASK-037` – Verify movie/person existence before AI generation
- **Status:** ✅ COMPLETED (Phase 1), ⏳ PENDING (Phase 2-3)
- **Priority:** 🔴 High
- **Estimated time:** Phase 1: 4-6h (✅), Phase 2: 8-12h (⏳), Phase 3: 20-30h (⏳)
- **Start time:** 2025-12-01
- **End time:** 2025-12-01 (Phase 1)
- **Duration:** ~5h (Phase 1)
- **Execution:** 🤖 AI Agent
- **Description:** Implement verification that a movie/person actually exists before calling AI, preventing AI hallucinations.
- **Details:**
  - **✅ Phase 1 (COMPLETED):** Enhanced prompts with existence verification instructions (AI returns `{"error": "Movie/Person not found"}` when entity doesn't exist), error response handling in OpenAiClient and Jobs
  - **⏳ Phase 2 (PENDING):** Pre-generation validation heuristics (PreGenerationValidator), activate `hallucination_guard` feature flag, extended heuristics (release year, birth date, slug similarity, suspicious patterns)
  - **⏳ Phase 3 (PENDING):** Optional integration with TMDb/OMDb API (feature flag), cache verification results, monitoring and dashboard
- **Dependencies:** none
- **Created:** 2025-11-30
- **Completed (Phase 1):** 2025-12-01
- **Related documents:** 
  - [`docs/knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.en.md`](../../knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.en.md)
  - [`docs/knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md`](../../knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md)

---

#### `TASK-038` – Verify AI data consistency with slug
- **Status:** ✅ COMPLETED (Phase 1), ⏳ PENDING (Phase 2)
- **Priority:** 🔴 High
- **Estimated time:** Phase 1: 3-4h (✅), Phase 2: 6-8h (⏳)
- **Start time:** 2025-12-01
- **End time:** 2025-12-01 (Phase 1)
- **Duration:** ~4h (Phase 1)
- **Execution:** 🤖 AI Agent
- **Description:** Implement validation that AI-generated data actually belongs to the movie/person specified by the slug, preventing data inconsistencies.
- **Details:**
  - **✅ Phase 1 (COMPLETED):** Implement `AiDataValidator` service with validation heuristics, validate if title/name matches slug (Levenshtein + fuzzy matching), validate if release year/birth date are reasonable (1888-current year+2), reject data if inconsistency > threshold (0.6), integration with Jobs (RealGenerateMovieJob, RealGeneratePersonJob) with `hallucination_guard` feature flag
  - **⏳ Phase 2 (PENDING):** Extended heuristics (check if director matches genre, geography for persons, genre consistency with year), logging and monitoring of suspicious cases (even when passed validation), dashboard/metrics for AI data quality, threshold tuning based on production data
- **Dependencies:** none (can be implemented in parallel with TASK-037)
- **Created:** 2025-11-30
- **Completed (Phase 1):** 2025-12-01
- **Related documents:** 
  - [`docs/knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.en.md`](../../knowledge/technical/AI_VALIDATION_AND_HALLUCINATION_PREVENTION.en.md)
  - [`docs/knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md`](../../knowledge/technical/TASK_037_038_ANALYSIS_AND_RECOMMENDATIONS.md)

---

#### `TASK-028` – Verify priority label sync from TASKS to Issues
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 0.5–1 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Confirm whether the `docs/issue/TASKS.md` → GitHub Issues sync workflow can attach labels reflecting each task's priority.
- **Details:**
  - Review the current sync workflow to see if priority metadata is transmitted.
  - Define mapping between priority icons (`🔴/🟡/🟢`) and GitHub Issue labels.
  - Propose required adjustments (if any) and document the updated process.
- **Dependencies:** none
- **Created:** 2025-11-10

---

#### `TASK-029` – Standardise tests around AAA or GWT
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 2–3 h
- **Start time:** --
- **End time:** --
- **Duration:** -- (AI agent will auto-calc when applicable)
- **Execution:** TBD
- **Description:** Analyse and unify the structure of unit/feature tests, choosing between Arrange-Act-Assert (AAA) and Given-When-Then (GWT).
- **Details:**
  - Gather reference material covering AAA and GWT (pros/cons, PHP or Laravel-oriented examples).
  - Produce a concise comparison and recommendation tailored to MovieMind API.
  - Draft a refactor plan for existing tests (file order, scope, effort).
  - Update PL/EN testing guidelines and add supporting documentation if warranted.
  - Evaluate the “three-line test” helper approach (Given/When/Then expressed via named helper methods) as a candidate pattern.
- **Dependencies:** none
- **Created:** 2025-11-10

---

#### `TASK-030` – Document the “three-line test” technique
- **Status:** ⏳ PENDING
- **Priority:** 🟢 Low
- **Estimated time:** 1–2 h
- **Start time:** --
- **End time:** --
- **Duration:** -- (AI agent will auto-calc when applicable)
- **Execution:** TBD
- **Description:** Collect references and produce a knowledge document describing the practice of structuring tests with only three helper calls (Given/When/Then).
- **Details:**
  - Gather sources (articles, PHP/Laravel examples) discussing “three-line” / “three-act” tests.
  - Create a PL/EN tutorial in `docs/knowledge/tutorials/` explaining the technique, code samples, benefits, and trade-offs.
  - Suggest naming conventions for helper methods (`given*`, `when*`, `then*`) and guidance for PHPUnit integration.
  - Link the document with `TASK-029` and update testing guidelines once the approach is adopted.
- **Dependencies:** `TASK-029`
- **Created:** 2025-11-10

---

### 🔄 IN_PROGRESS

#### `TASK-023` – OpenAI integration repair
- **Status:** 🔄 IN_PROGRESS
- **Priority:** 🔴 High
- **Estimated time:** 3 h
- **Start time:** 2025-11-10 14:00
- **End time:** --
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Restore and harden the OpenAI integration.
- **Details:**
  - Diagnose communication issues (timeouts, HTTP responses, rate limits).
  - Verify configuration secrets (`OPENAI_API_KEY`, endpoints, models).
  - Update the services and fallbacks that mediate OpenAI traffic within the API.
  - Add unit/feature tests confirming the integration works end-to-end.
- **Dependencies:** none
- **Created:** 2025-11-10

#### `TASK-021` – Fix duplicated generation events
- **Status:** 🔄 IN_PROGRESS
- **Priority:** 🔴 High
- **Estimated time:** 2 h
- **Start time:** 2025-11-10 16:05
- **End time:** --
- **Duration:** --
- **Execution:** 🤖 AI Agent
- **Description:** Identify and eliminate the cause of multiple jobs/descriptions being created for the movie generation flow.
- **Details:**
  - Reproduce the bug and audit event sources (controller, listener, job).
  - Adjust event/job triggering so each description is generated exactly once.
  - Add regression tests (unit/feature) preventing duplicate descriptions.
  - Verify side effects (Horizon queue, database writes) and update docs if needed.
- **Dependencies:** none
- **Created:** 2025-11-10

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

- **Active:** 16  
- **Completed:** 6  
- **Cancelled:** 0  
- **In progress:** 2

---

**Last updated:** 2025-11-30

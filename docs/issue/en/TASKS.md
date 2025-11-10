# 📋 Task Backlog – MovieMind API

**Last updated:** 2025-11-10  
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

#### `TASK-007` – Feature flag hardening
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 2–3 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Centralise flag configuration and document admin endpoints
- **Details:**
  - Consolidate config in `config/pennant.php`
  - Produce feature-flag docs
  - Extend admin endpoints for toggling (guarded)
- **Dependencies:** none
- **Created:** 2025-01-27

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

### 🔄 IN_PROGRESS

_No active tasks._

---

## ✅ Completed tasks

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

- **Active:** 11  
- **Completed:** 6  
- **Cancelled:** 0  
- **In progress:** 0

---

**Last updated:** 2025-11-10

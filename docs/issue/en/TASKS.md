# 📋 Task Backlog – MovieMind API

**Last updated:** 2025-11-07  
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

### ⏳ PENDING

#### `TASK-002` – Verify Queue Workers & Horizon
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 2–3 h
- **Start time:** --
- **End time:** --
- **Duration:** -- (auto for 🤖)
- **Execution:** TBD
- **Description:** Validate Horizon configuration and live worker behaviour
- **Details:** Confirm Horizon dashboard, worker liveness, and monitoring in staging/prod
- **Dependencies:** none
- **Created:** 2025-01-27

---

#### `TASK-003` – Introduce Redis caching for endpoints
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 3–4 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Add response caching for movie/person show endpoints + invalidation
- **Details:**
  - Cache responses in `MovieController::show()` and `PersonController::show()`
  - Invalidate cache after generation completes
  - Define cache keys and TTL strategy
- **Dependencies:** none
- **Created:** 2025-01-27

---

#### `TASK-004` – Update README.md (Symfony → Laravel)
- **Status:** ⏳ PENDING
- **Priority:** 🟢 Low
- **Estimated time:** 1 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Refresh README to reflect Laravel stack and add local setup instructions
- **Details:**
  - Update tech stack section
  - Document local run for `api/`, Horizon, Redis, Postgres
  - Ensure alignment with current architecture
- **Dependencies:** none
- **Created:** 2025-01-27

---

#### `TASK-005` – Review & update OpenAPI spec
- **Status:** ⏳ PENDING
- **Priority:** 🟡 Medium
- **Estimated time:** 2–3 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Audit `docs/openapi.yaml` and add OpenAPI links to README files
- **Details:**
  - Check coverage for all endpoints
  - Add request/response examples
  - Link OpenAPI from root and `api/` README
- **Dependencies:** none
- **Created:** 2025-01-27

---

#### `TASK-006` – Improve Postman collection
- **Status:** ⏳ PENDING
- **Priority:** 🟢 Low
- **Estimated time:** 2 h
- **Start time:** --
- **End time:** --
- **Duration:** --
- **Execution:** TBD
- **Description:** Add sample responses, tests, and env templates
- **Details:**
  - Provide example responses per request
  - Add Postman tests
  - Prepare environment files (local, staging)
- **Dependencies:** none
- **Created:** 2025-01-27

---

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

## ✅ Completed tasks

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

- **Active:** 9  
- **Completed:** 2  
- **Cancelled:** 0  
- **In progress:** 1

---

**Last updated:** 2025-11-07

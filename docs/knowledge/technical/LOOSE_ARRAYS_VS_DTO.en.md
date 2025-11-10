# Comparison: Loose arrays vs DTOs for tracking job status

> **Created:** 2025-11-10  
> **Context:** Review of `JobStatusService` and the potential shift from associative arrays to DTOs when storing job status in cache (e.g. `ai_job:*`).  
> **Category:** technical

## 🎯 Purpose

Compare the current array-based approach with a DTO-driven alternative for job status snapshots and outline the technical trade-offs for MovieMind API.

## 📋 Contents

### 1. Current state – loose arrays
- `JobStatusService` writes status snapshots directly as arrays (`initializeStatus`, `updateStatus`, `findActiveJobForSlug`).
- `array_merge` is used to mix the existing snapshot with incoming changes without validation.
- Keys such as `status`, `entity`, `slug`, `requested_slug`, `locale`, `context_tag`, `error`, `entity_id`, `confidence` are scattered across the service and its consumers (`RealGenerateMovieJob`, `QueueMovieGenerationAction`, etc.).
- Callers need to know the implicit structure and enforce types on their own.

### 2. Strengths of arrays
- **Implementation simplicity** – no extra classes, trivial to add/remove fields.
- **Schema flexibility** – any optional field can be stored without migrations.
- **Minimal overhead** – no conversion cost when reading/writing to Redis frequently.

### 3. Limitations of arrays
- **No guarantees for keys or types** – typos or unexpected values silently overwrite correct data.
- **`array_merge` pitfalls** – merging empty or malformed values can wipe parts of the snapshot.
- **Difficult evolution** – schema changes require searching the codebase and manually syncing all touchpoints.
- **Missing helpers** – validation, formatting and interpretation code gets duplicated.

### 4. DTOs – potential benefits
- **Explicit shape** – a central class (`JobStatusSnapshot`) can expose getters for whitelisted fields.
- **Validation hooks** – constructor/`fromArray()` enforces types, required fields and known statuses.
- **Safe updates** – methods like `withStatus()` or `merge()` can contain business rules for transitions.
- **Developer ergonomics** – better IDE support and no “magic strings”.
- **Extension points** – easier to add presentation helpers, logging or metrics around status changes.

### 5. Migration costs
- **Engineering effort** – implement the DTO, adjust service callers, add tests.
- **Performance** – additional object ↔ array conversions (usually negligible, but measurable).
- **Compatibility** – DTO must accept legacy cache entries (`fromArray()` with defaults).
- **Testing** – new unit tests for DTO behaviour (validation, serialization, transitions).

### 6. Recommendations for MovieMind API
- Stick to arrays when:
  - the snapshot schema is mostly stable and rarely changes,
  - raw performance/footprint in Redis is a top priority,
  - another layer already enforces types (e.g. API resources).
- Consider DTOs when:
  - status tracking is expected to evolve (extra metadata, AI audit trail, lifecycle timestamps),
  - you want to reduce technical debt and avoid “magic string” duplication,
  - multiple consumers (API, dashboards, reports) rely on the same structure,
  - business rules (allowed status transitions, mandatory identifiers) need hard guarantees.
- A hybrid option: expose DTOs from the public API of `JobStatusService` (`getStatus(): ?JobStatusSnapshot`) while storing arrays in Redis internally.

## 🔗 Related Documents
- `docs/knowledge/technical/STATUS_IMPLEMENTATION_REPORT.md`
- `docs/knowledge/technical/SUMMARY_STATUS_AND_RECOMMENDATIONS.md`
- `docs/knowledge/technical/QUEUE_ASYNC_EXPLANATION.md`

## 📌 Notes
- If the team opts for DTOs, plan a cache migration strategy (e.g. clearing legacy keys after deployment).
- Integration tests for `JobStatusService` will help ensure there are no regressions during the transition.

---

**Last updated:** 2025-11-10


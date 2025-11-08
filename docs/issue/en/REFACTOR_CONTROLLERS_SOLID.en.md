# 🔧 API Controller Refactor – SOLID & Laravel Best Practices

**Created:** 2025-01-27  
**Status:** 📋 Planned (now executed)  
**Priority:** 🔴 High  
**Estimated effort:** 6–8h

---

## 📋 Summary
Refactor API controllers (`MovieController`, `PersonController`, `GenerateController`, `JobsController`) to:
- remove duplicated logic (queue initialisation, cache writes, resource creation)
- enforce SOLID (especially SRP & DIP)
- introduce dedicated resource classes for response formatting
- add explicit return types (`JsonResponse`)
- improve testability via services/actions

---

## 🎯 Objectives
1. Extract business rules into services/actions.  
2. Deduplicate cache/job queue logic.  
3. Normalise response shaping with `MovieResource` / `PersonResource`.  
4. Simplify controller methods.  
5. Strengthen typing and enable isolated unit tests.

---

## 🔴 Pain points observed
### MovieController::show()
- 60+ lines, deeply nested conditionals, mixed concerns (disambiguation inside controller).
- Manual arrays instead of resources; cache init duplicated.

### PersonController::show()
- Uses `$person->toArray()`; no resource class.
- Duplicates queue/cache code.

### GenerateController
- Two near-identical methods (`handleMovieGeneration`, `handlePersonGeneration`).

### JobsController
- Raw cache access, magic key strings, no return types.

---

## ✅ Solution outline
### New building blocks
| Component | Purpose |
|-----------|---------|
| `JobStatusService` | Central cache helper (initialise/update/get job status). |
| `PersonResource` | Consistent response for person endpoints. |
| `MovieResource` | Consistent response for movie endpoints. |
| `MovieDisambiguationService` | Encapsulates ambiguous slug logic. |
| `QueueMovieGenerationAction` / `QueuePersonGenerationAction` | Wrap event dispatch, job status init, response payload. |

### Controller changes
- **MovieController**: delegates to actions + disambiguation service; responds via `MovieResource` and attaches `_meta` when needed.
- **PersonController**: mirrors movie flow using `PersonResource` and `QueuePersonGenerationAction`.
- **GenerateController**: already using actions (post-refactor version).  
- **JobsController**: now injects `JobStatusService` and returns `JsonResponse` based on cached status.

### Testing
- Unit tests for `MovieDisambiguationService` and `PersonResource`.  
- Existing job/action tests updated to reflect new classes.

---

## 🧪 Checklist executed
- [x] Added JobStatusService.  
- [x] Added MovieResource & PersonResource.  
- [x] Added MovieDisambiguationService.  
- [x] Created queue actions for movie/person.  
- [x] Refactored four API controllers.  
- [x] Added unit tests for new services/resources.  
- [x] Updated docs (`TASKS`, workflow guidelines).  
- [x] Synced backlog status (TASK-001 → completed).

---

## 📚 Related artifacts
- Polish spec: [`../pl/REFACTOR_CONTROLLERS_SOLID.md`](../pl/REFACTOR_CONTROLLERS_SOLID.md)  
- PR: `feature/task-sync-workflow`

---

**Last updated:** 2025-11-07

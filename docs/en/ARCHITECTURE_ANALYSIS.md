# Architecture Analysis – Service vs Events/Jobs

## 🔍 Current state
```
Controller → AiServiceInterface::queueMovieGeneration()
           → MockAiService (Bus::dispatch closure)
           → Queue worker executes closure
```
### Pain points
1. Large closure (70+ lines) hidden inside the service.
2. Hard to unit-test (no dedicated class/contract).
3. Mixed responsibilities: service handles cache, queue, business logic.
4. No retry/backoff config (closures lack `tries`, `timeout`, etc.).
5. Limited logging/monitoring.
6. Not event-driven – difficult to hook notifications/metrics.
7. Tight coupling: controllers depend on AiServiceInterface.

---

## ✅ Proposed approach: Events + Jobs
```
Controller → MovieGenerationRequested event
           → QueueMovieGenerationJob listener
           → GenerateMovieJob (ShouldQueue)
```
### Benefits
- Separate responsibilities (event, listener, job, service).
- Jobs support retries, backoff, timeouts, logging.
- Easier to extend (additional listeners, notifications, analytics).
- Controllers become minimal orchestrators.
- Code matches Laravel best practices.

### Implementation outline
1. Create events: `MovieGenerationRequested`, `PersonGenerationRequested`.
2. Create listeners queuing jobs: `QueueMovieGenerationJob`, `QueuePersonGenerationJob`.
3. Implement jobs: `GenerateMovieJob`, `GeneratePersonJob` (call AI client, update DB, cache).
4. Use `JobStatusService` to manage cache status (`ai_job:{id}`).
5. Controllers dispatch events instead of calling service closures.

---

## 🔄 Migration steps
1. Keep Mock service for legacy integration while introducing Jobs gradually.  
2. Update service container bindings; controllers fetch queue actions/dispatch events.  
3. Replace `Bus::dispatch` closures with jobs; write unit tests for each job.  
4. Add monitoring (Horizon) + logging per job execution.

---

## 📚 Related documents
- Polish version: [`../pl/ARCHITECTURE_ANALYSIS.md`](../pl/ARCHITECTURE_ANALYSIS.md)  
- Refactor execution report: [`../issue/en/REFACTOR_CONTROLLERS_SOLID.en.md`](../issue/en/REFACTOR_CONTROLLERS_SOLID.en.md)  
- For the distinction between a general-purpose API and a Backend for Frontend (BFF), see [BFF document](BFF_BACKEND_FOR_FRONTEND.md).

**Last updated:** 2025-11-07

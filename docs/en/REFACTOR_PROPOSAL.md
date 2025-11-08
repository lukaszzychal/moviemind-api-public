# Refactor Proposal: Service → Events + Jobs

## Goal
Replace `AiServiceInterface` + closure-based queuing with a first-class events & jobs pipeline.

## Target structure
```
app/Events/
  MovieGenerationRequested.php
  PersonGenerationRequested.php
app/Listeners/
  QueueMovieGenerationJob.php
  QueuePersonGenerationJob.php
app/Jobs/
  GenerateMovieJob.php
  GeneratePersonJob.php
```

## Steps
1. Dispatch events directly from controllers.  
2. Listeners enqueue the appropriate job and initialise status.  
3. Jobs call AI client, persist results, update `JobStatusService`.  
4. Remove closure logic from `MockAiService` (or adapt to dispatch events).  
5. Update tests to cover new flow.

## Benefits
- Consistent architecture across mock/real flows.  
- Retries/backoff/timeouts available.  
- Better logging & observability via Horizon.  
- Cleaner dependency graph.

**Polish source:** [`../pl/REFACTOR_PROPOSAL.md`](../pl/REFACTOR_PROPOSAL.md)

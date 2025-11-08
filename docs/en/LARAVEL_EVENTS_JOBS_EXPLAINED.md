# Laravel Events, Listeners & Jobs – Guide

## 🎯 Core concepts
### Event
- Represents something that happened (simple DTO).  
- Example: `MovieGenerationRequested` carrying `slug` and `jobId`.

### Listener
- Responds to events.  
- Example: `QueueMovieGenerationJob` listens for `MovieGenerationRequested` and dispatches `GenerateMovieJob`.

### Job
- Encapsulates queued work (`ShouldQueue`).  
- Can define `tries`, `timeout`, `backoff`, and handle AI API calls, DB updates, caching, logging.

## 🔄 Typical flow
```
Controller → event() → Listener → Job → Queue worker
```

## ✅ Benefits
- Decouples “what happened” from “what to do”.  
- Supports multiple listeners, retries, background processing.  
- Improves testability and observability.

## 🧱 Building blocks
1. Create events (`php artisan make:event`).  
2. Create listeners (`php artisan make:listener`).  
3. Register in `EventServiceProvider`.  
4. Dispatch events from controllers/services.  
5. Implement jobs with robust error handling.  
6. Run queue workers (e.g. Horizon) and monitor failures.

## 🛠 Tips
- Keep events light; listeners should offload heavy work to jobs.  
- Use `JobStatusService` (or similar) to track progress.  
- Leverage `ShouldQueue` listeners for simple workflows.  
- Write unit tests for each layer (event dispatch, listener reaction, job execution).

**Polish source:** [`../pl/LARAVEL_EVENTS_JOBS_EXPLAINED.md`](../pl/LARAVEL_EVENTS_JOBS_EXPLAINED.md)

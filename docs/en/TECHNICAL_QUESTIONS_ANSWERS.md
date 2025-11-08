# Technical Q&A

## 1. Is `OpenAiClientInterface` still used?
Yes. `RealGenerateMovieJob` and `RealGeneratePersonJob` resolve the interface via the container and call `generateMovie` / `generatePerson`.

## 2. Why keep both mock and real clients?
- Mock client enables deterministic tests and local development without external API calls.  
- Real client integrates with OpenAI in production.  
- Both follow the same interface so jobs stay agnostic.

## 3. Where is job status stored?
- `JobStatusService` caches entries under key `ai_job:{id}`.  
- Controllers poll via `JobsController::show`.  
- Listener initialises status when dispatching jobs.

## 4. How do we ensure asynchronous processing?
- Horizon/queue workers must be running.  
- `AI_SERVICE` toggles between mock closures and real jobs.  
- Real path uses events + queueable jobs, enabling retries/backoff.

**Polish source:** [`../pl/TECHNICAL_QUESTIONS_ANSWERS.md`](../pl/TECHNICAL_QUESTIONS_ANSWERS.md)

# Detecting in-flight generation jobs in the queue

> **Created:** 2025-11-10  
> **Context:** Repeated calls to `GET /api/v1/movies/{slug}` during an ongoing generation spawn duplicate jobs in Horizon/Redis.  
> **Category:** technical

## 🎯 Purpose

Describe a strategy for detecting an “ongoing” generation job for a given slug so that we can:
- avoid stacking new jobs while the first one is still running,
- return the existing `job_id` and status to the client,
- reduce OpenAI API spam and `failed_jobs` noise.

## 📋 Contents

### 1. Current flow (problem)

- The first call to `MovieController@show` for an unknown movie invokes `QueueMovieGenerationAction::handle()`.
- The action creates a new `job_id`, initializes cache status (Redis) and fires `MovieGenerationRequested`.
- The listener `QueueMovieGenerationJob` unapologetically dispatches a new job (`RealGenerateMovieJob` or `MockGenerateMovieJob`).
- If the client keeps polling the endpoint before the first job finishes, every request enqueues another job.
- Result: Horizon shows multiple entries for the same slug; when the API returns 403/429 we produce a storm of `failed_jobs`.

### 2. Detecting in-flight jobs

The easiest intervention point is `QueueMovieGenerationAction::handle()`. Before generating a new `job_id`, check the cache.

Suggested snippet:

```php
// app/Actions/QueueMovieGenerationAction.php
public function handle(string $slug, ?float $confidence = null, ?Movie $existingMovie = null): array
{
    if ($existingJob = $this->jobStatusService->findActiveJob('MOVIE', $slug)) {
        return [
            'job_id' => $existingJob['job_id'],
            'status' => $existingJob['status'],
            'message' => 'Generation already queued for movie by slug',
            'slug' => $slug,
            'confidence' => $existingJob['confidence'] ?? null,
            'confidence_level' => $this->confidenceLabel($existingJob['confidence'] ?? null),
        ];
    }

    // existing logic that enqueues a new job...
}
```

Helper additions in `JobStatusService`:

```php
public function findActiveJob(string $entityType, string $slug): ?array
{
    return Cache::get("ai_job_lookup:{$entityType}:{$slug}");
}

public function trackJobSlug(string $jobId, string $entityType, string $slug): void
{
    Cache::put("ai_job_lookup:{$entityType}:{$slug}", [
        'job_id' => $jobId,
        'status' => 'PENDING',
    ], now()->addMinutes(self::CACHE_TTL_MINUTES));
}
```

Updates required:
- during `initializeStatus()` call `trackJobSlug`,
- `markDone` / `markFailed` should delete (or update) the lookup entry,
- the job’s `failed()` hook must also clear the pointer.

### 3. Controller response refresh

When `QueueMovieGenerationAction::handle()` detects a pending job, it returns the existing state; `MovieController@show` replies with HTTP 202 and the same `job_id`.  
Clients can poll `GET /api/v1/jobs/{job_id}` instead of spawning another job.

### 4. Benefits & caveats

- Fewer `failed_jobs` entries, no repeated OpenAI requests.
- Horizon shows one job per slug, so retries are easier to diagnose.
- Choose a sensible cache TTL (>= highest backoff interval plus a safety margin).
- If you plan to add manual re-generation, allow a “force” flag to bypass the guard.

## 🔗 Related Documents

- `docs/knowledge/reference/FEATURE_FLAGS.en.md` – overview of flags and generation behaviour.
- `docs/knowledge/technical/QUEUE_ASYNC_EXPLANATION.md` – queue architecture primer.

## 📌 Notes

- After implementing, add a feature test verifying that the second request returns the same `job_id`.
- Monitor Redis locally (`redis-cli keys ai_job:*`) to ensure entries expire/clean up correctly.

---

**Last updated:** 2025-11-10


# Locking strategies for AI generation (MovieMind)

> **Created:** 2025-11-12  
> **Context:** Investigating duplicate movie descriptions during concurrent AI generation jobs  
> **Category:** technical

## 🎯 Goal

Summarise the locking mechanisms we evaluated for the movie description flow, provide examples, and explain why moving away from `Cache::lock` towards unique-index exception handling is recommended.

## 📋 Options overview

1. **`Cache::lock` (Laravel cache-backed lock)**
   - *How it works*  
     ```php
     Cache::lock("lock:movie:create:$slug", 30)->block(10, function () {
         // critical section — movie + description creation
     });
     ```
   - *Pros:* easy to enable, works out of the box, can guard extended logic (e.g. promoting default description).
   - *Cons:* global mutex throttles concurrent jobs; when the lock is released by the first worker, the second still needs custom logic to detect the new state (in our case it produced an extra description).

2. **Unique index + exception handling (recommended)**
   - *Mechanism:* rely on the existing unique index on `movies.slug` (`migrations/2025_10_30_000200_add_slugs_to_movies_and_people.php`). Creation happens without an explicit lock:
     ```php
     try {
         Movie::create(['slug' => $slug, /* ... */]);
     } catch (QueryException $e) {
         if ($this->isUniqueSlugViolation($e)) {
             $existing = Movie::whereSlug($slug)->first();
             $this->markDoneUsingExisting($existing);
         } else {
             throw $e;
         }
     }
     ```
   - *Pros:* no global lock, data integrity enforced by the database, simpler code path, better throughput.
   - *Cons:* requires precise detection of the unique-violation error code; logic outside the `INSERT` is not guarded.

3. **Transactional `SELECT ... FOR UPDATE`**
   - *Idea:* lock a control record inside a transaction. Great when you already have a row to lock. In our case the movie row is missing, so we would need a dedicated lock table.
   - *Pros:* strong consistency inside the transaction, explicit scope of the lock.
   - *Cons:* adds complexity (especially with SQLite in tests), demands careful transaction boundaries.

4. **Raw Redis lock (`SETNX`, Redlock)**
   - *Idea:* use Redis primitives directly (`SET key value NX PX 30000`). Horizon already relies on Redis.
   - *Pros:* fast and distributed, works across hosts.
   - *Cons:* custom implementation to maintain, still a global mutex, does not remove the need to handle “record already exists” afterwards.

## 🔍 Comparison

| Option                        | Overhead | Consistency | Complexity | Risk of duplicate descriptions | Notes |
|-------------------------------|----------|-------------|------------|-------------------------------|-------|
| `Cache::lock`                | medium   | depends on post-lock logic | low        | **High** (requires extra checks) | Current implementation causes the duplicate description symptom |
| Unique index + exception     | low      | guaranteed by DB           | low        | low                           | Recommended: deterministic, minimal code |
| `SELECT ... FOR UPDATE`      | medium   | high within transaction    | medium     | low                           | Needs a control row or separate lock table |
| `SETNX` / Redlock            | low      | depends on implementation  | medium     | medium                        | Still a manual mutex, doesn’t remove the “already exists” branch |

## ✅ Recommendation - Two-level strategy

### Level 1: "In-flight token" (`Cache::add`) - prevents duplicate job dispatching

- In `QueueMovieGenerationAction` and `QueuePersonGenerationAction`, we use `JobStatusService::acquireGenerationSlot()`.
- `Cache::add()` is **atomic** - only the first request will set the value.
- If slot is busy → return existing `job_id` instead of dispatching a new job.
- **Goal:** Resource savings (OpenAI API calls) - prevents unnecessary jobs.

```php
// In QueueMovieGenerationAction / QueuePersonGenerationAction
if (! $this->jobStatusService->acquireGenerationSlot('MOVIE', $slug, $jobId, ...)) {
    // Slot busy - return existing job_id
    return $this->buildExistingJobResponse(...);
}
```

### Level 2: Unique index + exception handling - database-level protection

- Remove `Cache::lock` from `RealGenerateMovieJob` and `RealGeneratePersonJob`.
- Rely on existing `movies.slug` and `people.slug` unique constraints.
- Catch `QueryException`, inspect the SQLSTATE (`23000` in SQLite, `23505` in PostgreSQL), and shortcut to `markDoneUsingExisting`.
- **Goal:** Race condition protection - even if two jobs are dispatched, database prevents duplicates.

```php
// In RealGenerateMovieJob / RealGeneratePersonJob
try {
    Movie::create(['slug' => $slug, ...]);  // ← Unique index guarantees no duplicates
} catch (QueryException $exception) {
    if ($this->isUniqueSlugViolation($exception)) {
        $existing = Movie::where('slug', $slug)->first();
        $this->markDoneUsingExisting($existing);
        return;
    }
    throw $exception;
}
```

### Why two levels?

1. **Level 1 (`Cache::add`):** Prevents unnecessary jobs - resource savings.
2. **Level 2 (Unique index):** Race condition protection - deterministic behavior.

### Additional notes

- Keep the small `Cache::lock` for promoting `default_description_id` if we still want protection against race conditions there.
- `releaseGenerationSlot()` is called in `finally` - always releases the slot after job completion.

## 🧪 Example flow after the change

### Scenario 1: Level 1 works (Cache::add)

1. **Request A** (`slug = matrix-1999`): `acquireGenerationSlot()` returns `true`, job dispatched ✅.
2. **Request B** (same slug, immediately after A): `acquireGenerationSlot()` returns `false` (slot busy).
3. Request B returns existing `job_id` from Request A - **no duplicate dispatch** ✅.

### Scenario 2: Level 2 works (Unique index) - edge case

1. **Request A** (`slug = matrix-1999`): Job 1 dispatched, creates movie ✅.
2. **Request B** (same slug, slot expired or edge case): Job 2 dispatched, tries `INSERT`.
3. Job 2 gets `QueryException` (unique violation), catches it, loads fresh row, updates cache job status, exits without writing a second description ✅.
4. Both jobs end with the same `description_id` - **no duplicate in database** ✅.

## 🔗 Related documents

- [Queue Async Explanation (PL)](./QUEUE_ASYNC_EXPLANATION.md)
- [Detecting ongoing queue jobs](./DETECTING_ONGOING_QUEUE_JOBS.en.md)
- [Locking strategies for AI generation (PL)](./LOCKING_STRATEGIES_FOR_AI_GENERATION.md)

## 📌 Notes

- Add a feature test simulating two simultaneous requests to ensure the exception branch works as expected.
- If AI latency grows, consider a lightweight “generation log” table, but do not reintroduce a global lock unless we observe new contention issues.
- Switching everything to PostgreSQL and relying on `SELECT ... FOR UPDATE` would provide deterministic locking, but comes with a heavy operational cost (SQLite tests no longer representative, more complex transaction handling, likely need for a dedicated "locks" table). Hence we prefer the **two-level strategy**: lightweight Redis `Cache::add` as "in-flight token" + unique index in database as final protection.

---

**Last updated:** 2025-11-12  
**Update:** 2025-11-12 - Added description of two-level strategy (Cache::add + unique index)


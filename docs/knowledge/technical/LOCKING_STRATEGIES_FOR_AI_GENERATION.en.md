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

## ✅ Recommendation

- Remove `Cache::lock` from `RealGenerateMovieJob`.
- Rely on the existing `movies.slug` unique index.
- Catch `QueryException`, inspect the SQLSTATE (`23000` / `23505`, etc.), and shortcut to `markDoneUsingExisting`.
- Keep the small `Cache::lock` for promoting `default_description_id` if we still want protection against race conditions there.

## 🧪 Example flow after the change

1. **Request A** (`slug = matrix-1999`): Job 1 creates the movie and description.
2. **Request B** (same slug, almost at the same time): Job 2 tries to insert, hits unique violation.
3. Job 2 catches the exception, loads the fresh row, updates cache job status, exits without writing a second description.

## 🔗 Related documents

- [Queue Async Explanation (PL)](./QUEUE_ASYNC_EXPLANATION.md)
- [Detecting ongoing queue jobs](./DETECTING_ONGOING_QUEUE_JOBS.en.md)
- [Locking strategies for AI generation (PL)](./LOCKING_STRATEGIES_FOR_AI_GENERATION.md)

## 📌 Notes

- Add a feature test simulating two simultaneous requests to ensure the exception branch works as expected.
- If AI latency grows, consider a lightweight “generation log” table, but do not reintroduce a global lock unless we observe new contention issues.
- Switching everything to PostgreSQL and relying on `SELECT ... FOR UPDATE` would provide deterministic locking, but comes with a heavy operational cost (SQLite tests no longer representative, more complex transaction handling, likely need for a dedicated “locks” table). Hence we prefer the lightweight Redis `Cache::add` in-flight token combined with the unique index.

---

**Last updated:** 2025-11-12


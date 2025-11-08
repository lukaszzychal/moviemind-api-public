# Laravel Queue – Async vs Sync

## Does `Bus::dispatch()` run asynchronously?
It depends on `QUEUE_CONNECTION`.

### Async configuration (prod/staging/local containers)
- `QUEUE_CONNECTION=redis` → jobs stored in Redis, processed by Horizon workers.  
- Fallback: `database` driver → jobs stored in `jobs` table, requires `queue:work`.

### Sync configuration (tests/local without workers)
- `QUEUE_CONNECTION=sync` → dispatch executes inline (no queue).  
- Useful for feature tests to avoid running workers.

### Best practices
- Use Redis + Horizon for production workloads.  
- Monitor queue length, failed jobs, and worker health.  
- Provide `/jobs/{id}` endpoint for status polling (via `JobStatusService`).

**Polish source:** [`../pl/QUEUE_ASYNC_EXPLANATION.md`](../pl/QUEUE_ASYNC_EXPLANATION.md)

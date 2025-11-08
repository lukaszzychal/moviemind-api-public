# How to Verify Queue Processing

## 🔍 Quick checks
1. **Environment**
   ```bash
   cd api
   grep QUEUE_CONNECTION .env
   ```
   - `QUEUE_CONNECTION=sync` → synchronous (no background worker).  
   - `database`, `redis`, `rabbitmq` → asynchronous (requires worker).

2. **Worker status**
   - Docker: `docker compose ps | grep horizon` or `docker compose logs horizon`.  
   - Local: `ps aux | grep "queue:work\|horizon"`.
   - If no worker is running, jobs queue up but are not processed.

3. **Smoke test**
   Add a small route that dispatches a job and returns immediately. Confirm you receive a 202 response while the worker logs job execution.

4. **Horizon dashboard**
   - Visit `/horizon` (if enabled) to inspect queues, failed jobs, throughput.

5. **Database/Redis storage**
   - For `database`: check the `jobs`/`failed_jobs` tables.  
   - For `redis`: use `redis-cli llen queues:default` to inspect backlog.

6. **Logs**
   - Worker logs in Docker: `docker compose logs -f horizon`.  
   - Horizon stores stats in `storage/horizon`.  
   - Ensure no repeated failures (retry loops).

---

## ✅ Checklist for asynchronous processing
- [ ] `QUEUE_CONNECTION` set to async driver.  
- [ ] Worker/Horizon service running.  
- [ ] Jobs disappear from queue (and reach success logs).  
- [ ] `failed_jobs` table empty or monitored.  
- [ ] /jobs/{id} endpoint returns updated status (via `JobStatusService`).

**Polish source:** [`../pl/CHECK_QUEUE_STATUS.md`](../pl/CHECK_QUEUE_STATUS.md)

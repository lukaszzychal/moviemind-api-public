# API Calls & Logging – Sync vs Async

## 🎯 At a glance
| Operation | Default | Async possible? |
|-----------|---------|-----------------|
| HTTP API calls | ✅ Synchronous | ✅ via queued jobs |
| Logging | ✅ Synchronous | ✅ configurable (queue driver) |
| Database queries | ✅ Synchronous | ✅ via jobs/events |

---

## 1. HTTP API calls
### Typical synchronous call
```php
$response = Http::get('https://api.example.com/data');
$payload = $response->json();
```
- Request thread waits for the external service to respond.
- Slow APIs block the user until completion.

### Offloading to background jobs
```php
GenerateMovieJob::dispatch($slug);
return response()->json(['status' => 'queued'], 202);
```
- Controller responds immediately (202).
- Job handles the `Http::post()` in the background, retries on failure, logs status.

**Best practice:** keep controllers lean; push heavy API operations to Jobs/Events.

---

## 2. Logging
- Laravel logs synchronously by default (Monolog handlers).
- You can switch to queued logging channel (`config/logging.php`, driver `stack` + `queue`).
- For high-volume logs consider sending to Logstash/Datadog via queue to avoid blocking requests.

---

## 3. Database writes
- Direct Eloquent saves run synchronously.  
- Heavy operations (analytics, audits) can be delegated to jobs that process after the main request finishes.

---

## 4. Recommendations
1. Use sync for quick operations (<100ms) and when immediate result is required.  
2. Use async for long-running calls, integrations, or side effects (emails, AI generation).  
3. Return 202 responses for queued work and provide polling endpoints (`/jobs/{id}`).  
4. Always handle retries/timeouts inside jobs (e.g. `retryUntil`, exponential backoff).  
5. Monitor queue workers (Horizon) and set alerts for failures.

**Polish source:** [`../pl/API_CALLS_ASYNC_LOGGING.md`](../pl/API_CALLS_ASYNC_LOGGING.md)

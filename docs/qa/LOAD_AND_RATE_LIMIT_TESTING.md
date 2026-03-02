# Load and Rate Limit Testing

> **For:** QA, DevOps  
> **Related:** [RATE_LIMITING_STRATEGY.md](../knowledge/technical/RATE_LIMITING_STRATEGY.md), [api/config/rate-limiting.php](../../api/config/rate-limiting.php)

---

## 1. Purpose

- Confirm that **rate limiting** behaves as configured (per-endpoint and per-plan limits, 429 when exceeded).
- Check that the API and infrastructure **withstand moderate load** without 502/503 or data errors.
- Validate **adaptive rate limiting** under load (limits decrease when CPU/queue/jobs are high).

This document does not replace full performance or stress testing; it gives a minimal, repeatable way to test limits and basic load.

---

## 2. What to test

| Area | What to verify |
|------|----------------|
| **Per-minute limit** | Sending more requests per minute than the plan/endpoint allows returns **429** and rate limit headers. |
| **Search endpoint** | Many parallel requests to `/api/v1/movies/search` (or `/people/search`): some succeed, excess get 429; no 502. |
| **Generate endpoint** | Multiple requests to `/api/v1/generate`: queue accepts work, rate limit applies; no 502. |
| **Adaptive behaviour** | Under high load (CPU/queue), limits decrease toward minimums (see `rate-limiting.php` min values). |
| **Health** | During and after load, `/api/v1/health` or `/api/v1/health/db` still returns 200. |

Default limits (see `api/config/rate-limiting.php`): search 100/min, show 120/min, bulk 30/min, generate 10/min, report 20/min. Plan-based limits apply when using an API key (see subscription plan `rate_limit_per_minute`).

---

## 3. Tools (simple options)

- **curl + shell loop** – Sequential or parallel requests to hit a limit or run a short burst.
- **Apache Bench (ab)** – Fixed concurrency, total requests; good for quick checks.
- **k6** – Scriptable load tests (e.g. ramp-up, hold, then ramp-down); can assert status codes and headers.
- **wrk** – High-throughput HTTP benchmarking; optional for heavier tests.

Use staging or a dedicated test environment; avoid production for anything beyond a light smoke check.

---

## 4. Example: triggering 429 (rate limit)

Assume base URL `https://staging.example.com` and a valid API key in header `Authorization: Bearer <key>` or `X-API-Key: <key>` (depending on your API auth).

**Per-minute limit (e.g. search = 100/min):** Send more than 100 requests in one minute; expect 429 after the limit.

```bash
# Example: 120 requests in quick succession (adjust endpoint and key)
BASE="https://staging.example.com"
KEY="your-api-key"
for i in $(seq 1 120); do
  curl -s -o /dev/null -w "%{http_code}\n" -H "X-API-Key: $KEY" "$BASE/api/v1/movies/search?q=test" &
done
wait
```

Or with **ab** (no API key in this example; add `-H "X-API-Key: ..."` if required):

```bash
ab -n 120 -c 10 -H "X-API-Key: YOUR_KEY" "https://staging.example.com/api/v1/movies/search?q=test"
```

**Pass criteria:** Some requests return **200**, others **429**; response headers include rate limit info (e.g. `X-RateLimit-*` if your API sets them). No **502** or **503** from rate limiting itself.

---

## 5. Example: short load burst (search)

Goal: many concurrent requests to search; expect 200s and 429s, no 502/503.

```bash
# 50 concurrent, 200 total requests
ab -n 200 -c 50 -H "X-API-Key: YOUR_KEY" "https://staging.example.com/api/v1/movies/search?q=test"
```

**Pass criteria:** No 502/503; majority 200, rest can be 429 when over limit. Check server and Horizon logs for errors.

---

## 6. Example: generate endpoint (queue + limit)

- Send a few **POST /api/v1/generate** requests (within plan and per-minute limit).
- **Pass criteria:** 202 or success response, job visible in Horizon; no 502/503. Exceeding per-minute limit for generate should yield 429.

---

## 7. k6 script sketch (optional)

Minimal k6 script to run a short load test and assert on status codes:

```javascript
import http from 'k6/http';
import { check } from 'k6';

const BASE = __ENV.BASE_URL || 'http://localhost:8000';
const API_KEY = __ENV.API_KEY || '';

export const options = {
  vus: 20,
  duration: '1m',
};

export default function () {
  const res = http.get(`${BASE}/api/v1/movies/search?q=test`, {
    headers: { 'X-API-Key': API_KEY },
  });
  check(res, { 'status is 200 or 429': (r) => r.status === 200 || r.status === 429 });
}
```

Run: `k6 run -e BASE_URL=https://staging.example.com -e API_KEY=your-key script.js`

---

## 8. What to document after a run

- Environment (staging URL, plan, API key type).
- Tool and command (ab, k6, curl).
- Number of requests, concurrency, duration.
- Counts: 200, 429, 5xx.
- Any 502/503 (investigate if present).
- Rate limit headers (if any) and correlation with 429.

---

## 9. References

- **Rate limits and config:** [RATE_LIMITING_STRATEGY.md](../knowledge/technical/RATE_LIMITING_STRATEGY.md), [api/config/rate-limiting.php](../../api/config/rate-limiting.php).
- **Subscription plans and per-key limits:** [SUBSCRIPTION_AND_RATE_LIMITING.md](../knowledge/technical/SUBSCRIPTION_AND_RATE_LIMITING.md) (if present).
- **Health checks:** `/api/v1/health`, `/api/v1/health/db`.

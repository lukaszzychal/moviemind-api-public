# Rate Limiting Strategy

> **Last updated:** 2025-02-21  
> **Related:** [SUBSCRIPTION_AND_RATE_LIMITING.md](./SUBSCRIPTION_AND_RATE_LIMITING.md)

---

## Purpose

This document describes the rate limiting strategy for the MovieMind API: who is limited, where limits apply, and why. It covers both **incoming** (client → API) and **outgoing** (API → external services) limits.

---

## Goals

- **Protect the system** from overload and abuse.
- **Comply with external API limits** (TMDb, TVMaze, OpenAI).
- **Fair access** for API clients via subscription plans (monthly and per-minute limits).

---

## Incoming Rate Limits (Clients → API)

Incoming limits are enforced per endpoint type and, when applicable, per API key and plan.

### Middleware and Configuration

| Component | Location | Role |
|-----------|----------|------|
| **AdaptiveRateLimit** | `api/app/Http/Middleware/AdaptiveRateLimit.php` | Adjusts limits based on system load (CPU, queue size, active jobs). Uses `api/config/rate-limiting.php` for defaults, minimums, and load thresholds. |
| **PlanBasedRateLimit** | `api/app/Http/Middleware/PlanBasedRateLimit.php` | Enforces monthly quota and per-minute rate limit per API key and subscription plan. |
| **Config** | `api/config/rate-limiting.php` | Default and minimum requests per minute per endpoint type; load thresholds and reduction factors. |

For plan-based behaviour, usage tracking, and feature flags, see [SUBSCRIPTION_AND_RATE_LIMITING.md](./SUBSCRIPTION_AND_RATE_LIMITING.md).

### Endpoint → Limit (defaults)

| Endpoint type | Default (req/min) | Minimum under load | Example routes |
|---------------|-------------------|---------------------|----------------|
| **search** | 100 | 20 | `/api/v1/movies/search`, `/api/v1/people/search` |
| **show** | 120 | 30 | `/api/v1/movies/{slug}`, `/api/v1/people/{slug}` |
| **bulk** | 30 | 5 | `/api/v1/movies/bulk` |
| **generate** | 10 | 2 | `/api/v1/generate` |
| **report** | 20 | 5 | `/api/v1/movies/{slug}/report`, `/api/v1/people/{slug}/report` |

Under high or critical load, AdaptiveRateLimit reduces limits toward the minimum values defined in config.

---

## Outgoing Rate Limits (API → External Services)

### TMDb

- **Limit:** 40 requests per 10 seconds (enforced in application).
- **Where:** `TmdbVerificationService` – `checkRateLimit()` uses Redis/Cache with key `tmdb:rate_limit:window` and a 10-second window.
- **Constants:** `RATE_LIMIT_REQUESTS = 40`, `RATE_LIMIT_WINDOW_SECONDS = 10` in `api/app/Services/TmdbVerificationService.php`.

### TVMaze

- **Limit:** 20 requests per 10 seconds (enforced in application).
- **Where:** `TvmazeVerificationService` – `checkRateLimit()` uses Redis/Cache with key `tvmaze:rate_limit:window` and a 10-second window.
- **Constants:** `RATE_LIMIT_REQUESTS = 20`, `RATE_LIMIT_WINDOW_SECONDS = 10` in `api/app/Services/TvmazeVerificationService.php`.

### OpenAI

- **Application-side:** No proactive per-minute or per-second cap. Jobs that call OpenAI use retry and backoff on failure.
- **Risk:** Under high load, the application can send many requests and may hit OpenAI’s 429 (rate limit) responses; handling is reactive (retries) rather than preventive.

### Outgoing webhooks

- **Current state:** No rate limit applied to outbound webhook delivery (e.g. per URL or per client).
- **Future option:** Consider a rate limit per webhook URL or per subscription to avoid overwhelming receivers.

---

## Gaps and Recommendations

1. **OpenAI:** Consider a central limiter for OpenAI calls (e.g. per-minute cap) to reduce 429s and align with provider limits. See **TASK-RATE-001**.
2. **External API docs:** Document official limits from TMDb, TVMaze, and OpenAI in this file or a linked doc for operations and audits. See **TASK-RATE-002**.
3. **Webhooks:** Optionally add rate limiting for outbound webhook delivery (e.g. per URL) if needed for fairness or receiver protection.

---

## References

- [SUBSCRIPTION_AND_RATE_LIMITING.md](./SUBSCRIPTION_AND_RATE_LIMITING.md) – plan-based limits, usage tracking, feature flags.
- `api/config/rate-limiting.php` – default/min limits, load thresholds, weights.
- `api/app/Http/Middleware/AdaptiveRateLimit.php`, `PlanBasedRateLimit.php` – incoming enforcement.
- `api/app/Services/TmdbVerificationService.php`, `TvmazeVerificationService.php` – outgoing TMDb/TVMaze limits.

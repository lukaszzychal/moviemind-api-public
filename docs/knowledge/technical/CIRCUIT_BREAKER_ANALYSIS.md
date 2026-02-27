# Circuit Breaker Analysis

> **Last updated:** 2025-02-21

---

## Pattern Overview

A **circuit breaker** prevents the application from repeatedly calling a failing or overloaded dependency. After a number of failures, the circuit **opens**: calls are not sent for a period. Then the circuit may move to **half-open**: a test request is allowed; on success the circuit **closes** (normal operation), on failure it opens again.

Benefits:

- Reduces cascading failures and load on the failing service.
- Faster failure feedback instead of long retry chains.
- Protects external APIs (e.g. TMDb, OpenAI) from being hammered during outages.

---

## When to Use

Consider a circuit breaker for:

- External HTTP APIs that can be unstable or rate-limited (TMDb, TVMaze, OpenAI).
- Any dependency where repeated retries during an outage would worsen the situation or waste resources.

---

## Current State in the Project

- **No circuit breaker** is implemented.
- **Retries with exponential backoff** exist for webhooks and for jobs (e.g. AI generation). When an external API is down or returning errors, the application keeps retrying on each attempt, which can prolong recovery and increase load on the dependency.

---

## Scope: Which Calls to Wrap

| Dependency | Usage | Candidate for circuit breaker |
|------------|--------|--------------------------------|
| **TMDb** | Verification, search (movies, persons, TV) | Yes – verification and search calls |
| **TVMaze** | Verification, search (TV) | Yes – verification and search calls |
| **OpenAI** | GenerateMovie, GeneratePerson, TV description jobs | Yes – all OpenAI API calls |
| **Outgoing webhooks** | Delivery to client URLs | Optional – per-URL or global CB |

Recommendation: start with one provider (e.g. TMDb) as a proof of concept, then extend to TVMaze and OpenAI after evaluation.

---

## Implementation Options

### 1. Ganesha (PHP library)

- **What:** Circuit breaker (and rate limiting) library for PHP; can integrate with Laravel and the HTTP client.
- **Storage:** Requires a backend (e.g. Redis) for state.
- **Pros:** Mature, configurable (failure count, time windows, half-open behaviour).  
- **Cons:** New dependency; needs wiring around HTTP client or service layer.

### 2. Custom implementation

- **What:** Simple circuit state in Cache (e.g. Redis): failure count per key, “open” until a timeout, then half-open with one trial.
- **Pros:** No new dependency; full control.  
- **Cons:** More code to maintain; no standard behaviour out of the box.

### 3. Defer

- **What:** Do not implement a circuit breaker until real availability/rate-limit issues are observed.
- **Pros:** No extra complexity now.  
- **Cons:** During a prolonged outage, retries can keep hitting the failing service.

---

## Recommendation

- **Backlog:** Add an evaluation task (e.g. **TASK-CB-001**) to analyse and run a proof-of-concept (e.g. Ganesha around one client, e.g. TMDb).
- **After PoC:** Decide whether to roll out circuit breakers for TMDb, then TVMaze and OpenAI, and whether to use Ganesha or a small custom implementation.
- **Optional:** Implement circuit breaker for the chosen provider (e.g. **TASK-CB-002**) after a positive evaluation.

---

## References

- `api/app/Services/TmdbVerificationService.php` – TMDb HTTP calls.
- `api/app/Services/TvmazeVerificationService.php` – TVMaze HTTP calls.
- Jobs calling OpenAI (e.g. GenerateMovieJob, GeneratePersonJob) – AI API usage.
- [RATE_LIMITING_STRATEGY.md](./RATE_LIMITING_STRATEGY.md) – outgoing rate limits and 429 handling context.

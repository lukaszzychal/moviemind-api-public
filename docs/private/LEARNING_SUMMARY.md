# MovieMind API – Learning Summary

> **For:** Interview Preparation, Self-Assessment, Educational Reference  
> **Last Updated:** 2026-01-25  
> **Status:** Portfolio/Demo Project

---

## 1. Executive Summary

This document summarizes what I learned while building MovieMind API—a RESTful API for AI-generated movie, series, and actor descriptions. It covers architecture, patterns, implementation rationale, and interview-ready talking points.

**Key takeaway:** Original AI-generated content (not copied from IMDb/TMDb), built with Laravel, Event-Driven architecture, and production-ready practices.

---

## 2. Architecture & Design Patterns

### Thin Controllers

**What:** Controllers limited to ~20–30 lines per method. They only validate input, delegate to Actions/Services, and format responses.

**How:** Constructor injection of `MovieRepository`, `MovieRetrievalService`, `MovieResponseFormatter`. Each method delegates to one Action or Service.

**Why:** Single Responsibility Principle. Controllers handle HTTP only. Business logic stays in Services/Actions—reusable from API, CLI, or Jobs. Easier to test (mock dependencies) and swap HTTP layer (e.g. REST → GraphQL).

**Example:**

```php
public function show(Request $request, string $slug): JsonResponse
{
    $descriptionId = $this->normalizeDescriptionId($request->query('description_id'));
    if ($descriptionId === false) {
        return $this->responseFormatter->formatError('Invalid description_id', 422);
    }
    $result = $this->movieRetrievalService->retrieveMovie($slug, $descriptionId);
    return $this->responseFormatter->formatFromResult($result, $slug, $descriptionId);
}
```

---

### Service Layer

**What:** Services encapsulate business logic and coordinate repositories, external APIs, and caching.

**How:** `MovieRetrievalService` uses `MovieRepository` and `EntityVerificationServiceInterface` (TMDB/TVmaze). It handles retrieval, disambiguation, and on-the-fly creation from external APIs.

**Why:** Centralized business logic, reusable across API and Jobs. Clear boundaries and testability via mocks.

**Examples:** `MovieRetrievalService`, `PlanService`, `UsageTracker`, `TmdbVerificationService`.

---

### Repository Pattern

**What:** Abstractions over data access. Repositories encapsulate queries.

**How:** `MovieRepository::findBySlug()`, `MovieRepository::searchMovies()`, etc. Return models or collections.

**Why:** Easier to test (mock repositories), swap storage (PostgreSQL → MongoDB), and keep query logic in one place.

**Example:**

```php
public function findBySlug(string $slug): ?Movie
{
    return Movie::where('slug', $slug)->first();
}
```

---

### Action Pattern

**What:** Single, cohesive business operations. One action = one workflow.

**How:** `QueueMovieGenerationAction::handle()` coordinates validation, JobStatusService, event dispatching. Returns array with job ID and status.

**Why:** Single Responsibility, clear input/output, easy to test and compose.

**Examples:** `QueueMovieGenerationAction`, `QueuePersonGenerationAction`, `VerifyMovieReportAction`.

---

### Response Formatter

**What:** Dedicated classes that format API responses into consistent JSON.

**How:** `MovieResponseFormatter::formatSuccess()`, `formatError()`, `formatNotFound()` etc. Handle HATEOAS links and structure.

**Why:** Uniform API responses. Controllers stay thin; formatting logic is centralized.

---

### Event-Driven Architecture

**What:** Flow: Event → Listener → Job. Events decouple producers from consumers.

**How:** `MovieGenerationRequested` event; listeners `QueueMovieGenerationJob` and `SendOutgoingWebhookListener`; jobs `RealGenerateMovieJob` or `MockGenerateMovieJob`.

**Why:** Loose coupling, extensibility (add listeners without changing producers), scalability (multiple workers).

**Flow:**

```
Controller → Action → Event::dispatch()
    → Listener (QueueMovieGenerationJob) → Job::dispatch()
    → Horizon Worker → OpenAI/TMDB → Database
```

---

## 3. Asynchronous Processing & Horizon

**What:** Long-running AI generation runs in background jobs via Laravel Horizon (Redis queues).

**Flow:** `Controller` → `QueueMovieGenerationAction` → `MovieGenerationRequested` event → `QueueMovieGenerationJob` listener → `RealGenerateMovieJob` (or Mock) → Horizon Worker → OpenAI API → DB.

**Why:** Avoid HTTP timeouts, better UX (async polling), horizontal scaling of workers.

**ADR-007 (Two-Level Locks):**

1. **Level 1 – In-flight token (`Cache::add`):** `JobStatusService::acquireGenerationSlot()` prevents dispatching multiple jobs for the same slug. Saves resources (no duplicate OpenAI calls).

2. **Level 2 – Unique index + exception:** Unique `movies.slug` + catch `QueryException` when two workers try to create the same record. Ensures deterministic behaviour even under race conditions.

**Why both?** Level 1 avoids unnecessary jobs; Level 2 handles edge cases when the cache slot expires.

---

## 4. Subscriptions & Rate Limiting

**Plans:**

- **Free:** 100 requests/month, read-only.
- **Pro:** 10,000/month, AI generation, context tags.
- **Enterprise:** Unlimited, webhooks, analytics, priority support.

**Components:**

- **PlanBasedRateLimit** middleware – checks monthly and per-minute limits.
- **UsageTracker** – `hasExceededMonthlyLimit()`, `hasExceededRateLimit()`, `getRemainingQuota()`.
- **PlanService** – `canUseFeature()`, `getMonthlyLimit()`, `getRateLimit()`.
- **SubscriptionPlan** model – `hasFeature()`, `isUnlimited()`.

**Flow:** `ApiKeyAuth` → `PlanBasedRateLimit` → `UsageTracker` checks → increment usage.

---

## 5. External Integrations

| Service  | Purpose                         | Licensing                          |
|----------|---------------------------------|------------------------------------|
| **OpenAI** | AI-generated descriptions (gpt-4o-mini) | API key, usage-based billing       |
| **TMDB**   | Verify movies/people, metadata  | Commercial license required in prod |
| **TVmaze** | Verify TV series/shows         | CC BY-SA, commercial use allowed   |

**AiServiceSelector:** Chooses `mock` vs `real` AI based on `AI_SERVICE` env. Public repo uses mock; private repo can use real OpenAI.

---

## 6. Feature Flags

**Stack:** Laravel Pennant + custom `BaseFeature` + `config/features.php`.

**Resolution order:** Environment Force (`_FORCE`) > Database Toggle (Filament) > Environment Default (`_DEFAULT`) > Code default.

**Use cases:**

- Instance specialization (API vs Worker nodes).
- Gradual rollouts.
- A/B testing.

**Filament Admin:** Manage flags at `/admin/features`. Lock icon when controlled by `_FORCE`.

**Developer flags:** Temporary (category `experiments`). Must be removed after feature deployment.

---

## 7. Testing Strategy

**Pyramid:**

- **Unit (~60%):** Services, Actions, Helpers. Fast, isolated.
- **Feature (~35%):** API endpoints, integrations. SQLite in-memory.
- **E2E (~5%):** Playwright for critical flows (e.g. admin panel).

**TDD:** Red → Green → Refactor. Tests first, then implementation.

**Mocking:** External APIs (OpenAI, TMDB, TVmaze) mocked in tests. Internal services usually not mocked.

---

## 8. Security

- **API keys:** Hashed, stored securely. Validated via `ApiKeyAuth` middleware.
- **Rate limiting:** Plan-based limits; Redis-backed.
- **Input validation:** Form Requests, strict rules.
- **Secrets:** GitLeaks in pre-commit; Composer Audit.
- **AI safety:** Prompt sanitization, strict output formats.

---

## 9. Key Architectural Decisions (ADRs)

| ADR  | Decision                                      | Rationale                                       |
|------|-----------------------------------------------|-------------------------------------------------|
| 001  | Laravel over Symfony                          | Faster MVP, Horizon, Eloquent, better DX        |
| 003  | Dual-repository (public portfolio / private)  | Security, portfolio showcase, flexible licensing|
| 004  | generation-first vs translate-then-adapt      | Unique content, cultural adaptation             |
| 006  | Pennant for feature flags                     | Laravel-native, simple, DB + env support        |
| 007  | Two-level locks (Cache::add + unique index)   | Avoid duplicate jobs + handle race conditions   |
| 008  | UUID v7/v4/v5 strategy                        | Sortable IDs, compatibility                     |

---

## 10. DevOps & Tools

- **Docker:** Mandatory for local dev (PostgreSQL, Redis, Nginx, PHP-FPM).
- **Pint:** PSR-12 formatting.
- **PHPStan:** Static analysis (level 5).
- **GitLeaks:** Secret detection.
- **Pre-commit:** Pint, PHPStan, tests, GitLeaks.

---

## 11. AI-Assisted Development

### IDE & Tools

- **Cursor** – Primary IDE with Claude/GPT integration.
- **Antigravity** – Alternative AI-powered IDE.
- **LLMs:** Claude (Sonnet, Opus), Gemini.

### MCP Servers (Model Context Protocol)

MCP extends AI assistants with tools and resources. I learned to create my own MCP server for documentation generation ([mcp-doc-generator](https://github.com/lukaszzychal/mcp-doc-generator)).

| Server           | Use case                                      |
|------------------|-----------------------------------------------|
| **GitHub MCP**   | Issues, PRs, commits, search, reviews         |
| **Firecrawl MCP**| Web scraping, search, crawl                   |
| **Filesystem MCP**| Read/write project files                     |
| **Playwright MCP**| Browser automation, E2E tests                |
| **Postman MCP**  | API testing, collections                      |
| **Sequential Thinking MCP** | Multi-step reasoning              |
| **Memory Bank MCP** | Long-term project memory                  |

### Skills (Cursor)

Custom Skills automate recurring tasks. The project includes a simple example Skill: `php-pre-commit` (`.cursor/skills/php-pre-commit/`) – reminder to run Pint, PHPStan, tests, and GitLeaks before commit.

### Practices

- Prompt engineering for code generation.
- AI-assisted code review and refactoring.
- AI-generated documentation with human review.
- TDD with AI (generate tests, then implementation).

---

## 12. Interview Talking Points

### "Tell me about the project"

> MovieMind API is a REST API that generates unique, AI-based descriptions for movies, series, and actors. Unlike IMDb or TMDb, it creates original content with OpenAI instead of copying metadata. It uses Laravel, Event-Driven architecture, Horizon for async jobs, and plan-based subscriptions with rate limiting. Built as a portfolio project with TDD, Docker, and production-ready practices.

### "How did you solve duplicate descriptions with parallel jobs?"

> We use a two-level strategy (ADR-007). Level 1: `Cache::add` as an in-flight token via `JobStatusService::acquireGenerationSlot()` so we don’t dispatch multiple jobs for the same slug. Level 2: a unique index on `movies.slug` plus `QueryException` handling when two workers try to create the same record. Level 1 reduces unnecessary jobs; Level 2 handles races when the cache slot expires.

### "Why Thin Controllers?"

> For Single Responsibility. Controllers handle HTTP only—validation, delegation, and response formatting. Business logic lives in Services and Actions, so it’s reusable from API, CLI, and Jobs. That makes it easier to test (mock dependencies) and to change the transport layer (e.g. REST to GraphQL) without touching core logic.

### "How does rate limiting work?"

> Plan-based. The `PlanBasedRateLimit` middleware uses `UsageTracker` to check monthly and per-minute limits per API key. Plans (Free, Pro, Enterprise) define these limits. Usage is stored in the DB; per-minute checks use Redis. If limits are exceeded, we return 429.

### "What is Event-Driven and why use it?"

> Events decouple producers from consumers. For example, `MovieGenerationRequested` is dispatched; listeners such as `QueueMovieGenerationJob` and `SendOutgoingWebhookListener` react. We can add listeners without changing the producer. Jobs run in Horizon workers, so we scale by adding workers.

### "How did you use AI in development?"

> I used Cursor with Claude/Gemini for coding, refactoring, and docs. MCP Servers (GitHub, Firecrawl, Playwright, Postman, mcp-doc-generator) extend the assistant with tools. I added a sample Skill `php-pre-commit` in the project as a reminder to run quality tools before commit. I use AI for TDD (generating tests) and code review, with human oversight.

### "What is an MCP Server?"

> Model Context Protocol (MCP) lets AI assistants call external tools. An MCP Server exposes tools (e.g. GitHub API, browser automation) and resources (e.g. files). The assistant can search code, create PRs, run E2E tests, or edit files through these tools instead of only generating text.

---

## 13. Quick Reference

### Key Files

| Layer      | Location                    | Examples                                   |
|-----------|-----------------------------|--------------------------------------------|
| Controllers | `api/app/Http/Controllers/` | MovieController, GenerateController        |
| Services    | `api/app/Services/`         | MovieRetrievalService, UsageTracker        |
| Actions     | `api/app/Actions/`          | QueueMovieGenerationAction                 |
| Repositories| `api/app/Repositories/`     | MovieRepository, PersonRepository          |
| Events      | `api/app/Events/`           | MovieGenerationRequested                   |
| Listeners   | `api/app/Listeners/`        | QueueMovieGenerationJob                    |
| Jobs        | `api/app/Jobs/`             | RealGenerateMovieJob, MockGenerateMovieJob |

### Documentation

- [Architecture](../technical/ARCHITECTURE.md)
- [ADRs](../adr/README.md)
- [Test Strategy](../qa/TEST_STRATEGY.md)
- [Feature Flags](../technical/FEATURE_FLAGS.md)
- [Subscription & Rate Limiting](../knowledge/technical/SUBSCRIPTION_AND_RATE_LIMITING.md)

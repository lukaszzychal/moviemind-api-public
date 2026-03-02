# Test Coverage Matrix

> **Purpose:** Map [MANUAL_TESTING_GUIDE.md](../MANUAL_TESTING_GUIDE.md) sections and
> [MANUAL_TEST_PLANS.md](MANUAL_TEST_PLANS.md) test cases (TC-*) to automated coverage
> (PHPUnit Feature, E2E/Playwright) and manual-only.

---

## Manual Testing Guide sections (TOC 1–19)

| # | Section | PHPUnit Feature | E2E/Playwright | Manual only |
|---|---------|----------------|-----------------|-------------|
| 1 | Overview | – | – | Yes (doc) |
| 2 | Prerequisites | – | – | Yes (doc) |
| 3 | Environment Setup | – | – | Yes (doc) |
| 4 | Movies API | Yes | Yes (happy path, smoke) | – |
| 5 | People API | Yes | Yes (smoke) | – |
| 6 | Movie Relationships | Yes | – | – |
| 7 | TV Series & TV Shows API | Yes | Yes (smoke) | – |
| 8 | TV Series & TV Show Reports | Yes | – | – |
| 9 | Generate API | Yes | Yes (security, admin trigger, happy path) | – |
| 10 | Jobs API | Yes | Yes (happy path) | – |
| 11 | Movie Reports | Yes | – | – |
| 12 | Person Reports | Yes | – | – |
| 13 | Webhooks | Yes (AdminPanelTest, OutgoingWebhooksTest) | No | – |
| 14 | Adaptive Rate Limiting | Yes | – | – |
| 15 | Health & Admin | Yes | Yes (admin-flow, flags, metrics, horizon, docs) | – |
| 16 | Security Verification | Yes | Yes (api-security.spec) | – |
| 17 | Performance Testing | Yes | – | – |
| 18 | Troubleshooting | – | – | Yes (doc) |
| 19 | Test Report Template | – | – | Yes (doc) |

---

## Test cases (TC-*) coverage

| Area | Test cases | PHPUnit | E2E |
|------|------------|--------|-----|
| Movies | TC-MOVIE-001 – 010 | Yes | Happy path: list, search, get, generate, job |
| People | TC-PERSON-001 – 009 | Yes | Smoke (list, get by slug) |
| TV Series | TC-TVSERIES-001 – 003 | Yes | Smoke (list) |
| TV Shows | TC-TVSHOW-001 – 003 | Yes | Smoke (list) |
| Generation | TC-GEN-001 – 004 | Yes | Auth + admin trigger + happy path |
| Auth | TC-AUTH-001 – 002 | Yes | api-security.spec |
| I18N | TC-I18N-001 | Yes | – |
| Integrations | TC-INT-TMDB-001, TC-INT-TVMAZE-001, TC-INT-OPENAI-001 | Yes | – |
| Admin API | TC-ADMIN-001 – 002 | Yes | admin-flags (partial) |
| Admin UI | TC-UI-001 – 010 | Yes | admin-flow, flags, metrics, generate, horizon, users (TC-UI-010 Webhooks: no E2E) |
| Error | TC-ERROR-001 – 003 | Yes (in endpoint tests) | – |
| Health | TC-HEALTH-001 – 004 | Yes | – (health/db used in e2e setup) |
| Scenarios | Happy path, Search→Generation, Multilingual | Yes | movies-happy-path.spec |

---

## E2E spec files (Playwright)

| Spec | Scope |
|------|--------|
| `api-security.spec.ts` | Public endpoints, Generate 401/403, plan feature, valid key |
| `movies-happy-path.spec.ts` | List movies → search → get by slug → generate → job status |
| `people-api-smoke.spec.ts` | List people, get person by slug |
| `tv-api-smoke.spec.ts` | List TV series, list TV shows |
| `admin-flow.spec.ts` | Login redirect, login success, validation error |
| `admin-flags.spec.ts` | Feature flags list, toggle, UI/API consistency |
| `admin-generate.spec.ts` | Trigger AI generation from admin UI |
| `admin-metrics.spec.ts` | AI metrics in Filament |
| `feature-flags.spec.ts` | Access feature flags resource |
| `user-management.spec.ts` | Create user, assign flag scope |
| `horizon.spec.ts` | Horizon dashboard access |
| `documentation.spec.ts` | Swagger UI, OpenAPI YAML |
| `deduplication.spec.ts` | Person/Movie/TV Series deduplication |

---

## Gaps (manual or PHPUnit only, no E2E)

- Full Movies API (bulk, compare, related, collection, refresh, report, disambiguation) – PHPUnit only.
- Full People API (search, bulk, compare, related, refresh, report) – PHPUnit only.
- TV Series/Shows get-by-slug, search, reports – PHPUnit only.
- Jobs API (GET job) – E2E in happy path only.
- Movie/Person/TV report submission – PHPUnit only.
- Adaptive rate limiting (429) – PHPUnit only.
- Health endpoint GET /api/v1/health – PHPUnit only.
- TC-UI-003, TC-UI-005 – TC-UI-010 (full UI flows) – partial E2E or PHPUnit only.
- TC-UI-010 Webhook Management – PHPUnit only (AdminPanelTest: list + create page); no Playwright E2E.

For which PHPUnit test class covers which area, see `api/tests/Feature/`.

# PostgreSQL Testing

## Overview

All tests (unit and feature) run on **PostgreSQL**. The main CI job `test` and local test runs (via Docker) use the same database stack as production.

This document describes how PostgreSQL is used for testing and how to run tests locally and in CI.

## Test Suite

- **Main test job (`test` in CI):** Runs the full PHPUnit suite (unit + feature tests) against PostgreSQL 16 (service container).
- **`PostgreSQLSpecificTest`** (in `api/tests/Feature/PostgreSQLSpecificTest.php`): Tests database features such as partial unique indexes and JSON/JSONB. These tests **always run** (no skip) because the test environment is always PostgreSQL.
- **Postman / other QA:** Can use the same or a dedicated PostgreSQL instance as documented in their workflows.

## Running Tests

### In CI (GitHub Actions)

The `test` job in `.github/workflows/ci.yml`:

- Starts a PostgreSQL 16 service container
- Sets `DB_CONNECTION=pgsql`, `DB_HOST=localhost`, `DB_DATABASE=moviemind_test`, etc.
- Runs `composer test` (or equivalent) so all tests use PostgreSQL

The optional `test-postgresql` job (if present) may run an additional subset; the primary test run is the main `test` job.

### Locally (Docker required)

```bash
docker compose up -d
docker compose exec php php artisan test
```

Ensure `.env` (used by the PHP container) has `DB_CONNECTION=pgsql`, `DB_HOST=db`, and valid credentials. The `api/phpunit.xml.dist` can override `DB_DATABASE=moviemind_test` for tests; other settings can come from `.env`.

### Without Docker

PostgreSQL must be running locally and reachable (e.g. `DB_HOST=127.0.0.1`). Create a database (e.g. `moviemind_test`) and set the same env vars as in `phpunit.xml.dist` or `.env`.

## Test Coverage (PostgreSQL)

- **Partial unique index** – e.g. only one active description per `(movie_id, locale, context_tag)`.
- **JSON/JSONB** – e.g. `ai_jobs.payload_json` and JSON operators.
- **Date/time** – e.g. `TO_CHAR` in analytics/failed jobs (no SQLite branch).
- **Raw SQL** – e.g. `genres::text` in search; all such code paths are exercised on PostgreSQL.

## Why PostgreSQL for All Tests

- **Parity with production:** Same SQL dialect and features (partial indexes, JSONB, types).
- **No dual code paths:** Migrations and application code no longer branch on SQLite vs PostgreSQL.
- **Single CI setup:** One database for the main test job; no separate “PostgreSQL-only” test environment to maintain for the core suite.

## Related Files

- `api/phpunit.xml.dist` – test DB env vars
- `api/tests/Feature/PostgreSQLSpecificTest.php` – database feature tests
- `.github/workflows/ci.yml` – CI test job with PostgreSQL service
- `docs/knowledge/reference/TESTING_DATABASE.md` – detailed test database configuration


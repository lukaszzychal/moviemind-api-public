# PostgreSQL-Specific Tests

## Overview

This document describes the PostgreSQL-specific test suite that verifies features available only in PostgreSQL, such as partial unique indexes and JSON/JSONB operations.

## Test Suite

The `PostgreSQLSpecificTest` class (`api/tests/Feature/PostgreSQLSpecificTest.php`) contains tests that:

- Verify partial unique index constraints for movie descriptions
- Test JSON/JSONB operations in `ai_jobs` table
- Validate versioning behavior with archived descriptions
- Test race condition prevention with concurrent inserts

## Running Tests

### In CI (GitHub Actions)

PostgreSQL-specific tests are automatically run in CI using the `test-postgresql` job, which:

- Uses PostgreSQL 16 service container
- Runs all tests in `PostgreSQLSpecificTest` class
- Verifies that partial unique indexes work correctly

### Locally (Optional)

If you have PostgreSQL installed locally, you can run these tests:

```bash
# Set environment variables
export DB_CONNECTION=pgsql
export DB_HOST=127.0.0.1
export DB_PORT=5432
export DB_DATABASE=moviemind_test
export DB_USERNAME=postgres
export DB_PASSWORD=postgres

# Run PostgreSQL-specific tests
cd api
php artisan test --filter=PostgreSQLSpecificTest
```

### Using Docker

If you're using Docker Compose:

```bash
# Start PostgreSQL container
docker compose up -d postgres

# Run tests
cd api
DB_CONNECTION=pgsql DB_HOST=localhost DB_PORT=5432 DB_DATABASE=moviemind_test DB_USERNAME=postgres DB_PASSWORD=postgres php artisan test --filter=PostgreSQLSpecificTest
```

## Test Coverage

### 1. Partial Unique Index Tests

- **`test_partial_unique_index_prevents_duplicate_active_descriptions`**: Verifies that only one active (non-archived) description can exist per `(movie_id, locale, context_tag)` combination.
- **`test_partial_unique_index_allows_multiple_archived_descriptions`**: Verifies that archived descriptions don't violate the unique constraint, allowing version history.
- **`test_partial_unique_index_allows_different_context_tags`**: Verifies that different context tags don't violate the unique constraint.
- **`test_partial_unique_index_prevents_race_condition`**: Tests that partial unique index prevents race conditions in concurrent inserts.

### 2. JSON/JSONB Tests

- **`test_jsonb_operations_in_ai_jobs`**: Verifies that JSON/JSONB columns work correctly in PostgreSQL, including nested JSON queries.

## Why These Tests Are Separate

These tests are separated from the main test suite because:

1. **SQLite Limitations**: SQLite doesn't support partial unique indexes, so these tests would always be skipped locally.
2. **Production Parity**: Running tests with PostgreSQL ensures that the test environment matches production.
3. **Feature Verification**: These tests verify database-level constraints that are critical for data integrity.

## Skipping in SQLite

All tests in `PostgreSQLSpecificTest` automatically skip when running with SQLite:

```php
if (DB::getDriverName() !== 'pgsql') {
    $this->markTestSkipped('This test suite requires PostgreSQL');
}
```

This ensures that:
- Local development with SQLite remains fast
- Tests don't fail due to unsupported features
- CI still verifies PostgreSQL-specific functionality

## CI Integration

The `test-postgresql` job in `.github/workflows/ci.yml`:

- Runs in parallel with other test jobs
- Uses PostgreSQL 16 service container
- Only runs PostgreSQL-specific tests
- Fails if any PostgreSQL-specific feature doesn't work

## Maintenance

When adding new PostgreSQL-specific features:

1. Add tests to `PostgreSQLSpecificTest`
2. Ensure tests skip in SQLite
3. Verify tests pass in CI
4. Update this documentation if needed

## Related Files

- `api/tests/Feature/PostgreSQLSpecificTest.php` - Test suite
- `.github/workflows/ci.yml` - CI configuration
- `api/database/migrations/2025_12_20_151647_add_versioning_to_movie_descriptions_table.php` - Migration with partial unique index


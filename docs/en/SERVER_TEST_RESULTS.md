# Server Test Results

- **Date:** 2025-11-01  
- **Environment:** Docker (local)  
- **Status:** ✅ All endpoints responding

## Docker services
- moviemind-db (PostgreSQL 15) – running on port 5433.  
- moviemind-redis (Redis 7) – running on port 6379.  
- moviemind-php (PHP-FPM).  
- moviemind-nginx – exposed on port 8000.  
- moviemind-horizon – not installed at the time (queue handled via `php artisan queue:work`).

## Database snapshot
- Migrations up-to-date.  
- Seed data loaded (movies/people).  
- Jobs table empty (processed successfully).

## API smoke tests
- `GET /api/v1/health` → 200 OK.  
- `POST /api/v1/generate` (mock) → returns job id + pending status.  
- `GET /api/v1/jobs/{id}` → returns cached status (`DONE` after worker completes).

## Observations
- Queue worker must be running for async jobs.  
- Horizon recommended for monitoring in future runs.  
- No critical errors in logs.

**Polish source:** [`../pl/SERVER_TEST_RESULTS.md`](../pl/SERVER_TEST_RESULTS.md)

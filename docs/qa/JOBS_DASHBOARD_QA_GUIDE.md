# Jobs Dashboard - QA Testing Guide

## Overview

This guide provides comprehensive testing procedures for the Jobs Dashboard feature, including manual testing scenarios, edge cases, and validation criteria.

## Test Environment Setup

### Prerequisites
- Docker Compose running
- Horizon worker running
- Database with test data
- Admin API access configured

### Test Data Setup
```bash
# Create test jobs
docker compose exec php php artisan tinker
# Create failed jobs, pending jobs, etc.
```

## Test Scenarios

### 1. Overview Endpoint

**Endpoint:** `GET /api/v1/admin/jobs-dashboard/overview`

**Test Cases:**

#### TC-001: Basic Overview
1. **Setup**: Create jobs in different states
2. **Action**: GET `/api/v1/admin/jobs-dashboard/overview`
3. **Expected**: Returns statistics with all required fields
4. **Validate**:
   - `total_pending` is a number
   - `total_processing` is a number
   - `total_completed` is a number
   - `total_failed` is a number
   - `queues` is an array
   - `ai_jobs` contains statistics

#### TC-002: Empty State
1. **Setup**: No jobs in database
2. **Action**: GET `/api/v1/admin/jobs-dashboard/overview`
3. **Expected**: Returns zeros for all counts
4. **Validate**: All counts are 0

#### TC-003: Large Dataset
1. **Setup**: Create 1000+ jobs
2. **Action**: GET `/api/v1/admin/jobs-dashboard/overview`
3. **Expected**: Returns correct counts
4. **Validate**: Performance is acceptable (< 2 seconds)

### 2. By Queue Endpoint

**Endpoint:** `GET /api/v1/admin/jobs-dashboard/by-queue`

**Test Cases:**

#### TC-004: Multiple Queues
1. **Setup**: Create jobs in different queues (default, high, low)
2. **Action**: GET `/api/v1/admin/jobs-dashboard/by-queue`
3. **Expected**: Returns statistics for each queue
4. **Validate**:
   - Each queue has `queue`, `pending`, `processing`, `failed`
   - Counts match actual jobs

#### TC-005: Single Queue
1. **Setup**: Create jobs only in 'default' queue
2. **Action**: GET `/api/v1/admin/jobs-dashboard/by-queue`
3. **Expected**: Returns one queue entry
4. **Validate**: Statistics are correct

#### TC-006: Empty Queues
1. **Setup**: No jobs in any queue
2. **Action**: GET `/api/v1/admin/jobs-dashboard/by-queue`
3. **Expected**: Returns empty array or queues with zeros
4. **Validate**: Response is valid JSON

### 3. Recent Jobs Endpoint

**Endpoint:** `GET /api/v1/admin/jobs-dashboard/recent`

**Test Cases:**

#### TC-007: Pagination
1. **Setup**: Create 25 jobs
2. **Action**: GET `/api/v1/admin/jobs-dashboard/recent?per_page=10&page=1`
3. **Expected**: Returns 10 jobs, total 25
4. **Validate**:
   - `data` contains 10 items
   - `total` is 25
   - `per_page` is 10
   - `current_page` is 1
   - `last_page` is 3

#### TC-008: Second Page
1. **Setup**: Create 25 jobs
2. **Action**: GET `/api/v1/admin/jobs-dashboard/recent?per_page=10&page=2`
3. **Expected**: Returns next 10 jobs
4. **Validate**: Jobs are different from page 1

#### TC-009: Invalid Pagination
1. **Setup**: Create 10 jobs
2. **Action**: GET `/api/v1/admin/jobs-dashboard/recent?per_page=-1&page=0`
3. **Expected**: Handles gracefully (defaults or error)
4. **Validate**: Response is valid

### 4. Failed Jobs Endpoint

**Endpoint:** `GET /api/v1/admin/jobs-dashboard/failed`

**Test Cases:**

#### TC-010: Basic Failed Jobs
1. **Setup**: Create 5 failed jobs
2. **Action**: GET `/api/v1/admin/jobs-dashboard/failed`
3. **Expected**: Returns failed jobs with details
4. **Validate**:
   - Each job has `uuid`, `queue`, `exception`
   - Jobs are ordered by `failed_at` desc

#### TC-011: Filter by Queue
1. **Setup**: Create failed jobs in different queues
2. **Action**: GET `/api/v1/admin/jobs-dashboard/failed?queue=default`
3. **Expected**: Returns only default queue jobs
4. **Validate**: All returned jobs have queue='default'

#### TC-012: Filter by Date Range
1. **Setup**: Create failed jobs at different times
2. **Action**: GET `/api/v1/admin/jobs-dashboard/failed?start_date=2026-01-01&end_date=2026-01-31`
3. **Expected**: Returns only jobs in date range
4. **Validate**: All jobs within date range

#### TC-013: Exception Parsing
1. **Setup**: Create failed job with exception
2. **Action**: GET `/api/v1/admin/jobs-dashboard/failed`
3. **Expected**: Exception is parsed correctly
4. **Validate**:
   - `exception` contains message
   - `exception_class` is extracted if available

### 5. Failed Stats Endpoint

**Endpoint:** `GET /api/v1/admin/jobs-dashboard/failed/stats`

**Test Cases:**

#### TC-014: Basic Statistics
1. **Setup**: Create failed jobs in different queues
2. **Action**: GET `/api/v1/admin/jobs-dashboard/failed/stats`
3. **Expected**: Returns aggregated statistics
4. **Validate**:
   - `total_failed` matches count
   - `by_queue` has correct counts
   - `by_hour` has hourly breakdown

#### TC-015: No Failed Jobs
1. **Setup**: No failed jobs
2. **Action**: GET `/api/v1/admin/jobs-dashboard/failed/stats`
3. **Expected**: Returns zeros
4. **Validate**: All counts are 0

### 6. Processing Times Endpoint

**Endpoint:** `GET /api/v1/admin/jobs-dashboard/processing-times`

**Test Cases:**

#### TC-016: Basic Response
1. **Action**: GET `/api/v1/admin/jobs-dashboard/processing-times`
2. **Expected**: Returns structure
3. **Validate**:
   - `by_queue` is an object
   - `overall_avg` is a number or null

### 7. Frontend Dashboard

**URL:** `http://localhost:8000/admin/dashboard.html`  
**Security:** Protected by `admin.basic` middleware (same as admin API endpoints)

**Test Cases:**

#### TC-017: Page Loads
1. **Action**: Open dashboard URL
2. **Expected**: Page loads without errors
3. **Validate**:
   - No JavaScript errors
   - Charts render
   - Data displays

#### TC-018: Auto-Refresh
1. **Action**: Open dashboard, wait 30+ seconds
2. **Expected**: Data refreshes automatically
3. **Validate**: Updated data appears

#### TC-019: Manual Refresh
1. **Action**: Click "Refresh" button
2. **Expected**: Data reloads immediately
3. **Validate**: Latest data appears

#### TC-020: Error Handling
1. **Setup**: Stop API server
2. **Action**: Open dashboard
3. **Expected**: Error message displayed
4. **Validate**: Error is user-friendly

#### TC-021: Chart Rendering
1. **Action**: Open dashboard with data
2. **Expected**: Charts render correctly
3. **Validate**:
   - Queue chart shows bars
   - Colors are correct
   - Labels are readable

## Edge Cases

### EC-001: Very Large Numbers
- **Test**: 1,000,000+ jobs
- **Expected**: Performance acceptable or pagination limits

### EC-002: Special Characters
- **Test**: Queue names with special characters
- **Expected**: Handled correctly

### EC-003: Timezone Issues
- **Test**: Jobs created in different timezones
- **Expected**: Dates formatted consistently

### EC-004: Concurrent Updates
- **Test**: Jobs created while dashboard is open
- **Expected**: Auto-refresh picks up changes

### EC-005: Database Connection Loss
- **Test**: Database unavailable
- **Expected**: Graceful error handling

## Performance Tests

### PT-001: Response Time
- **Metric**: API response time < 2 seconds
- **Test**: Load dashboard with 1000 jobs
- **Validate**: All endpoints respond quickly

### PT-002: Frontend Load Time
- **Metric**: Page loads < 3 seconds
- **Test**: Open dashboard
- **Validate**: Initial load is fast

### PT-003: Memory Usage
- **Metric**: No memory leaks
- **Test**: Leave dashboard open for 1 hour
- **Validate**: Memory usage stable

## Security Tests

### ST-001: Dashboard Authentication (Local)
- **Test**: Access dashboard without authentication in local environment
- **Expected**: 200 OK (bypass enabled in local via `ADMIN_AUTH_BYPASS_ENVS`)

### ST-002: Dashboard Authentication (Production)
- **Test**: Access dashboard without authentication in production
- **Expected**: 401 Unauthorized with Basic Auth challenge

### ST-003: Dashboard Authorization
- **Test**: Access dashboard with invalid credentials
- **Expected**: 401 Unauthorized

### ST-004: Dashboard Authorization (Valid)
- **Test**: Access dashboard with valid admin credentials (from `ADMIN_ALLOWED_EMAILS`)
- **Expected**: 200 OK with dashboard content

### ST-005: API Endpoint Authentication
- **Test**: Access API endpoints without authentication
- **Expected**: 401 Unauthorized (in production) or 200 OK (in local with bypass)

### ST-003: SQL Injection
- **Test**: Malicious input in query parameters
- **Expected**: Input sanitized, no SQL injection

## Browser Compatibility

### BC-001: Chrome
- **Test**: Latest Chrome
- **Expected**: All features work

### BC-002: Firefox
- **Test**: Latest Firefox
- **Expected**: All features work

### BC-003: Safari
- **Test**: Latest Safari
- **Expected**: All features work

### BC-004: Mobile
- **Test**: Mobile browser
- **Expected**: Responsive design works

## Regression Tests

### RT-001: Existing Analytics
- **Test**: Existing analytics endpoints still work
- **Expected**: No breaking changes

### RT-002: Horizon Integration
- **Test**: Horizon dashboard still accessible
- **Expected**: No conflicts

## Acceptance Criteria

✅ All API endpoints return correct data
✅ Frontend displays data correctly
✅ Auto-refresh works
✅ Error handling is graceful
✅ Performance is acceptable
✅ Security is maintained
✅ Browser compatibility verified
✅ No regressions introduced

## Test Execution Checklist

- [ ] Run all unit tests
- [ ] Run all feature tests
- [ ] Manual testing of all endpoints
- [ ] Frontend testing in multiple browsers
- [ ] Performance testing
- [ ] Security testing
- [ ] Edge case testing
- [ ] Regression testing


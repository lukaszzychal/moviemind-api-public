# Jobs Dashboard - Technical Documentation

## Architecture

The Jobs Dashboard consists of three main components:

1. **Backend Services**: Collect and aggregate job statistics
2. **API Endpoints**: Expose statistics via REST API
3. **Frontend Dashboard**: Visualize statistics in a web interface

## Components

### Services

#### QueueJobsDashboardService
**File:** `api/app/Services/QueueJobsDashboardService.php`

Provides queue statistics from both Horizon API and database:

- `getOverview()`: Overall queue statistics
- `getByQueue()`: Statistics grouped by queue name
- `getRecentJobs()`: Recent jobs with pagination
- `getProcessingTimes()`: Average processing times
- `getAiJobsStatistics()`: AI jobs aggregated statistics

**Data Sources:**
- `jobs` table: Pending and processing jobs
- `failed_jobs` table: Failed jobs
- `ai_jobs` table: AI generation jobs
- Redis (Horizon): Real-time queue metrics

#### FailedJobsService
**File:** `api/app/Services/FailedJobsService.php`

Provides failed jobs monitoring and statistics:

- `getFailedJobs()`: Failed jobs with pagination
- `getFailedJobsByQueue()`: Filter by queue
- `getFailedJobsByDateRange()`: Filter by date range
- `getFailureStatistics()`: Aggregated failure statistics
- `getFailureRate()`: Failure rate percentage

**Data Sources:**
- `failed_jobs` table: Failed job records
- `ai_jobs` table: Failed AI generation jobs

### Controllers

#### JobsDashboardController
**File:** `api/app/Http/Controllers/Admin/JobsDashboardController.php`

REST API endpoints for dashboard data:

- `GET /api/v1/admin/jobs-dashboard/overview`: Overall statistics
- `GET /api/v1/admin/jobs-dashboard/by-queue`: Statistics by queue
- `GET /api/v1/admin/jobs-dashboard/recent`: Recent jobs
- `GET /api/v1/admin/jobs-dashboard/failed`: Failed jobs
- `GET /api/v1/admin/jobs-dashboard/failed/stats`: Failure statistics
- `GET /api/v1/admin/jobs-dashboard/processing-times`: Processing times

### Frontend

#### Dashboard HTML
**File:** `api/public/admin/dashboard.html`  
**Route:** `GET /admin/dashboard.html` (protected by `admin.basic` middleware)

Simple HTML/JavaScript dashboard with:
- Chart.js for visualizations
- Real-time data fetching
- Auto-refresh every 30 seconds
- Responsive design
- **Security:** Protected by Basic Auth (same as admin API endpoints)

## Data Flow

```
Frontend (dashboard.html)
    ↓ HTTP GET
API Endpoints (JobsDashboardController)
    ↓
Services (QueueJobsDashboardService, FailedJobsService)
    ↓
Database (jobs, failed_jobs, ai_jobs)
    ↓
Redis (Horizon metrics)
```

## Database Schema

### jobs Table
```sql
CREATE TABLE jobs (
    id BIGINT PRIMARY KEY,
    queue VARCHAR(255),
    payload TEXT,
    attempts TINYINT,
    reserved_at INT NULL,
    available_at INT,
    created_at INT
);
```

### failed_jobs Table
```sql
CREATE TABLE failed_jobs (
    id BIGINT PRIMARY KEY,
    uuid VARCHAR(255) UNIQUE,
    connection TEXT,
    queue TEXT,
    payload TEXT,
    exception TEXT,
    failed_at TIMESTAMP
);
```

### ai_jobs Table
```sql
CREATE TABLE ai_jobs (
    id BIGINT PRIMARY KEY,
    entity_type VARCHAR(16),
    entity_id BIGINT,
    locale VARCHAR(10) NULL,
    status VARCHAR(16) DEFAULT 'PENDING',
    payload_json JSON NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## API Endpoints

### GET /api/v1/admin/jobs-dashboard/overview

Returns overall queue statistics.

**Response:**
```json
{
  "total_pending": 10,
  "total_processing": 5,
  "total_completed": 100,
  "total_failed": 2,
  "horizon_status": {
    "active": true,
    "masters_count": 1
  },
  "queues": [...],
  "ai_jobs": {
    "by_status": {
      "PENDING": 5,
      "DONE": 95,
      "FAILED": 2
    },
    "by_entity_type": {...},
    "total": 102
  }
}
```

### GET /api/v1/admin/jobs-dashboard/by-queue

Returns statistics grouped by queue.

**Response:**
```json
[
  {
    "queue": "default",
    "pending": 5,
    "processing": 3,
    "failed": 1
  }
]
```

### GET /api/v1/admin/jobs-dashboard/recent

Returns recent jobs with pagination.

**Query Parameters:**
- `per_page` (int, default: 10): Items per page
- `page` (int, default: 1): Page number

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "queue": "default",
      "job": "App\\Jobs\\GenerateMovieJob",
      "attempts": 0,
      "status": "pending",
      "created_at": "2026-01-07 10:00:00",
      "available_at": "2026-01-07 10:00:00"
    }
  ],
  "total": 50,
  "per_page": 10,
  "current_page": 1,
  "last_page": 5
}
```

### GET /api/v1/admin/jobs-dashboard/failed

Returns failed jobs with pagination.

**Query Parameters:**
- `per_page` (int, default: 10): Items per page
- `page` (int, default: 1): Page number
- `queue` (string, optional): Filter by queue
- `start_date` (string, optional): Start date (Y-m-d)
- `end_date` (string, optional): End date (Y-m-d)

**Response:**
```json
{
  "data": [
    {
      "id": 1,
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "queue": "default",
      "connection": "redis",
      "job": "App\\Jobs\\GenerateMovieJob",
      "exception": "OpenAI API error",
      "exception_class": "App\\Exceptions\\AiServiceException",
      "failed_at": "2026-01-07 10:00:00"
    }
  ],
  "total": 5,
  "per_page": 10,
  "current_page": 1,
  "last_page": 1
}
```

### GET /api/v1/admin/jobs-dashboard/failed/stats

Returns failure statistics.

**Response:**
```json
{
  "total_failed": 10,
  "by_queue": {
    "default": 7,
    "high": 3
  },
  "by_hour": {
    "2026-01-07 10:00:00": 2,
    "2026-01-07 11:00:00": 3
  }
}
```

### GET /api/v1/admin/jobs-dashboard/processing-times

Returns processing time statistics.

**Response:**
```json
{
  "by_queue": {
    "default": {
      "avg_seconds": null,
      "min_seconds": null,
      "max_seconds": null,
      "sample_size": 0
    }
  },
  "overall_avg": null
}
```

## Horizon Integration

The dashboard integrates with Laravel Horizon for real-time queue metrics:

- **Redis Keys**: Checks for `horizon:*` keys
- **Masters**: Reads `horizon:masters` for active workers
- **Status**: Determines if Horizon is active

## Database Compatibility

The services support multiple database drivers:

- **PostgreSQL:** Uses `TO_CHAR()` for date formatting
- **MySQL/MariaDB**: Uses `DATE_FORMAT()` for date formatting

## Performance Considerations

### Caching
- No caching implemented (real-time data required)
- Consider adding caching for expensive aggregations

### Query Optimization
- Indexes on `queue`, `failed_at`, `status` columns
- Pagination limits result set size
- Date range queries use indexed columns

### Frontend
- Auto-refresh every 30 seconds
- Chart.js for efficient rendering
- Minimal DOM manipulation

## Security

### Authentication
- Admin API endpoints require authentication via `admin.basic` middleware
- Dashboard HTML route is protected by `admin.basic` middleware
- Bypass in local/staging environments for testing (via `ADMIN_AUTH_BYPASS_ENVS`)
- Production requires Basic Auth with authorized email and password

### Configuration
- `ADMIN_AUTH_BYPASS_ENVS`: Comma-separated list of environments that bypass auth (default: `local,staging`)
- `ADMIN_ALLOWED_EMAILS`: Comma-separated list of authorized email addresses
- `ADMIN_BASIC_AUTH_PASSWORD`: Password for Basic Auth (required in production)

### Authorization
- Only authorized users can access dashboard
- Same authorization as other admin endpoints
- Basic Auth challenge returned for unauthorized access (401 Unauthorized)

## Testing

### Unit Tests
- `QueueJobsDashboardServiceTest`: Service logic
- `FailedJobsServiceTest`: Service logic

### Feature Tests
- `JobsDashboardTest`: API endpoints

### Test Coverage
- All service methods tested
- All controller endpoints tested
- Database compatibility tested

## Deployment

### Requirements
- Laravel Horizon running
- Redis connection configured
- Database with jobs tables
- Admin authentication configured

### Configuration
- No additional configuration required
- Uses existing Horizon and database configuration

## Troubleshooting

### Dashboard Not Loading
- Check admin authentication
- Verify API endpoints are accessible
- Check browser console for errors

### No Data Displayed
- Verify jobs exist in database
- Check Horizon is running
- Verify Redis connection

### Performance Issues
- Check database indexes
- Consider adding caching
- Optimize queries if needed


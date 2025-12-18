# Request-ID and Correlation-ID: Tracing HTTP Requests

## 📋 Overview

The MovieMind API implements **request-id** and **correlation-id** headers to enable request tracing, debugging, and distributed system monitoring. These identifiers help track requests across services, logs, and error reports.

## 🎯 What Are They?

### Request-ID (`X-Request-ID`)

**Purpose:** Unique identifier for a **single HTTP request**.

- **Generated:** Automatically for each incoming request (UUIDv4)
- **Scope:** One request → one request-id
- **Use Case:** Track a specific request through logs, errors, and responses

**Example:**
```http
GET /api/v1/movies/the-matrix-1999 HTTP/1.1
Host: api.example.com

HTTP/1.1 200 OK
X-Request-ID: 550e8400-e29b-41d4-a716-446655440000
```

### Correlation-ID (`X-Correlation-ID`)

**Purpose:** Identifier to track **related requests** across services or user sessions.

- **Generated:** Automatically if not provided, or uses client-provided value
- **Scope:** Can span multiple requests (e.g., user session, API call chain)
- **Use Case:** Track related requests in microservices, user journeys, or distributed systems

**Example:**
```http
# Client sends correlation-id to maintain chain
GET /api/v1/movies/the-matrix-1999 HTTP/1.1
Host: api.example.com
X-Correlation-ID: 7c9e6679-7425-40de-944b-e07fc1f90ae7

HTTP/1.1 200 OK
X-Request-ID: 550e8400-e29b-41d4-a716-446655440000
X-Correlation-ID: 7c9e6679-7425-40de-944b-e07fc1f90ae7
```

## 🔧 How It Works

### Automatic Generation

1. **Request arrives** → Middleware intercepts
2. **Request-ID generated** → UUIDv4 (always unique)
3. **Correlation-ID checked** → Uses `X-Correlation-ID` header if provided, otherwise generates UUIDv4
4. **IDs stored** → Added to request attributes and log context
5. **Response sent** → Both IDs added to response headers

### Client-Provided Correlation-ID

Clients can send `X-Correlation-ID` header to maintain a correlation chain:

```bash
# First request - no correlation-id
curl -X GET http://localhost:8000/api/v1/movies/the-matrix-1999

# Response includes both IDs
# X-Request-ID: 550e8400-e29b-41d4-a716-446655440000
# X-Correlation-ID: 7c9e6679-7425-40de-944b-e07fc1f90ae7

# Subsequent requests - use correlation-id from previous response
curl -X GET http://localhost:8000/api/v1/movies/the-matrix-1999 \
  -H "X-Correlation-ID: 7c9e6679-7425-40de-944b-e07fc1f90ae7"

# Response maintains same correlation-id
# X-Request-ID: 8b1e8400-e29b-41d4-a716-446655440001  (new)
# X-Correlation-ID: 7c9e6679-7425-40de-944b-e07fc1f90ae7  (same)
```

## 📊 Implementation Details

### UUID Version

Both IDs use **UUIDv4** (random UUID) as per ADR-008:

- ✅ **UUIDv4** – Universal identifiers, request-id, correlation-id
- ❌ **UUIDv7** – Only for database primary keys (time-sortable)

**Rationale:** Request and correlation IDs don't need time-sorting. They're ephemeral identifiers for tracing, not database records.

### Middleware

**File:** `api/app/Http/Middleware/RequestIdMiddleware.php`

**Behavior:**
- Runs on **every HTTP request** (global middleware)
- Generates UUIDv4 for request-id
- Accepts client-provided correlation-id (validates UUID format)
- Adds IDs to:
  - Response headers (`X-Request-ID`, `X-Correlation-ID`)
  - Log context (all logs include these IDs)
  - Request attributes (accessible in controllers/services)

### Log Context

All log entries automatically include request-id and correlation-id:

```php
// In any controller/service
Log::info('Movie retrieved', ['movie_id' => $movie->id]);

// Log output includes:
// [2025-12-18 10:30:45] local.INFO: Movie retrieved
// {"movie_id":"0197f96c-b278-7f64-a32f-dae3cabe1ff0","request_id":"550e8400-e29b-41d4-a716-446655440000","correlation_id":"7c9e6679-7425-40de-944b-e07fc1f90ae7"}
```

### Accessing IDs in Code

```php
// In controllers/services
$requestId = $request->attributes->get('request_id');
$correlationId = $request->attributes->get('correlation_id');

// Or via helper (if you create one)
$requestId = request()->attributes->get('request_id');
```

## 🎓 Use Cases

### 1. Debugging Single Requests

**Problem:** User reports error, but you don't know which request caused it.

**Solution:** Ask user for `X-Request-ID` from error response, search logs:

```bash
# Search logs for specific request-id
grep "550e8400-e29b-41d4-a716-446655440000" storage/logs/laravel.log
```

### 2. Tracing User Journeys

**Problem:** User makes multiple API calls, want to trace entire flow.

**Solution:** Client sends `X-Correlation-ID` in all requests:

```javascript
// Frontend JavaScript
const correlationId = generateUUID(); // Generate once per user session

fetch('/api/v1/movies/search?q=matrix', {
  headers: {
    'X-Correlation-ID': correlationId
  }
});

fetch('/api/v1/movies/the-matrix-1999', {
  headers: {
    'X-Correlation-ID': correlationId  // Same correlation-id
  }
});

// All logs for this user journey share same correlation-id
```

### 3. Microservices Tracing

**Problem:** Request goes through multiple services, need to trace across all.

**Solution:** Each service forwards correlation-id:

```
Client → API Gateway → Movie Service → Database Service
         (correlation-id: abc-123)
                    ↓
         (correlation-id: abc-123)
                              ↓
         (correlation-id: abc-123)
```

### 4. Error Reporting

**Problem:** Error occurs, need to find all related logs.

**Solution:** Use correlation-id to find all logs for related requests:

```bash
# Find all logs for a correlation chain
grep "7c9e6679-7425-40de-944b-e07fc1f90ae7" storage/logs/laravel.log
```

## 📝 Best Practices

### For API Clients

1. **Always read response headers** - Store `X-Request-ID` for error reporting
2. **Use correlation-id for related requests** - Generate once per user session/flow
3. **Include correlation-id in error reports** - Helps developers trace issues
4. **Forward correlation-id in downstream calls** - Maintain chain across services

### For API Developers

1. **Always log request-id and correlation-id** - Already done via middleware
2. **Include IDs in error responses** - Helps clients report issues
3. **Use IDs in database queries** - Store in audit logs if needed
4. **Document IDs in API docs** - Clients need to know about headers

## 🔍 Example: Complete Flow

### Request 1: Search Movies

```http
GET /api/v1/movies/search?q=matrix HTTP/1.1
Host: api.example.com
```

**Response:**
```http
HTTP/1.1 200 OK
X-Request-ID: 550e8400-e29b-41d4-a716-446655440000
X-Correlation-ID: 7c9e6679-7425-40de-944b-e07fc1f90ae7

{
  "data": [...]
}
```

### Request 2: Get Movie Details (with correlation-id)

```http
GET /api/v1/movies/the-matrix-1999 HTTP/1.1
Host: api.example.com
X-Correlation-ID: 7c9e6679-7425-40de-944b-e07fc1f90ae7
```

**Response:**
```http
HTTP/1.1 200 OK
X-Request-ID: 8b1e8400-e29b-41d4-a716-446655440001
X-Correlation-ID: 7c9e6679-7425-40de-944b-e07fc1f90ae7

{
  "data": {...}
}
```

**Logs:**
```
[2025-12-18 10:30:45] local.INFO: Movie search {"query":"matrix","request_id":"550e8400-e29b-41d4-a716-446655440000","correlation_id":"7c9e6679-7425-40de-944b-e07fc1f90ae7"}
[2025-12-18 10:30:46] local.INFO: Movie retrieved {"movie_id":"0197f96c-b278-7f64-a32f-dae3cabe1ff0","request_id":"8b1e8400-e29b-41d4-a716-446655440001","correlation_id":"7c9e6679-7425-40de-944b-e07fc1f90ae7"}
```

## 🛠️ Testing

### Manual Testing

```bash
# Test without correlation-id
curl -v http://localhost:8000/api/v1/movies/the-matrix-1999

# Check response headers:
# < X-Request-ID: 550e8400-e29b-41d4-a716-446655440000
# < X-Correlation-ID: 7c9e6679-7425-40de-944b-e07fc1f90ae7

# Test with correlation-id
curl -v http://localhost:8000/api/v1/movies/the-matrix-1999 \
  -H "X-Correlation-ID: my-custom-correlation-id-123"

# Check response headers:
# < X-Request-ID: 8b1e8400-e29b-41d4-a716-446655440001
# < X-Correlation-ID: my-custom-correlation-id-123
```

### Automated Testing

```php
// Feature test example
public function test_request_id_header_present(): void
{
    $response = $this->getJson('/api/v1/movies/the-matrix-1999');

    $response->assertHeader('X-Request-ID');
    $response->assertHeader('X-Correlation-ID');
    
    $requestId = $response->headers->get('X-Request-ID');
    $this->assertTrue(Str::isUuid($requestId));
}

public function test_correlation_id_from_client(): void
{
    $correlationId = (string) Str::uuid();
    
    $response = $this->getJson('/api/v1/movies/the-matrix-1999', [
        'X-Correlation-ID' => $correlationId,
    ]);

    $response->assertHeader('X-Correlation-ID', $correlationId);
}
```

## 📚 References

- **ADR-008:** UUID Strategy (UUIDv4 for request-id/correlation-id)
- **RFC 4122:** UUID Standard
- **Laravel Logging:** https://laravel.com/docs/12.x/logging#contextual-information
- **Distributed Tracing:** https://opentelemetry.io/docs/concepts/signals/traces/

## 🎯 Summary

- ✅ **Request-ID**: Unique per request, auto-generated (UUIDv4)
- ✅ **Correlation-ID**: Tracks related requests, can be client-provided (UUIDv4)
- ✅ **Automatic**: Added to all responses and logs
- ✅ **Traceable**: Use IDs to find logs and debug issues
- ✅ **Standards-compliant**: Uses UUIDv4 as per ADR-008

---

**Last Updated:** 2025-12-18  
**Author:** MovieMind API Team


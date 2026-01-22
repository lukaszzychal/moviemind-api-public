# MovieMind API - API Specification

> **For:** Developers, API Consumers, Integrators  
> **Last Updated:** 2026-01-21  
> **Status:** Portfolio/Demo Project

---

## 🎯 Overview

This document provides detailed API specification for MovieMind API. For complete OpenAPI specification, see [`docs/openapi.yaml`](../openapi.yaml).

**Note:** This is a portfolio/demo project. For production deployment, see [Production Considerations](#production-considerations).

---

## 📚 OpenAPI Specification

The complete OpenAPI 3.0 specification is available at:
- **File:** [`docs/openapi.yaml`](../openapi.yaml)
- **Interactive Docs:** Available at `/api/doc` when running locally (if Swagger UI is configured)

---

## 🔐 Authentication

### API Key Authentication

All endpoints require API key authentication via one of the following methods:

**Standard (Recommended):**
```http
X-API-Key: mm_abc123def456...
```

**Legacy Support:**
```http
X-RapidAPI-Key: mm_abc123def456...
```

**Alternative:**
```http
Authorization: Bearer mm_abc123def456...
```

### API Key Format

- **Prefix:** `mm_`
- **Format:** `mm_<40-random-chars>`
- **Example:** `mm_abc123def456ghi789jkl012mno345pqr678stu901vwx234`

### Obtaining API Keys

**Via Admin API:**
```bash
POST /api/v1/admin/api-keys
Authorization: Basic <admin-credentials>
{
  "name": "My API Key",
  "plan_id": "<plan-uuid>"
}
```

**Via Seeder (Demo):**
```bash
php artisan db:seed --class=ApiKeySeeder
```

**⚠️ Important:** API keys are only shown once during creation. Store them securely.

---

## 📊 Rate Limiting

### Plan-Based Limits

| Plan | Monthly Limit | Per-Minute Limit |
|------|---------------|------------------|
| Free | 100 requests | 10 requests |
| Pro | 10,000 requests | 100 requests |
| Enterprise | Unlimited | 1,000 requests |

### Rate Limit Headers

All responses include rate limit information:

```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1642684800
```

### Rate Limit Exceeded

When rate limit is exceeded:

**Status:** `429 Too Many Requests`

**Response:**
```json
{
  "error": "Rate limit exceeded",
  "message": "You have exceeded your rate limit of 100 requests/minute",
  "retry_after": 60
}
```

---

## 🎬 Entity Types

### MOVIE

Feature films and movies.

**Slug Format:** `{title-slug}-{year}`

**Examples:**
- `the-matrix-1999`
- `inception-2010`
- `bad-boys-1995`

---

### PERSON

Actors, directors, writers, producers, and other people.

**Slug Format:** `{name-slug}-{birth-year}`

**Examples:**
- `keanu-reeves-1964`
- `christopher-nolan-1970`
- `laurence-fishburne-1961`

---

### TV_SERIES

Scripted TV series (drama, comedy, sci-fi, etc.).

**Slug Format:** `{title-slug}-{year}`

**Examples:**
- `breaking-bad-2008`
- `game-of-thrones-2011`

---

### TV_SHOW

Unscripted TV shows (talk shows, reality, news, documentaries).

**Slug Format:** `{title-slug}-{year}`

**Examples:**
- `the-daily-show-1996`
- `planet-earth-2006`

---

## 📍 Endpoints

### Movies

#### List Movies
```http
GET /api/v1/movies
```

**Query Parameters:**
- `q` (string, optional): Free-text search
- `locale` (string, optional): Locale code (en-US, pl-PL, de-DE, fr-FR, es-ES)

**Response:** `200 OK`
```json
{
  "data": [
    {
      "id": "uuid",
      "slug": "the-matrix-1999",
      "title": "The Matrix",
      "release_year": 1999,
      "director": "Lana Wachowski & Lilly Wachowski",
      "default_description": {...},
      "genres": [...],
      "people": [...],
      "_links": {...}
    }
  ]
}
```

---

#### Get Movie by Slug
```http
GET /api/v1/movies/{slug}
```

**Path Parameters:**
- `slug` (string, required): Movie slug

**Query Parameters:**
- `locale` (string, optional): Locale code
- `description_id` (string, optional): Specific description UUID

**Response:** `200 OK`
```json
{
  "id": "uuid",
  "slug": "the-matrix-1999",
  "title": "The Matrix",
  "release_year": 1999,
  "director": "Lana Wachowski & Lilly Wachowski",
  "default_description": {...},
  "descriptions": [...],
  "genres": [...],
  "people": [...],
  "_links": {...}
}
```

**Disambiguation Response:** `300 Multiple Choices`
```json
{
  "disambiguation": {
    "message": "Multiple movies found",
    "options": [
      {
        "slug": "heat-1995",
        "title": "Heat",
        "release_year": 1995,
        "director": "Michael Mann"
      },
      {
        "slug": "heat-1995-2",
        "title": "Heat",
        "release_year": 1995,
        "director": null
      }
    ]
  }
}
```

---

#### Search Movies
```http
GET /api/v1/movies/search?q={query}
```

**Query Parameters:**
- `q` (string, required): Search query
- `locale` (string, optional): Locale code

**Response:** `200 OK`
```json
{
  "data": [...],
  "meta": {
    "total": 10,
    "per_page": 50,
    "current_page": 1
  }
}
```

---

#### Bulk Retrieve Movies
```http
POST /api/v1/movies/bulk
```

**Request Body:**
```json
{
  "slugs": [
    "the-matrix-1999",
    "inception-2010",
    "interstellar-2014"
  ]
}
```

**Response:** `200 OK`
```json
{
  "data": [
    {...}, // Movie 1
    {...}, // Movie 2
    {...}  // Movie 3
  ]
}
```

---

#### Compare Movies
```http
GET /api/v1/movies/compare?slugs=the-matrix-1999,inception-2010
```

**Query Parameters:**
- `slugs` (string, required): Comma-separated movie slugs

**Response:** `200 OK`
```json
{
  "data": [
    {...}, // Movie 1
    {...}  // Movie 2
  ],
  "comparison": {
    "common_genres": [...],
    "common_people": [...]
  }
}
```

---

#### Get Related Movies
```http
GET /api/v1/movies/{slug}/related
```

**Response:** `200 OK`
```json
{
  "data": [
    {
      "slug": "inception-2010",
      "title": "Inception",
      "relationship_type": "SAME_UNIVERSE",
      "relevance_score": 0.85
    }
  ]
}
```

---

#### Get Movie Collection
```http
GET /api/v1/movies/{slug}/collection
```

**Response:** `200 OK`
```json
{
  "collection": {
    "name": "The Matrix Collection",
    "movies": [
      {
        "slug": "the-matrix-1999",
        "title": "The Matrix",
        "position": 1
      },
      {
        "slug": "the-matrix-reloaded-2003",
        "title": "The Matrix Reloaded",
        "position": 2
      }
    ]
  }
}
```

---

#### Refresh Movie Data
```http
POST /api/v1/movies/{slug}/refresh
```

**Response:** `202 Accepted`
```json
{
  "message": "Movie refresh queued",
  "job_id": "uuid"
}
```

---

#### Report Movie Issue
```http
POST /api/v1/movies/{slug}/report
```

**Request Body:**
```json
{
  "reason": "INACCURATE_DATA",
  "description": "Release year is incorrect"
}
```

**Response:** `201 Created`
```json
{
  "message": "Report submitted",
  "report_id": "uuid"
}
```

---

### People

Similar endpoints to Movies:
- `GET /api/v1/people` - List people
- `GET /api/v1/people/{slug}` - Get person by slug
- `GET /api/v1/people/search` - Search people
- `POST /api/v1/people/bulk` - Bulk retrieve people
- `GET /api/v1/people/compare` - Compare people
- `GET /api/v1/people/{slug}/related` - Get related people
- `POST /api/v1/people/{slug}/refresh` - Refresh person data
- `POST /api/v1/people/{slug}/report` - Report person issue

---

### TV Series

Similar endpoints to Movies:
- `GET /api/v1/tv-series` - List TV series
- `GET /api/v1/tv-series/{slug}` - Get TV series by slug
- `GET /api/v1/tv-series/search` - Search TV series
- `GET /api/v1/tv-series/compare` - Compare TV series
- `GET /api/v1/tv-series/{slug}/related` - Get related TV series
- `POST /api/v1/tv-series/{slug}/refresh` - Refresh TV series data
- `POST /api/v1/tv-series/{slug}/report` - Report TV series issue

---

### TV Shows

Similar endpoints to Movies:
- `GET /api/v1/tv-shows` - List TV shows
- `GET /api/v1/tv-shows/{slug}` - Get TV show by slug
- `GET /api/v1/tv-shows/search` - Search TV shows
- `GET /api/v1/tv-shows/compare` - Compare TV shows
- `GET /api/v1/tv-shows/{slug}/related` - Get related TV shows
- `POST /api/v1/tv-shows/{slug}/refresh` - Refresh TV show data
- `POST /api/v1/tv-shows/{slug}/report` - Report TV show issue

---

### Generation

#### Generate Content
```http
POST /api/v1/generate
```

**Request Body:**
```json
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999",
  "locale": "pl-PL",
  "context_tag": "modern"
}
```

**Response:** `202 Accepted`
```json
{
  "job_id": "uuid",
  "status": "PENDING",
  "message": "Generation queued"
}
```

**Entity Types:**
- `MOVIE` - Generate movie description
- `PERSON` - Generate person biography
- `TV_SERIES` - Generate TV series description
- `TV_SHOW` - Generate TV show description

**Context Tags:**
- `modern` - Modern, dynamic style
- `critical` - Critical, analytical style
- `humorous` - Light, playful style
- `journalistic` - Objective, news-style
- `noir` - Dark, cinematic style
- `scholarly` - Academic, detailed style

---

### Jobs

#### Get Job Status
```http
GET /api/v1/jobs/{id}
```

**Response:** `200 OK`
```json
{
  "id": "uuid",
  "status": "DONE",
  "entity_type": "MOVIE",
  "entity_id": "uuid",
  "locale": "pl-PL",
  "context_tag": "modern",
  "result": {
    "description_id": "uuid",
    "text": "Generated description..."
  },
  "created_at": "2026-01-21T10:00:00Z",
  "updated_at": "2026-01-21T10:00:05Z"
}
```

**Status Values:**
- `PENDING` - Job queued, waiting for processing
- `PROCESSING` - Job currently being processed
- `DONE` - Job completed successfully
- `FAILED` - Job failed (check `error` field)

---

### Health Checks

#### OpenAI Health
```http
GET /api/v1/health/openai
```

**Response:** `200 OK`
```json
{
  "success": true,
  "service": "openai",
  "status": "operational"
}
```

---

#### TMDB Health
```http
GET /api/v1/health/tmdb
```

**Response:** `200 OK`
```json
{
  "success": true,
  "service": "tmdb",
  "status": "operational"
}
```

---

#### TVmaze Health
```http
GET /api/v1/health/tvmaze
```

**Response:** `200 OK`
```json
{
  "success": true,
  "service": "tvmaze",
  "status": "operational"
}
```

---

#### Database Health
```http
GET /api/v1/health/db
```

**Response:** `200 OK`
```json
{
  "success": true,
  "service": "database",
  "status": "operational"
}
```

---

#### Instance Health
```http
GET /api/v1/health/instance
```

**Response:** `200 OK`
```json
{
  "instance_id": "uuid",
  "version": "1.0.0",
  "environment": "local"
}
```

---

## 🔧 Request/Response Format

### Request Headers

**Required:**
```http
X-API-Key: mm_abc123def456...
Content-Type: application/json
```

**Optional:**
```http
X-Correlation-ID: uuid (for request tracking)
Accept: application/json
```

---

### Response Format

**Success Response:**
```json
{
  "data": {...},
  "_links": {
    "self": {
      "href": "/api/v1/movies/the-matrix-1999"
    }
  }
}
```

**Error Response:**
```json
{
  "error": "Error type",
  "message": "Human-readable error message",
  "details": {...} // Optional additional details
}
```

---

### HTTP Status Codes

| Code | Meaning | Usage |
|------|---------|-------|
| `200` | OK | Successful GET, PUT, PATCH |
| `201` | Created | Successful POST (resource created) |
| `202` | Accepted | Request accepted, processing async |
| `300` | Multiple Choices | Disambiguation required |
| `400` | Bad Request | Invalid request format |
| `401` | Unauthorized | Missing or invalid API key |
| `403` | Forbidden | Insufficient permissions |
| `404` | Not Found | Resource not found |
| `422` | Unprocessable Entity | Validation errors |
| `429` | Too Many Requests | Rate limit exceeded |
| `500` | Internal Server Error | Server error |
| `503` | Service Unavailable | External service unavailable |

---

## 🔗 HATEOAS (Hypermedia as the Engine of Application State)

All responses include `_links` object with related resources:

```json
{
  "data": {...},
  "_links": {
    "self": {
      "href": "/api/v1/movies/the-matrix-1999"
    },
    "people": [
      {
        "href": "/api/v1/people/keanu-reeves",
        "title": "Keanu Reeves",
        "role": "ACTOR",
        "character_name": "Neo"
      }
    ],
    "related": {
      "href": "/api/v1/movies/the-matrix-1999/related"
    },
    "collection": {
      "href": "/api/v1/movies/the-matrix-1999/collection"
    }
  }
}
```

---

## 📝 Request Tracing

All responses include tracing headers:

```http
X-Request-ID: uuid (auto-generated)
X-Correlation-ID: uuid (client-provided, optional)
```

**Usage:**
- `X-Request-ID`: Unique identifier for each request (auto-generated)
- `X-Correlation-ID`: Identifier for tracking related requests (can be provided by client)

---

## 🌍 Multilingual Support

### Supported Locales

- `en-US` (English - United States) - Default
- `pl-PL` (Polish - Poland)
- `de-DE` (German - Germany)
- `fr-FR` (French - France)
- `es-ES` (Spanish - Spain)

### Locale Parameter

Use `locale` query parameter to request localized content:

```http
GET /api/v1/movies/the-matrix-1999?locale=pl-PL
```

**Fallback Behavior:**
- If requested locale not available, falls back to `en-US`
- Metadata (titles, taglines) localized when available
- Descriptions generated in requested locale

---

## ⚠️ Error Handling

### Error Response Format

```json
{
  "error": "validation_error",
  "message": "The given data was invalid",
  "errors": {
    "slug": ["The slug field is required."],
    "locale": ["The selected locale is invalid."]
  }
}
```

### Common Errors

**401 Unauthorized:**
```json
{
  "error": "unauthorized",
  "message": "Invalid or missing API key"
}
```

**404 Not Found:**
```json
{
  "error": "not_found",
  "message": "Movie not found: the-matrix-1999"
}
```

**422 Unprocessable Entity:**
```json
{
  "error": "validation_error",
  "message": "The given data was invalid",
  "errors": {...}
}
```

**429 Too Many Requests:**
```json
{
  "error": "rate_limit_exceeded",
  "message": "You have exceeded your rate limit of 100 requests/minute",
  "retry_after": 60
}
```

---

## 📚 Best Practices

### 1. Use Bulk Operations

Instead of multiple individual requests:
```bash
# ❌ BAD: Multiple requests
GET /api/v1/movies/the-matrix-1999
GET /api/v1/movies/inception-2010
GET /api/v1/movies/interstellar-2014
```

Use bulk endpoint:
```bash
# ✅ GOOD: Single bulk request
POST /api/v1/movies/bulk
{
  "slugs": ["the-matrix-1999", "inception-2010", "interstellar-2014"]
}
```

---

### 2. Monitor Rate Limits

Check rate limit headers:
```http
X-RateLimit-Remaining: 95
```

Implement exponential backoff when rate limited.

---

### 3. Use Caching

Cache API responses to reduce requests:
- Cache successful responses
- Respect cache headers
- Invalidate cache on updates

---

### 4. Handle Async Operations

For generation requests:
1. Send `POST /api/v1/generate`
2. Receive `job_id`
3. Poll `GET /api/v1/jobs/{job_id}` until `status: DONE`
4. Use result

---

### 5. Use Correlation IDs

Include `X-Correlation-ID` header for request tracking:
```http
X-Correlation-ID: my-request-123
```

---

## 🔮 Production Considerations

### Base URL

**Portfolio/Demo:**
- Local: `http://localhost:8000/api`
- Staging: TBD

**Production:**
- Base URL: TBD (when deployed)
- HTTPS required
- API versioning: `/api/v1/`

### API Versioning

Current version: `v1`

Version included in URL path: `/api/v1/...`

Future versions: `/api/v2/...` (backward compatible)

---

## 📖 Related Documentation

- [OpenAPI Specification](../openapi.yaml) - Complete OpenAPI 3.0 spec
- [Architecture](ARCHITECTURE.md) - System architecture
- [Features](../business/FEATURES.md) - Business features
- [Subscription Plans](../business/SUBSCRIPTION_PLANS.md) - Plan details

---

**Last Updated:** 2026-01-21  
**Status:** Portfolio/Demo Project

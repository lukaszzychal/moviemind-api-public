# MovieMind API - Architecture Documentation

> **For:** Developers, Architects, Technical Leads  
> **Last Updated:** 2026-01-21  
> **Status:** Portfolio/Demo Project

---

## 🎯 Overview

This document provides a comprehensive overview of the MovieMind API architecture, including system design, component structure, data flow, and architectural decisions.

**Note:** This is a portfolio/demo project. For production deployment, see [Production Considerations](#production-considerations).

---

## 🏗️ System Architecture

### High-Level Architecture (C4 Context)

```
┌─────────────┐
│   Client    │
│ (API Users) │
└──────┬──────┘
       │
       │ HTTP/REST
       │
┌──────▼─────────────────────────────────────┐
│         MovieMind API (Laravel)            │
│  ┌──────────────────────────────────────┐  │
│  │  Controllers (API + Admin)           │  │
│  │  ↓                                   │  │
│  │  Services (Business Logic)           │  │
│  │  ↓                                   │  │
│  │  Repositories (Data Access)         │  │
│  │  ↓                                   │  │
│  │  Models (Database)                   │  │
│  └──────────────────────────────────────┘  │
│  ┌──────────────────────────────────────┐  │
│  │  Actions (Business Operations)       │  │
│  │  Events + Listeners (Async)          │  │
│  │  Jobs (Background Processing)        │  │
│  └──────────────────────────────────────┘  │
└──────┬──────────────────────────────────────┘
       │
       ├──► PostgreSQL (Database)
       ├──► Redis (Cache + Queue)
       │
       ├──► OpenAI API (AI Generation)
       ├──► TMDB API (Verification)
       └──► TVmaze API (Verification)
```

---

## 🧩 Component Architecture

### Layer Structure

```
┌─────────────────────────────────────────┐
│         HTTP Layer                      │
│  - Controllers (API + Admin)            │
│  - Middleware (Auth, Rate Limiting)     │
│  - Request Validation                  │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│      Business Logic Layer                │
│  - Actions (Business Operations)         │
│  - Services (Domain Logic)               │
│  - Response Formatters                   │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│      Data Access Layer                  │
│  - Repositories (Data Abstraction)      │
│  - Models (Eloquent ORM)                │
└─────────────────┬───────────────────────┘
                  │
┌─────────────────▼───────────────────────┐
│      Infrastructure Layer                │
│  - Database (PostgreSQL)                │
│  - Cache (Redis)                        │
│  - Queue (Laravel Horizon)              │
└─────────────────────────────────────────┘
```

---

## 📦 Core Components

### 1. Controllers

**Location:** `api/app/Http/Controllers/`

**Responsibilities:**
- Handle HTTP requests
- Validate input (via Form Requests)
- Delegate to Actions/Services
- Format responses (via Response Formatters)
- Return JSON responses

**Pattern:** Thin Controllers (max 20-30 lines per method)

**Examples:**
- `MovieController` - Movie endpoints
- `PersonController` - Person endpoints
- `GenerateController` - AI generation endpoint
- `JobsController` - Job status endpoint

**Architecture:**
```php
class MovieController extends Controller
{
    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly MovieRetrievalService $movieRetrievalService,
        private readonly MovieResponseFormatter $responseFormatter,
        // ... other dependencies
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        // Validate input
        // Delegate to service
        // Format response
        return $this->responseFormatter->formatSuccess($movie);
    }
}
```

---

### 2. Services

**Location:** `api/app/Services/`

**Responsibilities:**
- Business logic
- External API integration
- Complex operations
- Caching strategies

**Examples:**
- `MovieRetrievalService` - Movie retrieval logic
- `MovieSearchService` - Search functionality
- `TmdbVerificationService` - TMDB integration
- `TvmazeVerificationService` - TVmaze integration
- `AiGenerationTriggerService` - AI generation coordination

**Pattern:** Service Layer Pattern

**Architecture:**
```php
class MovieRetrievalService
{
    public function __construct(
        private readonly MovieRepository $movieRepository,
        private readonly EntityVerificationServiceInterface $verificationService
    ) {}

    public function retrieveMovie(string $slug, ?string $descriptionId): MovieRetrievalResult
    {
        // Business logic here
        // External API calls
        // Caching
    }
}
```

---

### 3. Actions

**Location:** `api/app/Actions/`

**Responsibilities:**
- Single business operations
- Complex workflows
- Event dispatching
- Job queuing

**Examples:**
- `QueueMovieGenerationAction` - Queue AI generation for movies
- `QueuePersonGenerationAction` - Queue AI generation for people

**Pattern:** Action Pattern (Single Responsibility)

**Architecture:**
```php
class QueueMovieGenerationAction
{
    public function handle(
        string $slug,
        ?float $confidence = null,
        ?Movie $existingMovie = null,
        // ... parameters
    ): array {
        // Single business operation
        // Return result
    }
}
```

---

### 4. Repositories

**Location:** `api/app/Repositories/`

**Responsibilities:**
- Data access abstraction
- Database queries
- Query optimization
- Data mapping

**Examples:**
- `MovieRepository` - Movie data access
- `PersonRepository` - Person data access

**Pattern:** Repository Pattern

**Architecture:**
```php
class MovieRepository
{
    public function findBySlug(string $slug): ?Movie
    {
        return Movie::where('slug', $slug)->first();
    }

    public function searchMovies(?string $query, int $limit = 50): Collection
    {
        // Search logic
    }
}
```

---

### 5. Models

**Location:** `api/app/Models/`

**Responsibilities:**
- Database representation
- Relationships
- Business rules (accessors, mutators)
- Validation

**Examples:**
- `Movie` - Movie entity
- `Person` - Person entity
- `MovieDescription` - Movie description entity
- `ApiKey` - API key entity

**Pattern:** Active Record (Eloquent ORM)

**Architecture:**
```php
class Movie extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['title', 'slug', 'release_year', ...];

    public function descriptions(): HasMany
    {
        return $this->hasMany(MovieDescription::class);
    }
}
```

---

### 6. Response Formatters

**Location:** `api/app/Http/Responses/`

**Responsibilities:**
- Consistent API responses
- Error formatting
- HATEOAS links
- Response structure

**Examples:**
- `MovieResponseFormatter` - Movie response formatting
- `PersonResponseFormatter` - Person response formatting

**Pattern:** Response Formatter Pattern

**Architecture:**
```php
class MovieResponseFormatter
{
    public function formatSuccess(Movie $movie, ...): JsonResponse
    {
        return response()->json([
            'data' => new MovieResource($movie),
            'links' => $this->hateoas->generateLinks($movie),
        ]);
    }
}
```

---

## 🔄 Data Flow

### Request Flow

```
1. Client Request
   ↓
2. Middleware (Auth, Rate Limiting)
   ↓
3. Controller (Validation, Delegation)
   ↓
4. Service/Action (Business Logic)
   ↓
5. Repository (Data Access)
   ↓
6. Model (Database Query)
   ↓
7. Response Formatter (Format Response)
   ↓
8. Client Response
```

### Async Processing Flow

```
1. Client Request (POST /api/v1/generate)
   ↓
2. Controller → Action
   ↓
3. Action → Event (MovieGenerationRequested)
   ↓
4. Listener → Job (RealGenerateMovieJob)
   ↓
5. Queue Worker (Laravel Horizon)
   ↓
6. Job → AI Service → OpenAI API
   ↓
7. Job → Save to Database
   ↓
8. Client Polls (GET /api/v1/jobs/{id})
```

---

## 🎨 Design Patterns

### 1. Repository Pattern

**Purpose:** Abstract data access layer

**Implementation:**
- `MovieRepository`, `PersonRepository`
- Encapsulates database queries
- Provides clean interface for data access

**Benefits:**
- Testability (easy to mock)
- Flexibility (can swap implementations)
- Separation of concerns

---

### 2. Service Layer Pattern

**Purpose:** Encapsulate business logic

**Implementation:**
- `MovieRetrievalService`, `MovieSearchService`
- Contains complex business operations
- Coordinates between repositories and external services

**Benefits:**
- Reusability
- Testability
- Business logic centralization

---

### 3. Action Pattern

**Purpose:** Single business operations

**Implementation:**
- `QueueMovieGenerationAction`
- Encapsulates single business operation
- Returns result (not side effects)

**Benefits:**
- Single responsibility
- Testability
- Composability

---

### 4. Event-Driven Architecture

**Purpose:** Decouple components and enable async processing

**Implementation:**
- Events: `MovieGenerationRequested`
- Listeners: `QueueMovieGenerationJob`
- Jobs: `RealGenerateMovieJob`

**Benefits:**
- Loose coupling
- Scalability
- Extensibility

---

### 5. Response Formatter Pattern

**Purpose:** Consistent API responses

**Implementation:**
- `MovieResponseFormatter`, `PersonResponseFormatter`
- Standardizes response structure
- Handles HATEOAS links

**Benefits:**
- Consistency
- Maintainability
- Versioning support

---

## 🗄️ Database Architecture

### Core Tables

**Movies:**
```sql
movies
├── id (UUIDv7, PK)
├── title
├── slug (unique)
├── release_year
├── director
├── genres (array)
├── tmdb_id
├── default_description_id (FK)
└── timestamps
```

**Movie Descriptions:**
```sql
movie_descriptions
├── id (UUIDv7, PK)
├── movie_id (FK)
├── locale (pl-PL, en-US, etc.)
├── text (AI-generated)
├── context_tag (modern, critical, etc.)
├── origin (GENERATED/TRANSLATED)
├── ai_model (gpt-4o-mini)
└── timestamps
```

**People:**
```sql
people
├── id (UUIDv7, PK)
├── name
├── slug (unique)
├── birth_date
├── birthplace
├── tmdb_id
├── default_bio_id (FK)
└── timestamps
```

**API Keys:**
```sql
api_keys
├── id (UUIDv7, PK)
├── name
├── key_hash (hashed API key)
├── prefix (mm_)
├── plan_id (FK)
├── is_active
└── timestamps
```

**Subscription Plans:**
```sql
subscription_plans
├── id (UUIDv7, PK)
├── name (free, pro, enterprise)
├── monthly_limit
├── rate_limit_per_minute
├── features (array)
└── timestamps
```

---

## 🔄 Async Processing Architecture

### Event-Driven Flow

```
Controller
  ↓
Action (QueueMovieGenerationAction)
  ↓
Event (MovieGenerationRequested)
  ↓
Listener (QueueMovieGenerationJob)
  ↓
Job (RealGenerateMovieJob implements ShouldQueue)
  ↓
Queue (Laravel Horizon)
  ↓
Worker (Processes Job)
  ↓
AI Service → OpenAI API
  ↓
Save to Database
```

### Job Configuration

**Queue:** `default` (configurable)

**Retry Logic:**
- Max attempts: 3
- Timeout: 300 seconds
- Backoff: Exponential

**Monitoring:**
- Laravel Horizon dashboard
- Job status tracking
- Failed job handling

---

## 🔐 Security Architecture

### Authentication

**API Key Authentication:**
- Header: `X-API-Key: <key>`
- Legacy: `X-RapidAPI-Key: <key>`
- Alternative: `Authorization: Bearer <key>`

**Storage:**
- API keys hashed (bcrypt)
- Prefix: `mm_`
- Plaintext shown only once on creation

### Authorization

**Plan-Based Access:**
- Rate limiting per plan
- Feature gating per plan
- Usage tracking per API key

### Security Measures

- Input validation (Form Requests)
- SQL injection prevention (parameterized queries)
- XSS prevention (output encoding)
- Rate limiting (abuse prevention)
- Request tracing (X-Request-ID, X-Correlation-ID)

---

## ⚡ Performance Architecture

### Caching Strategy

**Layers:**
1. **Application Cache (Redis):** API responses, AI-generated content
2. **Database Cache:** Query results, entity relationships
3. **External API Cache:** TMDB/TVmaze data (per license requirements)

**Cache Keys:**
- Format: `{entity}:{slug}:{locale}:{context_tag}`
- Example: `movie:the-matrix-1999:pl-PL:modern`

**Cache Duration:**
- AI-generated content: Permanent (until refresh)
- TMDB data: Maximum 6 months
- TVmaze data: Indefinite

### Rate Limiting

**Plan-Based Limits:**
- Free: 10 req/min, 100/month
- Pro: 100 req/min, 10,000/month
- Enterprise: 1,000 req/min, unlimited/month

**Implementation:**
- Middleware: `PlanBasedRateLimit`
- Tracking: `ApiUsage` model
- Headers: `X-RateLimit-*`

---

## 🔗 External Integrations

### TMDB Integration

**Service:** `TmdbVerificationService`

**Flow:**
```
Service → HTTP Client → TMDB API
  ↓
Response → Cache (Redis)
  ↓
Return Data
```

**Rate Limiting:**
- 40 requests per 10 seconds
- Caching: Maximum 6 months

**License:**
- Portfolio/Demo: Non-commercial (with attribution)
- Production: Commercial license required

---

### TVmaze Integration

**Service:** `TvmazeVerificationService`

**Flow:**
```
Service → HTTP Client → TVmaze API
  ↓
Response → Cache (Redis)
  ↓
Return Data
```

**Rate Limiting:**
- 20 requests per 10 seconds
- Caching: Indefinite

**License:**
- Portfolio/Demo & Production: CC BY-SA (commercial use allowed)

---

### OpenAI Integration

**Service:** `AiService` (via Jobs)

**Flow:**
```
Job → AiService → OpenAI API
  ↓
Response → Parse → Save to Database
```

**Configuration:**
- Model: `gpt-4o-mini` (default)
- Token tracking: Via `AiMetrics` model
- Cost monitoring: Admin API

---

## 📊 Monitoring & Observability

### Laravel Horizon

**Purpose:** Queue monitoring and management

**Features:**
- Job status tracking
- Queue metrics
- Failed job management
- Processing time statistics

**Access:** `/horizon` (admin only)

---

### Health Checks

**Endpoints:**
- `/api/v1/health/openai` - OpenAI API status
- `/api/v1/health/tmdb` - TMDB API status
- `/api/v1/health/tvmaze` - TVmaze API status
- `/api/v1/health/db` - Database status
- `/api/v1/health/instance` - Instance information

---

### Analytics

**Admin Endpoints:**
- `/api/v1/admin/analytics/overview` - Overview statistics
- `/api/v1/admin/analytics/by-plan` - Statistics by plan
- `/api/v1/admin/analytics/by-endpoint` - Statistics by endpoint
- `/api/v1/admin/ai-metrics/token-usage` - AI token usage

---

## 🚀 Scalability

### Horizontal Scaling

**Stateless Design:**
- No session state in API layer
- Shared cache (Redis) across instances
- Shared database (PostgreSQL)

**Queue Scaling:**
- Multiple Horizon workers
- Independent worker scaling
- Queue distribution

### Vertical Scaling

**Database:**
- Read replicas (future)
- Connection pooling
- Query optimization

**Cache:**
- Redis cluster (future)
- Cache sharding (future)

---

## 📝 Architectural Decisions

### ADR-001: Laravel-Only Architecture

**Decision:** Use Laravel for both API and admin panel

**Rationale:**
- Simplicity (single framework)
- Development speed
- Cost efficiency
- Developer experience

**Trade-offs:**
- Less flexibility than microservices
- Single point of failure (mitigated by horizontal scaling)

---

### ADR-002: Event-Driven Async Processing

**Decision:** Use Events + Listeners + Jobs for async processing

**Rationale:**
- Laravel best practices
- Loose coupling
- Extensibility
- Retry logic support

**Trade-offs:**
- Additional complexity
- Event ordering challenges (mitigated by job queues)

---

### ADR-003: Repository Pattern

**Decision:** Use Repository pattern for data access

**Rationale:**
- Testability (easy to mock)
- Flexibility (can swap implementations)
- Separation of concerns

**Trade-offs:**
- Additional abstraction layer
- Slight performance overhead (negligible)

---

## 🔮 Production Considerations

### Infrastructure

**Requirements:**
- PostgreSQL 15+ (with read replicas for high availability)
- Redis 7+ (with cluster for high availability)
- Laravel Horizon (for queue management)
- Nginx (reverse proxy)
- Docker (containerization)

### Security Hardening

- HTTPS only (TLS 1.3)
- API key rotation policies
- Rate limiting per IP (additional layer)
- DDoS protection
- Security headers (CSP, HSTS, etc.)

### Monitoring

- Application Performance Monitoring (APM)
- Error tracking (Sentry, Bugsnag)
- Log aggregation (ELK, Loki)
- Metrics (Prometheus, Grafana)
- Alerting (PagerDuty, Opsgenie)

### Backup & Disaster Recovery

- Database backups (daily, with retention)
- Cache backup strategy
- Disaster recovery plan
- RTO/RPO targets

---

## 📚 Related Documentation

- [API Specification](API_SPECIFICATION.md) - Detailed API documentation
- [Deployment Guide](DEPLOYMENT.md) - Deployment instructions
- [Integrations Guide](INTEGRATIONS.md) - External API integrations
- [Business Features](../business/FEATURES.md) - Business features overview
- [Requirements](../business/REQUIREMENTS.md) - Requirements specification

---

**Last Updated:** 2026-01-21  
**Status:** Portfolio/Demo Project

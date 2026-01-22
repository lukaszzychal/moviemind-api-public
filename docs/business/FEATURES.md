# MovieMind API - Features and Capabilities

> **For:** Business Stakeholders, Product Managers, Developers  
> **Last Updated:** 2026-01-21  
> **Status:** Portfolio/Demo Project

---

## 🎯 Overview

MovieMind API is a RESTful service that generates and stores unique AI-powered descriptions for movies, series, and actors. This document provides a comprehensive overview of all features and capabilities available in the API.

**Note:** This is a portfolio/demo project with full functionality for demonstration purposes. For production deployment, commercial licenses may be required (see [Third-Party API Licenses](#third-party-api-licenses)).

---

## ✨ Core Features

### 1. AI-Generated Content

**Description:**  
Creates unique, original descriptions and biographies using OpenAI/LLM APIs. Unlike traditional movie databases that copy content from IMDb or TMDb, MovieMind generates content from scratch.

**Key Capabilities:**
- **Uniqueness:** Every description is generated from scratch, ensuring no plagiarism
- **Quality:** AI-powered content tailored to specific contexts and styles
- **Flexibility:** Support for multiple languages and contextual styling

**Use Cases:**
- Generate movie descriptions in different styles (modern, critical, humorous)
- Create actor biographies with cultural adaptation
- Produce multilingual content for international audiences

**Example:**
```bash
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999",
  "locale": "pl-PL",
  "context_tag": "modern"
}
```

---

### 2. Multi-language Support

**Description:**  
Content generation and retrieval in multiple locales with intelligent fallback mechanisms.

**Supported Languages:**
- Polish (pl-PL)
- English (en-US)
- German (de-DE)
- French (fr-FR)
- Spanish (es-ES)

**Key Capabilities:**
- **Generation-first:** Long descriptions generated from scratch in target language
- **Translate-then-adapt:** Short summaries translated and culturally adapted
- **Automatic Fallback:** Falls back to en-US if requested locale is unavailable
- **Locale-specific Metadata:** Titles, taglines, and other metadata localized

**Use Cases:**
- Serve content to international audiences
- Generate descriptions in user's preferred language
- Provide culturally adapted content

**Example:**
```bash
GET /api/v1/movies/the-matrix-1999?locale=pl-PL
```

---

### 3. Contextual Styling

**Description:**  
Different description styles to match various use cases and audiences.

**Available Styles:**
- **Modern:** Dynamic, contemporary style
- **Critical:** Analytical, critical perspective
- **Humorous:** Light, playful tone
- **Journalistic:** Objective, news-style
- **Noir:** Dark, cinematic atmosphere
- **Scholarly:** Academic, detailed analysis

**Key Capabilities:**
- Multiple style variants per entity
- Style selection via `context_tag` parameter
- Style-specific generation prompts

**Use Cases:**
- Match content style to application theme
- Provide variety in content presentation
- Cater to different audience preferences

**Example:**
```bash
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999",
  "context_tag": "critical"
}
```

---

### 4. Smart Caching

**Description:**  
Redis-based caching system to avoid redundant AI calls and improve performance.

**Key Capabilities:**
- **Intelligent Cache Keys:** Based on entity, locale, and context tag
- **Cache Invalidation:** Automatic invalidation on content updates
- **Performance Optimization:** Reduces AI API calls and response times
- **Cost Reduction:** Minimizes OpenAI API usage

**Cache Strategy:**
- TMDB data: Maximum 6 months (per TMDB license requirements)
- TVmaze data: Indefinite (per TVmaze license)
- AI-generated content: Permanent until refresh

**Use Cases:**
- Reduce API costs
- Improve response times
- Handle high traffic efficiently

---

### 5. Async Processing

**Description:**  
Background job processing for AI generation using Laravel Horizon and queues.

**Key Capabilities:**
- **Non-blocking:** API returns immediately with job ID
- **Status Tracking:** Check job status via `/api/v1/jobs/{id}`
- **Retry Logic:** Automatic retry on failures
- **Queue Management:** Horizon dashboard for monitoring

**Job Statuses:**
- `PENDING`: Job queued, waiting for processing
- `PROCESSING`: Job currently being processed
- `DONE`: Job completed successfully
- `FAILED`: Job failed (with error details)

**Use Cases:**
- Handle long-running AI generation tasks
- Provide responsive API endpoints
- Monitor and manage background jobs

**Example:**
```bash
POST /api/v1/generate
# Returns: { "job_id": "...", "status": "PENDING" }

GET /api/v1/jobs/{job_id}
# Returns: { "status": "DONE", "result": {...} }
```

---

## 📊 Entity Management

### Movies

**Endpoints:**
- `GET /api/v1/movies` - List movies
- `GET /api/v1/movies/search` - Search movies
- `GET /api/v1/movies/{slug}` - Get movie details
- `GET /api/v1/movies/{slug}/related` - Get related movies
- `GET /api/v1/movies/{slug}/collection` - Get movie collection
- `POST /api/v1/movies/{slug}/refresh` - Refresh movie data
- `POST /api/v1/movies/bulk` - Bulk retrieve movies
- `GET /api/v1/movies/compare` - Compare movies

**Features:**
- Slug-based identification (`{title-slug}-{year}`)
- Disambiguation support (multiple movies with same title/year)
- Related movies (similar, sequels, prequels)
- Collection support (movie series)
- Bulk operations for efficiency

---

### People (Actors, Directors, etc.)

**Endpoints:**
- `GET /api/v1/people` - List people
- `GET /api/v1/people/search` - Search people
- `GET /api/v1/people/{slug}` - Get person details
- `GET /api/v1/people/{slug}/related` - Get related people
- `POST /api/v1/people/{slug}/refresh` - Refresh person data
- `POST /api/v1/people/bulk` - Bulk retrieve people
- `GET /api/v1/people/compare` - Compare people

**Features:**
- Slug-based identification (`{name-slug}-{birth-year}`)
- Disambiguation support
- Biography generation
- Related people (co-actors, frequent collaborators)
- Filmography and career information

---

### TV Series

**Endpoints:**
- `GET /api/v1/tv-series` - List TV series
- `GET /api/v1/tv-series/search` - Search TV series
- `GET /api/v1/tv-series/{slug}` - Get TV series details
- `GET /api/v1/tv-series/{slug}/related` - Get related TV series
- `POST /api/v1/tv-series/{slug}/refresh` - Refresh TV series data
- `GET /api/v1/tv-series/compare` - Compare TV series

**Features:**
- Scripted TV series (drama, comedy, sci-fi, etc.)
- TVmaze integration for verification
- Season and episode information
- Related series recommendations

---

### TV Shows

**Endpoints:**
- `GET /api/v1/tv-shows` - List TV shows
- `GET /api/v1/tv-shows/search` - Search TV shows
- `GET /api/v1/tv-shows/{slug}` - Get TV show details
- `GET /api/v1/tv-shows/{slug}/related` - Get related TV shows
- `POST /api/v1/tv-shows/{slug}/refresh` - Refresh TV show data
- `GET /api/v1/tv-shows/compare` - Compare TV shows

**Features:**
- Unscripted TV shows (talk shows, reality, news, documentaries)
- TVmaze integration for verification
- Episode information
- Related shows recommendations

---

## 🔐 Authentication & Authorization

### API Key Authentication

**Methods:**
- `X-API-Key: <api-key>` (standard)
- `Authorization: Bearer <api-key>` (alternative)

**Key Format:**
- Prefix: `mm_`
- Format: `mm_<random-string>`
- Example: `mm_abc123def456...`

**Management:**
- Created via Admin API (`POST /api/v1/admin/api-keys`)
- Demo keys via `ApiKeySeeder`
- Revocation and regeneration support

---

### Subscription Plans

**Free Plan:**
- Monthly Limit: 100 requests
- Rate Limit: 10 requests/minute
- Features: `read` (basic data access)

**Pro Plan:**
- Monthly Limit: 10,000 requests
- Rate Limit: 100 requests/minute
- Features: `read`, `generate`, `context_tags`

**Enterprise Plan:**
- Monthly Limit: Unlimited
- Rate Limit: 1,000 requests/minute
- Features: `read`, `generate`, `context_tags`, `webhooks`, `analytics`

**Note:** For portfolio/demo, subscriptions are managed locally via API keys. For production, billing providers (Stripe, PayPal) can be integrated.

---

## 🚀 Advanced Features

### 1. Bulk Operations

**Description:**  
Retrieve multiple entities in a single request for efficiency.

**Endpoints:**
- `POST /api/v1/movies/bulk`
- `POST /api/v1/people/bulk`

**Use Cases:**
- Batch processing
- Dashboard displays
- Data synchronization

**Example:**
```bash
POST /api/v1/movies/bulk
{
  "slugs": ["the-matrix-1999", "inception-2010", "interstellar-2014"]
}
```

---

### 2. Comparison

**Description:**  
Compare multiple entities side-by-side.

**Endpoints:**
- `GET /api/v1/movies/compare?slugs=the-matrix-1999,inception-2010`
- `GET /api/v1/people/compare?slugs=keanu-reeves-1964,laurence-fishburne-1961`

**Use Cases:**
- Comparison tools
- Recommendation engines
- Analysis dashboards

---

### 3. Related Content

**Description:**  
Get related entities (similar movies, co-actors, etc.).

**Endpoints:**
- `GET /api/v1/movies/{slug}/related`
- `GET /api/v1/people/{slug}/related`
- `GET /api/v1/tv-series/{slug}/related`
- `GET /api/v1/tv-shows/{slug}/related`

**Use Cases:**
- Recommendation systems
- Discovery features
- Content exploration

---

### 4. Reporting System

**Description:**  
Report issues with content (inaccuracies, missing data, etc.).

**Endpoints:**
- `POST /api/v1/movies/{slug}/report`
- `POST /api/v1/people/{slug}/report`
- `POST /api/v1/tv-series/{slug}/report`
- `POST /api/v1/tv-shows/{slug}/report`

**Use Cases:**
- Quality assurance
- User feedback
- Content improvement

---

### 5. Health Checks

**Description:**  
Monitor API health and external service connectivity.

**Endpoints:**
- `GET /api/v1/health/openai` - OpenAI API status
- `GET /api/v1/health/tmdb` - TMDB API status
- `GET /api/v1/health/tvmaze` - TVmaze API status
- `GET /api/v1/health/db` - Database status
- `GET /api/v1/health/instance` - Instance information

**Use Cases:**
- Monitoring
- Status dashboards
- Troubleshooting

---

## 🎛️ Admin Features

### Feature Flags

**Endpoints:**
- `GET /api/v1/admin/flags` - List all flags
- `POST /api/v1/admin/flags/{name}` - Enable flag
- `DELETE /api/v1/admin/flags/{name}` - Disable flag
- `GET /api/v1/admin/flags/usage` - Flag usage statistics

**Available Flags:**
- `ai_description_generation` - Enable AI movie description generation
- `ai_bio_generation` - Enable AI person biography generation
- `tmdb_verification` - Enable TMDB verification
- `tvmaze_verification` - Enable TVmaze verification
- `webhook_billing` - Enable billing webhooks

**Use Cases:**
- Feature toggling
- Gradual rollouts
- A/B testing
- Emergency feature disabling

---

### API Key Management

**Endpoints:**
- `GET /api/v1/admin/api-keys` - List all API keys
- `POST /api/v1/admin/api-keys` - Create new API key
- `POST /api/v1/admin/api-keys/{id}/revoke` - Revoke API key
- `POST /api/v1/admin/api-keys/{id}/regenerate` - Regenerate API key

**Use Cases:**
- Customer onboarding
- Key rotation
- Security management

---

### Analytics

**Endpoints:**
- `GET /api/v1/admin/analytics/overview` - Overview statistics
- `GET /api/v1/admin/analytics/by-plan` - Statistics by subscription plan
- `GET /api/v1/admin/analytics/by-endpoint` - Statistics by endpoint
- `GET /api/v1/admin/analytics/by-time-range` - Time-based statistics
- `GET /api/v1/admin/analytics/top-api-keys` - Top API keys by usage
- `GET /api/v1/admin/analytics/error-rate` - Error rate statistics

**Use Cases:**
- Usage monitoring
- Performance analysis
- Business intelligence

---

### AI Metrics

**Endpoints:**
- `GET /api/v1/admin/ai-metrics/token-usage` - OpenAI token usage
- `GET /api/v1/admin/ai-metrics/parsing-accuracy` - Parsing accuracy
- `GET /api/v1/admin/ai-metrics/errors` - Error statistics
- `GET /api/v1/admin/ai-metrics/comparison` - Format comparison

**Use Cases:**
- Cost monitoring
- Quality assurance
- Performance optimization

---

### Jobs Dashboard

**Endpoints:**
- `GET /api/v1/admin/jobs-dashboard/overview` - Overview statistics
- `GET /api/v1/admin/jobs-dashboard/by-queue` - Statistics by queue
- `GET /api/v1/admin/jobs-dashboard/recent` - Recent jobs
- `GET /api/v1/admin/jobs-dashboard/failed` - Failed jobs
- `GET /api/v1/admin/jobs-dashboard/failed/stats` - Failed jobs statistics
- `GET /api/v1/admin/jobs-dashboard/processing-times` - Processing time statistics

**Use Cases:**
- Queue monitoring
- Performance analysis
- Troubleshooting

---

### Reports Management

**Endpoints:**
- `GET /api/v1/admin/reports` - List all reports
- `POST /api/v1/admin/reports/{id}/verify` - Verify report

**Use Cases:**
- Content quality management
- User feedback processing

---

## 🔗 Integrations

### TMDB (The Movie Database)

**Purpose:**  
Movie and person data verification and metadata.

**Features:**
- Movie verification
- Person verification
- Metadata synchronization
- Collection information

**License Requirements:**
- **Portfolio/Demo:** Non-commercial use allowed (with attribution)
- **Production:** Commercial license required (contact: sales@themoviedb.org)

**Rate Limits:**
- 40 requests per 10 seconds
- Caching: Maximum 6 months

---

### TVmaze

**Purpose:**  
TV series and TV show data verification and metadata.

**Features:**
- TV series verification
- TV show verification
- Episode information
- Show metadata

**License Requirements:**
- **Portfolio/Demo & Production:** Commercial use allowed (CC BY-SA license)
- Attribution required

**Rate Limits:**
- 20 requests per 10 seconds
- Caching: Indefinite

---

### OpenAI

**Purpose:**  
AI content generation (descriptions, biographies).

**Features:**
- Description generation
- Biography generation
- Multilingual support
- Contextual styling

**Models:**
- `gpt-4o-mini` (default)
- Configurable via environment variables

**Cost Management:**
- Token usage tracking
- Cost monitoring via admin API
- Caching to reduce API calls

---

## 📈 Performance & Scalability

### Rate Limiting

**Plan-based Limits:**
- Free: 10 requests/minute, 100/month
- Pro: 100 requests/minute, 10,000/month
- Enterprise: 1,000 requests/minute, unlimited/month

**Headers:**
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Remaining requests
- `X-RateLimit-Reset`: Reset timestamp

---

### Caching Strategy

**Layers:**
1. **Application Cache (Redis):** API responses, AI-generated content
2. **Database Cache:** Query results, entity relationships
3. **External API Cache:** TMDB/TVmaze data (per license requirements)

**Benefits:**
- Reduced API costs
- Improved response times
- Better scalability

---

### Async Processing

**Queue System:**
- Laravel Horizon for job management
- Multiple queues for different job types
- Automatic retry on failures
- Job status tracking

**Benefits:**
- Non-blocking API responses
- Better resource utilization
- Improved user experience

---

## 🔒 Security

### API Key Security

- **Hashing:** API keys stored as hashed values (bcrypt)
- **Prefix:** All keys prefixed with `mm_` for identification
- **Revocation:** Immediate revocation support
- **Regeneration:** Secure key regeneration

### Request Tracing

**Headers:**
- `X-Request-ID`: Unique identifier for each request (auto-generated)
- `X-Correlation-ID`: Identifier for tracking related requests (client-provided)

**Use Cases:**
- Request tracking
- Debugging
- Log correlation

---

## 📚 Documentation

### Available Resources

- **OpenAPI Specification:** `docs/openapi.yaml`
- **Postman Collection:** `docs/postman/`
- **API Testing Guide:** `docs/API_TESTING_GUIDE.md`
- **Architecture Documentation:** `docs/c4/`
- **Business Documentation:** `docs/business/`
- **Technical Documentation:** `docs/knowledge/technical/`

---

## 🆚 Comparison with Competitors

### vs. IMDb/TMDb

**Advantages:**
- ✅ Unique, AI-generated content (no copying)
- ✅ Multiple languages and styles
- ✅ RESTful API with modern design
- ✅ Async processing for scalability

**Differences:**
- MovieMind generates content, competitors aggregate existing content
- MovieMind focuses on API-first approach
- MovieMind provides contextual styling options

---

## 🗺️ Roadmap

### Current Features (MVP)
- ✅ Core entity management (Movies, People, TV Series, TV Shows)
- ✅ AI content generation
- ✅ Multi-language support
- ✅ Contextual styling
- ✅ Subscription plans and rate limiting
- ✅ Admin panel and analytics

### Future Enhancements
- [ ] Style packs (predefined style combinations)
- [ ] Audience packs (content tailored to specific audiences)
- [ ] Advanced search (semantic search, fuzzy matching)
- [ ] Recommendation engine (ML-based)
- [ ] Webhook system enhancements
- [ ] GraphQL API (alternative to REST)

---

## 📞 Support & Resources

### Documentation
- **Business Docs:** `docs/business/`
- **Technical Docs:** `docs/knowledge/technical/`
- **QA Docs:** `docs/qa/`

### Related Documents
- [Subscription System](SUBSCRIPTION_SYSTEM.md)
- [Webhook System](WEBHOOK_SYSTEM_BUSINESS.md)
- [Requirements](REQUIREMENTS.md)

---

**Last Updated:** 2026-01-21  
**Status:** Portfolio/Demo Project

# MovieMind API - External Integrations

> **For:** Developers, Integrators  
> **Last Updated:** 2026-01-21  
> **Status:** Portfolio/Demo Project

---

## 🎯 Overview

This document provides detailed information about external API integrations used by MovieMind API, including configuration, usage, rate limiting, and licensing requirements.

**Note:** This is a portfolio/demo project. For production deployment, commercial licenses may be required (see [License Requirements](#license-requirements)).

---

## 🎬 TMDB (The Movie Database) Integration

### Overview

TMDB provides movie and person metadata for verification and data enrichment.

### Service Implementation

**Service:** `TmdbVerificationService`

**Location:** `api/app/Services/TmdbVerificationService.php`

**Interface:** `EntityVerificationServiceInterface`

### Configuration

**Environment Variables:**
```env
TMDB_API_KEY=your-tmdb-api-key
TMDB_BASE_URL=https://api.themoviedb.org/3
TMDB_CACHE_TTL=15552000  # 6 months in seconds (max per license)
```

**Feature Flag:**
- `tmdb_verification` - Enable/disable TMDB verification

### API Endpoints Used

**Movie Verification:**
```
GET https://api.themoviedb.org/3/movie/{tmdb_id}
```

**Person Verification:**
```
GET https://api.themoviedb.org/3/person/{tmdb_id}
```

**Search:**
```
GET https://api.themoviedb.org/3/search/movie?query={query}
GET https://api.themoviedb.org/3/search/person?query={query}
```

### Rate Limiting

- **Limit:** 40 requests per 10 seconds
- **Implementation:** `TmdbRateLimiter` middleware
- **Caching:** Maximum 6 months (per license requirements)

### Caching Strategy

**Cache Keys:**
- Movies: `tmdb:movie:{tmdb_id}`
- People: `tmdb:person:{tmdb_id}`
- Search: `tmdb:search:movie:{query}`

**Cache Duration:**
- Maximum 6 months (per TMDB license requirements)
- Automatic invalidation on updates

### Error Handling

**Retry Logic:**
- Exponential backoff
- Max 3 attempts
- Graceful degradation (returns null if verification fails)

**Error Responses:**
- `401 Unauthorized` - Invalid API key
- `404 Not Found` - Entity not found in TMDB
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - TMDB service error

### License Requirements

**Portfolio/Demo:**
- ✅ Non-commercial use allowed
- ✅ Attribution required (logo + text + link)
- ✅ Cache duration: Maximum 6 months

**Production:**
- ❌ Commercial license required
- Contact: sales@themoviedb.org
- Estimated cost: ~$149/month (small apps) to $42,000/year (enterprise)

**Attribution Requirements:**
- Display TMDB logo (less prominent than own logo)
- Text: "This [application] uses TMDB and the TMDB APIs but is not endorsed, certified, or otherwise approved by TMDB."
- Link: https://www.themoviedb.org

**Related Documentation:**
- [TMDB License Analysis](../../knowledge/technical/API_LEGAL_ANALYSIS_TMDB_TVMAZE.md)
- [TMDB Legal License](../../LEGAL_TMDB_LICENSE.md)

---

## 📺 TVmaze Integration

### Overview

TVmaze provides TV series and TV show metadata for verification and data enrichment.

### Service Implementation

**Service:** `TvmazeVerificationService`

**Location:** `api/app/Services/TvmazeVerificationService.php`

**Direct Injection:** Injected directly into `TvSeriesRetrievalService` and `TvShowRetrievalService`

### Configuration

**Environment Variables:**
```env
TVMAZE_BASE_URL=https://api.tvmaze.com
TVMAZE_CACHE_TTL=0  # Indefinite (allowed per license)
```

**Feature Flag:**
- `tvmaze_verification` - Enable/disable TVmaze verification

### API Endpoints Used

**TV Series Verification:**
```
GET https://api.tvmaze.com/singlesearch/shows?q={slug}
```

**TV Show Verification:**
```
GET https://api.tvmaze.com/singlesearch/shows?q={slug}
```

**Health Check:**
```
GET https://api.tvmaze.com/shows/1
```

### Rate Limiting

- **Limit:** 20 requests per 10 seconds
- **Implementation:** `TvmazeRateLimiter` middleware
- **Caching:** Indefinite (allowed per license)

### Caching Strategy

**Cache Keys:**
- TV Series: `tvmaze:tv_series:{slug}`
- TV Shows: `tvmaze:tv_show:{slug}`

**Cache Duration:**
- Indefinite (per TVmaze license - allowed)

### Error Handling

**Retry Logic:**
- Exponential backoff
- Max 3 attempts
- Graceful degradation (returns null if verification fails)

**Error Responses:**
- `404 Not Found` - Entity not found in TVmaze
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - TVmaze service error

### License Requirements

**Portfolio/Demo & Production:**
- ✅ Commercial use allowed
- ✅ License: Creative Commons Attribution-ShareAlike (CC BY-SA)
- ✅ Attribution required (link to TVmaze)
- ✅ Cache duration: Indefinite (allowed)

**Attribution Requirements:**
- Link to TVmaze: https://www.tvmaze.com
- ShareAlike: If redistributing data, must use CC BY-SA license

**Related Documentation:**
- [TVmaze License Analysis](../../knowledge/technical/API_LEGAL_ANALYSIS_TMDB_TVMAZE.md)
- [TVmaze Legal License](../../LEGAL_TVMAZE_LICENSE.md)

---

## 🤖 OpenAI Integration

### Overview

OpenAI provides AI content generation for movie descriptions and person biographies.

### Service Implementation

**Service:** `AiService` (via Jobs)

**Location:** `api/app/Services/AiService.php`

**Jobs:**
- `RealGenerateMovieJob` - Generate movie descriptions
- `RealGeneratePersonJob` - Generate person biographies
- `RealGenerateTvSeriesJob` - Generate TV series descriptions
- `RealGenerateTvShowJob` - Generate TV show descriptions

### Configuration

**Environment Variables:**
```env
AI_SERVICE=real  # or 'mock' for testing
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o-mini
OPENAI_URL=https://api.openai.com/v1/chat/completions
OPENAI_HEALTH_URL=https://api.openai.com/v1/models
OPENAI_BACKOFF_ENABLED=true
OPENAI_BACKOFF_INTERVALS=20,60,180
```

**Feature Flags:**
- `ai_description_generation` - Enable AI movie description generation
- `ai_bio_generation` - Enable AI person biography generation

### API Endpoints Used

**Chat Completions:**
```
POST https://api.openai.com/v1/chat/completions
```

**Models:**
```
GET https://api.openai.com/v1/models
```

### Rate Limiting

- **Limit:** Per OpenAI account tier
- **Implementation:** Exponential backoff in `AiService`
- **Caching:** Generated content cached permanently (until refresh)

### Caching Strategy

**Cache Keys:**
- Movie Descriptions: `movie:{slug}:{locale}:{context_tag}`
- Person Bios: `person:{slug}:{locale}:{context_tag}`

**Cache Duration:**
- Permanent (until refresh via `/refresh` endpoint)

### Error Handling

**Retry Logic:**
- Exponential backoff (20s, 60s, 180s)
- Max 3 attempts
- Job marked as FAILED if all attempts fail

**Error Responses:**
- `401 Unauthorized` - Invalid API key
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - OpenAI service error
- `503 Service Unavailable` - OpenAI service unavailable

### Cost Management

**Token Tracking:**
- Tracked via `AiMetrics` model
- Admin API: `/api/v1/admin/ai-metrics/token-usage`

**Cost Optimization:**
- Caching to reduce API calls
- Efficient prompts
- Model selection (gpt-4o-mini for cost efficiency)

### Prompt Engineering

**Movie Description Prompt:**
```
Write a concise, unique description of the movie {title} from {year}.
Style: {context_tag}.
Length: 2-3 sentences, natural language, no spoilers.
Language: {locale}.
Return only plain text.
```

**Person Biography Prompt:**
```
Write a concise, unique biography of {name} (born {birth_year}).
Style: {context_tag}.
Length: 3-4 sentences, natural language.
Language: {locale}.
Return only plain text.
```

---

## 🔄 Integration Flow Diagrams

### TMDB Verification Flow

```
Controller
  ↓
Service (MovieRetrievalService)
  ↓
TmdbVerificationService
  ↓
Check Cache (Redis)
  ↓ (if not cached)
HTTP Client → TMDB API
  ↓
Cache Response (6 months max)
  ↓
Return Data
```

### TVmaze Verification Flow

```
Controller
  ↓
Service (TvSeriesRetrievalService)
  ↓
TvmazeVerificationService
  ↓
Check Cache (Redis)
  ↓ (if not cached)
HTTP Client → TVmaze API
  ↓
Cache Response (indefinite)
  ↓
Return Data
```

### OpenAI Generation Flow

```
Controller
  ↓
Action (QueueMovieGenerationAction)
  ↓
Event (MovieGenerationRequested)
  ↓
Listener → Job (RealGenerateMovieJob)
  ↓
Queue Worker (Horizon)
  ↓
AiService → OpenAI API
  ↓
Parse Response
  ↓
Save to Database
  ↓
Cache Result
```

---

## 📊 Integration Monitoring

### Health Checks

**TMDB:**
```http
GET /api/v1/health/tmdb
```

**TVmaze:**
```http
GET /api/v1/health/tvmaze
```

**OpenAI:**
```http
GET /api/v1/health/openai
```

### Metrics

**Admin Endpoints:**
- `/api/v1/admin/ai-metrics/token-usage` - OpenAI token usage
- `/api/v1/admin/ai-metrics/parsing-accuracy` - Parsing accuracy
- `/api/v1/admin/ai-metrics/errors` - Error statistics

---

## 🔒 Security Considerations

### API Keys

**Storage:**
- Environment variables (never in code)
- `.env` file (not committed to repository)
- Secret management (Vault, AWS Secrets Manager) for production

### Rate Limiting

**Implementation:**
- Per-service rate limiters
- Exponential backoff
- Graceful degradation

### Error Handling

**Best Practices:**
- Never expose API keys in error messages
- Log errors without sensitive data
- Retry with exponential backoff
- Graceful degradation when services unavailable

---

## 📚 Related Documentation

- [Architecture](ARCHITECTURE.md) - System architecture
- [API Specification](API_SPECIFICATION.md) - API documentation
- [Deployment](DEPLOYMENT.md) - Deployment guide
- [TMDB Legal License](../../LEGAL_TMDB_LICENSE.md) - TMDB license requirements
- [TVmaze Legal License](../../LEGAL_TVMAZE_LICENSE.md) - TVmaze license requirements

---

**Last Updated:** 2026-01-21  
**Status:** Portfolio/Demo Project

# MovieMind API - Complete Specification and Action Plan

> Note: This document consolidates specifications and action plans for the MovieMind API project.

## 🎬 Project Overview

MovieMind API is an AI-powered Film & Series Metadata API that generates and stores unique descriptions for movies, series, and actors using AI, caching, and automatic selection of the best content versions.

### 🎯 Main Goals

Provide an API that:
- generates and stores unique descriptions for movies, series, and actors
- uses AI (e.g., ChatGPT / LLM API) for content creation
- ensures uniqueness (no copying from IMDb, TMDb, etc.)
- enables caching, multilingualism, and style tagging
- allows clients to retrieve data through REST API

### 💡 Product Type

MVP (Minimum Viable Product) — first working version with minimal feature scope, ready for deployment on RapidAPI.

Not PoC, because:
- PoC = only proof that AI text generation is possible (without system, database, API)
- MVP = real API, cache, minimal storage, and API key

---

## 🏗️ Dual-Repository Strategy

### Approach

| Aspect               | Public Repository                                  | Private Repository                                  |
| -------------------- | --------------------------------------------------- | --------------------------------------------------- |
| Goal                 | Portfolio, skills demonstration                     | Production, commercial product                      |
| Content              | Trimmed code, mock AI, documentation                | Full code, real AI, billing, webhooks               |
| Security             | No API keys, sample data                            | Real keys, production data                          |
| License              | MIT / CC-BY-NC                                      | Custom commercial                                   |
| Timeline             | 6 weeks (MVP)                                       | 8-12 weeks (full product)                           |

### Why This Is a Good Solution

1) Image — public repo shows architecture, structure, clean code, best practices.
2) Security & flexibility — private repo can contain secrets, full workflows, monitoring, and proprietary AI logic.

### Practical Division

Public repo (`moviemind-api-public`):
```
├── README.md
├── src/
├── docker-compose.yml
├── docs/
├── .env.example
├── .gitignore
├── .gitleaks.toml
└── LICENSE
```

Private repo (`moviemind-api-private`):
```
├── all public files
├── .env.production
├── src/AI/
├── src/Webhooks/
├── src/Billing/
├── src/Admin/
├── tests/integration/
└── LICENSE (commercial)
```

---

## 🧩 MVP Scope

### Functional Scope

Client can:
| Endpoint              | Description                                      |
| --------------------- | ------------------------------------------------ |
| GET /v1/movies?q=     | search movies (title, year, genre)              |
| GET /v1/movies/{slug} | get movie details + description (AI or cache)   |
| GET /v1/people/{slug} | get person (actor, director, etc.) + bio        |
| GET /v1/actors/{id}   | get actor details + biography                    |
| POST /v1/generate     | force new generation: entity_type = MOVIE or PERSON |
| GET /v1/jobs/{id}     | check generation status (PENDING, DONE, FAILED) |
#### 📘 Example Payloads (Request/Response)

##### POST `/v1/generate` — MOVIE
Request
```json
{
  "entity_type": "MOVIE",
  "entity_id": 123,
  "locale": "pl-PL",
  "context_tag": "modern"
}
```

Response 200 (mock)
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "PENDING"
}
```

Response 403 (feature disabled)
```json
{
  "error": "Feature not available"
}
```

##### POST `/v1/generate` — PERSON
Request
```json
{
  "entity_type": "PERSON",
  "entity_id": 456,
  "locale": "en-US",
  "context_tag": "scholarly"
}
```

##### GET `/v1/movies/{slug}` — example response
```json
{
  "id": 123,
  "title": "The Matrix",
  "release_year": 1999,
  "director": "The Wachowskis",
  "genres": ["Action","Sci-Fi"],
  "default_description": {
    "id": 999,
    "locale": "pl-PL",
    "text": "Concise description…",
    "context_tag": "modern"
  }
}
```

##### GET `/v1/people/{slug}` — example response
```json
{
  "id": 456,
  "name": "Keanu Reeves",
  "bios": [
    { "locale": "en-US", "text": "Short bio…" }
  ],
  "movies": [
    { "id": 123, "title": "The Matrix" }
  ]
}
```

##### GET `/v1/jobs/{id}` — example response
```json
{
  "id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "PENDING"
}
```

System internally:
- stores data in PostgreSQL (movies, actors, descriptions, bios, jobs)
- if data missing and feature flag enabled → returns 202 and queues generation (see below)
- stores result in DB and cache (Redis)
- next request hits cache (no AI)
- every generation stored with context (modern, critical, humorous, …)

### MVP Technologies (Laravel Architecture)

| Container        | Technology             | Responsibility                                        |
| ---------------- | ---------------------- | ----------------------------------------------------- |
| Laravel API      | PHP 8.3 + Laravel 11   | REST endpoints (movies, actors, AI generation)        |
| Admin Panel      | Laravel (Nova/Breeze)  | Data management, AI models, monitoring               |
| AI Service       | OpenAI SDK (PHP)       | Generates content in Laravel Jobs                    |
| Database         | PostgreSQL             | Content, metadata, versions, tags, quality ratings   |
| Cache            | Redis                  | Caching API/AI results                               |
| Task Queue       | Laravel Horizon        | Queueing AI generation, async processing             |

Optional public layer: `/src-fastapi` — Python + FastAPI + Celery + RabbitMQ + Redis

### Data Structure (selected tables)

movies
- id (int), title (varchar), release_year (smallint), director (varchar), genres (text[]), default_description_id (int)

movie_descriptions
- id, movie_id (FK), locale (varchar10), text, context_tag, origin, ai_model, created_at

actors
- id, name, birth_date, birthplace, default_bio_id

actor_bios
- id, actor_id, locale, text, context_tag, origin, ai_model, created_at

jobs
- id, entity_type (MOVIE/ACTOR), entity_id, locale, status, payload_json, created_at

### MVP Flow (Happy Path)
1) Client: GET /v1/movies/123 → check DB for movie_descriptions
2) If missing → create job and queue generation
3) Worker generates via AI, stores result, sets job DONE
4) Client: GET /v1/jobs/{id} → status + result
5) Further GETs hit cache/DB

### Example Prompt (EN)
```
Write a concise, unique description of the movie {title} ({year}).
Style: {context_tag}.
Length: 2–3 sentences, natural language, no spoilers.
Language: {locale}.
Return plain text only.
```

### Runtime Environment (excerpt)
```yaml
version: "3.9"
services:
  api:
    build: .
    ports: ["8000:80"]
    environment:
      DATABASE_URL: postgresql://moviemind:moviemind@db:5432/moviemind
      OPENAI_API_KEY: sk-xxxx
      APP_ENV: dev
    depends_on: [db, redis]
  db:
    image: postgres:15
  redis:
    image: redis:7
```

### Out of Scope (MVP)
- No admin UI
- No webhooks
- No plans/billing
- No multiuser auth
- No monitoring/metrics
- No AI version comparison (only generate + store)
- No automatic translations

### MVP Output
- Repository with Docker
- Working REST endpoints under /v1
- PostgreSQL data
- Async AI generation (Laravel Jobs)
- README and OpenAPI YAML

---

## 📋 Action Plan — 10 Phases (high level)
1) Setup & Structure (Week 1)
2) Infrastructure & Docker (Week 2)
3) Mock API Endpoints (Week 3)
4) Mock AI Integration (Week 4)
5) Real AI Integration (Weeks 5-6)
6) Caching & Performance (Week 7)
7) Multilingual Support (Week 8)
8) Testing & QA (Week 9)
9) Documentation & API Docs (Week 10)
10) RapidAPI Preparation & Launch (Weeks 11-12)

---

## 🌳 Git Trunk Flow

Strategy: Git Trunk Flow as the main code management approach.

Advantages: simpler workflow, faster integrations, fewer conflicts, better CI/CD, feature flags, easy rollback.

Workflow: feature branch → PR → merge → deploy → feature flag control.

---

## 🎛️ Feature Flags

We use Laravel Pennant feature flags (`laravel/pennant`).

Types: boolean, percentage, user-based, environment.

Feature-flag controlled behavior:
- If `ai_description_generation` is ON and `GET /v1/movies/{slug}` misses, API returns 202 Accepted with `{ job_id, status: PENDING, slug }` and queues generation.
- If `ai_bio_generation` is ON and `GET /v1/people/{slug}` misses, API returns 202 Accepted with `{ job_id, status: PENDING, slug }` and queues generation.
- If the corresponding flag is OFF, API returns 404 Not Found.

Admin endpoints for flags:
- `GET /v1/admin/flags` — list flags
- `POST /v1/admin/flags/{name}` body `{ "state": "on" | "off" }` — toggle
- `GET /v1/admin/flags/usage` — usage stats

Example configuration:
```php
<?php
return [
    'ai_description_generation' => true,
    'gpt4_generation' => [
        'enabled' => true,
        'percentage' => 25
    ],
    'multilingual_support' => [
        'enabled' => true,
        'percentage' => 50
    ],
    'style_packs' => false
];
```

---

## 🔐 Security & Key Management

- Never commit real API keys
- Use `.env` locally/on server; commit only `.env.example`

Environment files and CI secrets should be configured securely.

---

## 💰 Monetization (RapidAPI)

| Plan       | Limit                 | Features                                           |
| ---------- | --------------------- | -------------------------------------------------- |
| Free       | 100 requests/month    | DB-only access (no generation)                     |
| Pro        | 10,000 requests/month | AI regeneration and context selection              |
| Enterprise | Unlimited             | API + dedicated AI models + webhooks               |

---

## ⚖️ Licensing Strategy

- Portfolio only: "No License" or CC BY-NC
- Open source in portfolio: MIT or Apache 2.0
- Commercial SaaS: dual license (public MIT/CC-BY-NC, private commercial)

---

## 🎯 Strategy Summary

Public vs Private repos differ in code, security, tests, docs, and licensing. Start with MVP in public, scale to private features for production.

Next Steps (suggested):
1) Weeks 1-2: Setup repos & infra
2) Weeks 3-4: Implement mock API (public)
3) Weeks 5-6: Implement real AI (private)
4) Weeks 7-8: Caching & multilingual
5) Weeks 9-10: Tests & docs
6) Weeks 11-12: RapidAPI & launch

Key Principles: security, separation, quality, documentation.

---

Document created: 2025-01-27



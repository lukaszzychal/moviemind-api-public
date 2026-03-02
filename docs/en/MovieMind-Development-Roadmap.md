# 🎬 MovieMind API - Development Roadmap
## 🇬🇧 Project Development Plan

---

## 📋 Table of Contents

1. [Project Goal](#project-goal)
2. [MVP - Public Repository](#mvp---public-repository)
3. [MVP - Private Repository](#mvp---private-repository)
4. [Development Stages](#development-stages)
5. [Laravel Architecture](#laravel-architecture)
6. [Multilingual Support](#multilingual-support)
7. [Advanced Features](#advanced-features)
8. [Monetization](#monetization)
9. [Git Trunk Flow](#git-trunk-flow)
10. [Feature Flags](#feature-flags)
11. [Timeline](#timeline)

---

## Project Goal

MovieMind API is an intelligent API that generates and stores unique descriptions, biographies, and data about movies, series, and actors using AI models.

### Key Objectives
- Content Uniqueness — every description generated from scratch by AI
- Multilingual Support — intelligent translation across multiple languages
- Versioning — comparison and selection of best description versions
- Scalability — hybrid Python + PHP architecture
- Monetization — API-as-a-Service through RapidAPI

### Dual-Repository Strategy
- Public repo — portfolio, skills demonstration
- Private repo — full commercial product with AI, billing, webhooks

---

## MVP - Public Repository

### Public MVP Goal
Demonstrate architecture, code quality, and design approach without revealing commercial secrets.

### Project Structure
```
moviemind-api-public/
├── api/                     # Laravel application (public API)
│   ├── app/
│   │   ├── Actions/
│   │   ├── Http/Controllers/Api/
│   │   ├── Jobs/
│   │   ├── Services/
│   │   └── ...
│   ├── routes/
│   │   ├── api.php          # V1 public REST API
│   │   ├── web.php          # Root status endpoint
│   │   └── console.php
│   ├── composer.json
│   └── package.json
├── docs/
├── docker-compose.yml
└── README.md
```

### Public Demo Features

| Component          | Showcase Scope                                                        | Status                   |
| ------------------ | --------------------------------------------------------------------- | ------------------------ |
| Laravel API        | REST endpoints (movies, people, job status) + feature flag toggles     | ✅ Demo-ready             |
| Admin UI           | CRUD, feature flag management, demo auth roles                        | ❌ Private edition only   |
| Webhooks           | Simulator endpoints with payload inspector and retry flow             | ❌ Planned                |
| AI Jobs            | `AI_SERVICE=mock` deterministic jobs + `AI_SERVICE=real` OpenAI calls | ✅ Dual-mode              |
| Queue & Monitoring | Laravel Horizon configuration                                         | ⚠️ Requires manual start  |
| Database           | PostgreSQL schema with multilingual content tables                    | ✅ Available              |
| Cache              | Redis integration for job status caching                              | ⚠️ To be expanded         |
| Security           | GitLeaks, pre-commit hooks, branch protection guides                  | ✅ Enforced               |

### Portfolio Showcases

- Admin UI screencast highlighting feature flags, CRUD flows, and role-based access
- Webhook simulator walk-through (payload replay, signature verification)
- AI dual-mode demo: `mock` vs `real` execution with Horizon/Telescope insights
- Monitoring bundle tour (Grafana JSON, Telescope filters, queue depth alerts)
- Architecture overview deck covering the single Laravel service strategy and delivery flow

### MVP Endpoints
```php
// Laravel - Public API (routes/api.php)
GET  /api/v1/movies               # List movies
GET  /api/v1/movies/{slug}        # Movie details + auto-generation when missing (AI)
POST /api/v1/generate             # Generate description (mock/real)
GET  /api/v1/jobs/{id}            # Job status
```

```php
// Laravel - Admin API (routes/api.php)
GET  /api/v1/admin/flags         # Feature flag overview
POST /api/v1/admin/flags/{name}  # Toggle flag on/off
GET  /api/v1/admin/flags/usage   # Static usage report
```

---

## MVP - Private Repository

### Private MVP Goal
Full commercial product with real AI integration, billing, and SaaS features.

### Private MVP Features

| Component      | Functionality            | Difference vs Public |
| -------------- | ------------------------ | -------------------- |
| AI Integration | OpenAI GPT-4o, Claude    | Mock → Real AI       |
| Billing        | Local API keys (demo), Stripe/PayPal (production) | Local → Full billing  |
| Rate Limiting  | Free/pro/enterprise plans| None → Advanced      |
| Monitoring     | Prometheus, Grafana      | Basic → Full         |
| Security       | OAuth, JWT, encryption   | Basic → Enterprise   |
| CI/CD          | GitHub Actions, deploy   | None → Automation    |

### Additional Private Endpoints

```php
// Laravel - Single service (Public + Admin)
POST /admin/billing/webhook   # Billing webhooks (prepared for Stripe/PayPal)
GET  /admin/analytics/usage   # Usage statistics
POST /admin/ai/regenerate     # Force regeneration
GET  /admin/health/detailed   # Health check
```

---

## Development Stages

### Stage 1: Foundation (Weeks 1-2)
Goal: Basic infrastructure and architecture

Tasks:
- [ ] Project setup — Laravel structure, Docker
- [ ] Database schema — basic tables (movies, actors, descriptions)
- [ ] Laravel API — basic public REST endpoints
- [ ] Laravel Admin — admin panel with CRUD
- [ ] Redis cache — basic caching
- [ ] Laravel Horizon — setup async jobs
- [ ] GitLeaks security — pre-commit hooks

Deliverables:
- Working development environment
- Basic API endpoints
- Admin panel with data management
- Architecture documentation

### Stage 2: AI Integration (Weeks 3-4)
Goal: AI integration and description generation

Tasks:
- [ ] OpenAI integration — connection to GPT-4o
- [ ] Prompt engineering — templates for different contexts
- [ ] Async processing — Laravel Horizon workers for long-running jobs
- [ ] Quality scoring — content quality assessment
- [ ] Plagiarism detection — similarity detection
- [ ] Version management — description version storage

Deliverables:
- Real AI description generation
- Content quality assessment system
- Asynchronous task processing
- Description version comparison

### Stage 3: Multilingual (Weeks 5-6)
Goal: Multilingual support

Tasks:
- [ ] Language detection — automatic language detection
- [ ] Translation pipeline — translation vs generation
- [ ] Glossary system — non-translatable terms dictionary
- [ ] Locale-specific content — region-adapted content
- [ ] Fallback mechanisms — fallback mechanisms
- [ ] Cultural adaptation — cultural adaptation

Deliverables:
- Support for 5+ languages (PL, EN, DE, FR, ES)
- Intelligent translation strategy selection
- Specialized terms dictionary
- Culturally adapted content

### Stage 4: Observability & Integrations (Weeks 7-8)
Goal: Showcase operations capabilities without exposing secrets

Tasks:
- [ ] Webhook simulator — demo endpoints, signature validation, replay tooling
- [ ] Monitoring bundle — Telescope, Horizon dashboard presets, sample Grafana json
- [ ] Alerting demo — mail/slack notifications using fake channels
- [ ] Admin analytics — lightweight dashboards (jobs, AI usage, feature toggles)
- [ ] Documentation polish — portfolio walkthrough, diagrams, demo scripts

Deliverables:
- Webhook showcase with inspector
- Observability toolkit packaged for demos
- Admin analytics widgets
- Updated docs and demo guides

### Stage 5: Monetization & Advanced Features (Weeks 9-10)
Goal: Bridge from showcase to commercial deployment

Tasks:
- [x] Subscription plans — plan matrix, rate-limit policies, feature gating (✅ Completed - local API keys)
- [ ] Billing provider integration — Stripe/PayPal integration for production (optional)
- [ ] Style packs & recommendation — highlight advanced AI capabilities
- [ ] Usage analytics — dashboards for AI cost, request volume, locales
- [ ] Production playbooks — deployment runbooks, security checklist

Deliverables:
- Monetization-ready plan definitions
- Advanced AI feature demos
- Usage analytics dashboards
- Operational playbooks

---

## Laravel Architecture

### System Components

| Component      | Technology   | Role               | Port |
| ---------------| ------------ | ------------------ | ---- |
| Laravel API    | PHP 8.3+     | Public API + Admin | 8000 |
| PostgreSQL     | 15+          | Database           | 5432 |
| Redis          | 7+           | Cache              | 6379 |
| Horizon        | Laravel Queue| Async task queue   | 8001 |
| OpenAI API     | External     | AI content gen     | -    |

### Data Flow
```
Client → Laravel API → Redis Cache → PostgreSQL
                              ↓
                         OpenAI API (async job)
                              ↓
                         PostgreSQL → Redis → Client
```

### Laravel Architecture Benefits
- Simplicity — single framework for API and admin
- Development speed — Laravel has everything out-of-the-box
- Async processing — Laravel Horizon for AI tasks
- Scalability — Horizon scale workers independently
- Cost — cheaper infrastructure and maintenance
- Developer experience — easier debugging of single stack

### Public edge evolution (optional future)
If you ever need:
- API Gateway deployment → Place an API Gateway (Kong, Tyk) in front of Laravel (optional)
- High scale (>10k req/min) → Scale Laravel horizontally (Octane, Redis caching)
- Python team → Let them integrate via queue/SDK without owning a separate API

---

## Multilingual Support

### i18n/l10n Strategy

#### General Principles
- Canonical language — en-US as source of truth
- Generation-first — descriptions generated from scratch in target language
- Translate-then-adapt — short summaries translated and adapted
- Glossary system — dictionary of non-translatable terms

#### Supported Languages
1. Polish (pl-PL)
2. English (en-US)
3. German (de-DE)
4. French (fr-FR)
5. Spanish (es-ES)

### Multilingual Data Schema
```sql
-- Main tables
movies(id, source_of_truth_locale, ...)
people(id, source_of_truth_locale, ...)

-- Localization variants
movie_locales(id, movie_id, locale, title_localized, tagline, ...)
person_locales(id, person_id, locale, name_localized, aliases[], ...)

-- Generated/translated content
movie_descriptions(id, movie_id, locale, text, context_tag, origin, ...)
person_bios(id, person_id, locale, text, context_tag, origin, ...)

-- Glossary
glossary_terms(id, term, locale, policy, notes, examples[])
```

---

## Advanced Features

### Style Packs
- Modern — modern, dynamic style
- Critical — critical, analytical
- Journalistic — journalistic, objective
- Playful — light, humorous
- Noir — dark, cinematic
- Scholarly — academic, detailed

### Audience Packs
- Family-friendly
- Cinephile
- Teen
- Casual viewer

### Search Features
- Multilingual embeddings — search in different languages
- Transliteration — phonetic search
- Aliases and pseudonyms — alternative names support
- Fuzzy search — approximate search

### Analytics and Quality
- Quality scoring — content quality assessment
- Plagiarism detection — plagiarism detection
- Hallucination guard — AI hallucination protection
- User feedback — user rating system

---

## Monetization

### Subscription Plans (Local API Keys for Portfolio/Demo)

| Plan       | Limit                 | Price      | Features                   |
| ---------- | --------------------- | ---------- | -------------------------- |
| Free       | 100 requests/month    | $0         | Basic data, cache          |
| Pro        | 10 000 requests/month | Demo         | AI generation, style packs |
| Enterprise | Unlimited             | Demo        | Webhooks, dedicated models |

**Note:** For portfolio/demo, subscriptions are managed locally via API keys in the admin panel. For production, Stripe/PayPal can be integrated. RapidAPI has been removed from the project.

### Billing Model (Portfolio/Demo)
- **Local API keys** — subscription management via admin panel
- **Subscription plans** — free/pro/enterprise plans with limits
- **Rate limiting** — plan-based rate limiting
- **Webhook billing** — prepared for future providers (Stripe, PayPal)

**Note:** For production, Stripe/PayPal can be integrated. RapidAPI has been removed from the project.

### Pricing Strategy
- Competitive pricing
- Value-based pricing
- Freemium model
- Enterprise sales

---

## Git Trunk Flow

### Code Management Strategy
We use **Git Trunk Flow** as the primary code management strategy for MovieMind API with a single always-releasable branch.

### Trunk Flow Advantages
- Single source of truth — we work exclusively on `main`
- Fast iterations — small changes land on `main` the same day
- Continuous quality — run tests and linters before every push
- Feature flags — control feature exposure without branches
- Simple rollback — `git revert` or flag toggle
- Lower integration cost — no long-lived branches

### Trunk Flow Workflow
1. Sync with `main` — `git pull --rebase origin main`
2. Small change set — implement in one or few commits (optionally behind a flag)
3. Local validation — Pint, PHPStan, PHPUnit, GitLeaks, Composer audit
4. Fast review — short PR targeting `main` (no protection blocking merge after approval)
5. Merge/push to `main` — same day, avoid batching changes
6. Observability — monitor deploy; use `revert` or disable flag if needed

### Practices That Support Trunk Flow
- Feature flags to hide incomplete features
- Toggle routing/feature configuration via `.env`/database instead of branches
- Pair or async review with max 2h response time
- Automated CI/CD pipelines on every push to `main`

---

## Feature Flags

### Feature Control Strategy
We use official Laravel Feature Flags integration (`laravel/feature-flags`) instead of custom implementation.

### Official Laravel integration advantages
- Official support
- Simplicity
- Security
- Integration
- Features out-of-the-box
- Maintenance by Laravel team

### Feature Flag Types
1. Boolean flags — enable/disable features
2. Percentage flags — gradual rollout (0-100%)
3. User-based flags — for specific users
4. Environment flags — different settings per environment

### Feature Flags Configuration (example)
```php
<?php
return [
    'ai_description_generation' => true,
    'gpt4_generation' => [
        'enabled' => true,
        'percentage' => 25 // 25% of users
    ],
    'multilingual_support' => [
        'enabled' => true,
        'percentage' => 50 // 50% of users
    ],
    'style_packs' => false // Disabled
];
```

---

## Timeline

### 10-Week Schedule

| Week | Stage                       | Tasks                                      | Deliverables           |
| ---- | --------------------------- | ------------------------------------------ | ---------------------- |
| 1-2  | Foundation                  | Setup, Docker, DB schema, Laravel          | Working environment    |
| 3-4  | AI Integration              | OpenAI, Laravel Horizon, Quality scoring   | Description generation |
| 5-6  | Multilingual                | i18n, Translation, Glossary                | 5+ languages           |
| 7-8  | Observability & Integrations| Webhook simulator, Monitoring              | Ops toolkit            |
| 9-10 | Monetization & Adv. Features| Plans, Style packs, Analytics              | Commercial readiness   |

### Milestones
- Week 2 — Laravel MVP Public repo ready
- Week 4 — AI integration working (Laravel + OpenAI)
- Week 6 — Multilingual implemented
- Week 8 — Observability toolkit solidified
- Week 10 — Commercial handoff package (optional: expose Laravel via API Gateway)

---

## Summary

MovieMind API is an ambitious project combining best practices of Laravel architecture with advanced AI capabilities. Through the dual-repository strategy, we build both a portfolio and a commercial product. The Laravel-only architecture simplifies the MVP and can evolve to hybrid when needed.

---

## Architecture Evolution

### Evolutionary Strategy

Phase 1 (MVP): Everything in Laravel — Current
- Single framework = faster development
- Simpler maintenance and debugging
- Cheaper infrastructure
- Laravel Horizon for async jobs

Phase 2 (optional, if needed): Harden the public edge
- API Gateway deployment → Add an API Gateway (Kong/Tyk) in front of Laravel (optional)
- High scale (>10k req/min) → Scale Laravel horizontally (Octane, cache, read replicas)
- Python team → Integrate via queues (RabbitMQ) or SDK instead of a separate API

When to split?
- Publishing API via API Gateway (optional)
- >10k requests/minute
- Need advanced Python AI pipelines
- Have separate Python team

---

Document created: 2025-01-27



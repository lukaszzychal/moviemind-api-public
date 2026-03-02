# 🎬 MovieMind API

**AI-powered Film & Series Metadata API**

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.3-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-ff2d20.svg)](https://laravel.com)
[![CI](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/ci.yml)
[![CodeQL](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/codeql.yml)
[![Code Security Scan](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/code-security-scan.yml/badge.svg?branch=main)](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/code-security-scan.yml)
[![Docker Security Scan](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/docker-security-scan.yml/badge.svg?branch=main)](https://github.com/lukaszzychal/moviemind-api-public/actions/workflows/docker-security-scan.yml)

> 🇵🇱 **Wersja polska:** [`README.pl.md`](README.pl.md)

## 🎯 Project Overview

MovieMind API is a **portfolio/demo project** that demonstrates a RESTful service for generating and storing unique descriptions for movies, series, and actors using AI technology. Unlike traditional movie databases that copy content from IMDb or TMDb, MovieMind creates original, AI-generated content with support for multiple languages and contextual styling.

**Note:** This is a portfolio project with full functionality for demonstration purposes. For production deployment, commercial licenses may be required (see [Third-Party API Licenses](#-third-party-api-licenses) below).

## ✨ Key Features

- 🤖 **AI-Generated Content**: Creates unique descriptions using OpenAI/LLM APIs
- 🌍 **Multi-language Support**: Content generation in multiple locales
- 🎨 **Contextual Styling**: Different description styles (modern, critical, humorous)
- ⚡ **Smart Caching**: Redis-based caching to avoid redundant AI calls
- 🔄 **Async Processing**: Background job processing for AI generation
- 📊 **RESTful API**: Clean, well-documented REST endpoints

## 🏗️ Architecture

### Technology Stack

| Component | Technology | Purpose |
|-----------|------------|---------|
| **Backend** | Laravel 12 (PHP 8.3) | API (public demo) |
| **Database** | PostgreSQL | Data persistence |
| **Cache** | Redis | Performance optimization |
| **AI Integration** | OpenAI API | Content generation |
| **Queue System** | Laravel Horizon + Queues | Async processing |
| **Documentation** | OpenAPI/Swagger | API documentation |

### Database Schema

#### Core Tables

**Movies**
```sql
movies
├── id (PK)
├── title
├── release_year
├── director
├── genres (array)
└── default_description_id (FK)
```

**Movie Descriptions**
```sql
movie_descriptions
├── id (PK)
├── movie_id (FK)
├── locale (pl-PL, en-US)
├── text
├── context_tag (modern, critical, humorous)
├── origin (GENERATED/TRANSLATED)
├── ai_model (gpt-4o-mini)
└── created_at
```

**People & Bios**
```sql
people
├── id (PK, UUIDv7)
├── name
├── slug
├── birth_date
├── birthplace
├── tmdb_id
└── default_bio_id (FK, UUIDv7)

person_bios
├── id (PK, UUIDv7)
├── person_id (FK, UUIDv7)
├── locale
├── text
├── context_tag
├── origin
├── ai_model
└── created_at
```

**Jobs (Async Processing)**
```sql
jobs
├── id (PK)
├── entity_type (MOVIE, PERSON)
├── entity_id
├── locale
├── status (PENDING, DONE, FAILED)
├── payload_json
└── created_at
```

## 🚀 API Endpoints

### Core Endpoints

| Method | Endpoint              | Description                                                  |
| ------ | --------------------- | ------------------------------------------------------------ |
| `GET`  | `/v1/movies?q=`       | Search movies by title, year, genre                          |
| `GET`  | `/v1/movies/{slug}`   | Get movie details + AI description (queues generation if missing) |
| `GET`  | `/v1/people/{slug}`   | Get person (actor, director, etc.) with bios and roles       |
| `POST` | `/v1/generate`        | Trigger new AI generation                                     |
| `GET`  | `/v1/jobs/{id}`       | Check generation job status                                   |

### Example Usage

```bash
# Search for movies
curl -H "X-API-Key: <REPLACE_ME>" \
     "https://api.moviemind.com/v1/movies?q=matrix"

# Get movie details
curl -H "X-API-Key: <REPLACE_ME>" \
     "https://api.moviemind.com/v1/movies/the-matrix"

# Trigger AI generation
curl -X POST \
     -H "X-API-Key: <REPLACE_ME>" \
     -H "Content-Type: application/json" \
     -d '{"entity_type": "MOVIE", "entity_id": 123, "locale": "pl-PL", "context_tag": "modern"}' \
     "https://api.moviemind.com/v1/generate"
```

## 🔄 Workflow

### Happy Path Flow

1. **Client Request**: `GET /v1/movies/the-matrix`
2. **Database Check**: System checks for existing description
3. **AI Generation** (if needed):
   - Creates job record with `PENDING` status
   - Dispatches a Laravel queue worker (Horizon container)
   - Worker calls OpenAI API with contextual prompt
   - Saves result to database and updates job status
4. **Response**: Returns movie data with AI-generated description
5. **Caching**: Subsequent requests served from cache

### AI Prompt Example

```
Napisz zwięzły, unikalny opis filmu {title} z roku {year}.
Styl: {context_tag}.
Długość: 2–3 zdania, naturalny język, bez spoilera.
Język: {locale}.
Zwróć tylko czysty tekst.
```

## 🐳 Quick Start

> **⚠️ IMPORTANT: Always use Docker for local development!**  
> The application requires PostgreSQL and Redis. Using `php artisan serve` locally may cause inconsistencies between local and production environments. **Always use Docker Compose.**

### Prerequisites

- **Docker & Docker Compose** (REQUIRED)
- OpenAI API Key (optional, can use mock mode)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/lukaszzychal/moviemind-api-public.git
   cd moviemind-api-public
   ```

2. **Environment Setup**
   ```bash
   # Copy template into the Laravel app directory
   cp env/local.env.example api/.env
   # Edit api/.env and add your OpenAI API key
   ```

3. **Start Services (Docker)**
   ```bash
   docker compose up -d --build
   ```

4. **Install backend dependencies**
   ```bash
   docker compose exec php composer install
   ```

5. **Generate application key**
   ```bash
   docker compose exec php php artisan key:generate
   ```

6. **Run database migrations & seed demo data**
   ```bash
   docker compose exec php php artisan migrate --seed
   ```

7. **Follow Horizon logs (queues run in dedicated container)**
   ```bash
   docker compose logs -f horizon
   ```

### 🚀 Automatyczne przygotowanie środowiska

Możesz użyć skryptu `setup-local-testing.sh`, który automatycznie przygotuje środowisko:

```bash
# Podstawowe użycie (tryb mock)
./scripts/setup-local-testing.sh

# Z trybem real (OpenAI)
./scripts/setup-local-testing.sh --ai-service real

# Z rebuild kontenerów
./scripts/setup-local-testing.sh --rebuild
```

**Szczegółowe instrukcje:** [`scripts/README.md`](scripts/README.md)

### Docker Compose Configuration

See docker-compose.yml in repo for full configuration (PHP-FPM, Nginx, Postgres, Redis, Horizon).

## 📋 Feature Overview

| Area | Public Demo (this repo) | Commercial Edition (private) |
|------|-------------------------|-------------------------------|
| API | REST endpoints for movies, people, async jobs | Extended SLA tooling, partner integrations |
| AI generation | `AI_SERVICE=mock` (deterministic showcase) and `AI_SERVICE=real` using OpenAI | Multi-provider routing, cost controls, hallucination guardrails |
| Admin experience | Admin UI with feature flags, content CRUD, demo accounts | Full operations console with billing, analytics, audit trails |
| Authentication | Demo auth for admin + open public API for easy testing | API keys per plan, OAuth/JWT, rate limiting by tier |
| Webhooks | Simulator endpoints + request inspector for demos | Production billing/webhook processors (Stripe, PayPal, partner events) |
| Monitoring | Telescope dashboards, sample Grafana dashboards | Advanced metrics, SLA monitors, on-call alerting |
| Localization | Sample multilingual content + glossary showcase | Full translation pipeline, locale-specific prompts |
| Documentation | OpenAPI spec, architecture notes, portfolio walkthrough | Commercial runbooks, deployment playbooks, vendor docs |

> 💡 The public repository focuses on demonstrating implementation skills without exposing proprietary integrations. The private repository contains production credentials, billing, compliance, and partner contracts.

## 🔐 Authentication & Access

- **Public demo:** API endpoints remain open to simplify local testing and workshops. The Admin UI uses Laravel authentication with demo users (credentials in `.env.demo`). This keeps the focus on showcasing feature flags, CRUD, and queue monitoring without distributing secrets.
- **Commercial edition:** Adds per-customer API keys, OAuth/JWT support, subscription-aware rate limits, billing hooks, and detailed audit trails. These live in the private repository alongside the payment/webhook integrations.

When you need to preview authenticated flows locally, set up the demo users and sign in to the Admin UI. For production integrations, request access to the private repository.

## 📚 Documentation

- **API Documentation**: Available at `/api/doc` when running locally
- **OpenAPI Spec**: `docs/openapi.yaml`
- **Architecture Diagrams**: `docs/c4/` (C4 model diagrams)
- **GitHub Projects Setup**: [`docs/GITHUB_PROJECTS_SETUP.md`](docs/GITHUB_PROJECTS_SETUP.md) - Przewodnik zarządzania zadaniami
- **Portfolio Recommendations**: [`docs/PUBLIC_REPO_PORTFOLIO_RECOMMENDATIONS.md`](docs/PUBLIC_REPO_PORTFOLIO_RECOMMENDATIONS.md) - Funkcje do dodania dla portfolio
- **MCP File System Server**: [`docs/en/MCP_FILESYSTEM_SERVER_SETUP.md`](docs/en/MCP_FILESYSTEM_SERVER_SETUP.md) - Konfiguracja MCP File System Server dla Cursor/Claude Desktop

## 🧪 Testing

### PHPUnit Tests

```bash
# Run the full test suite
docker compose exec php php artisan test

# Run only feature tests
docker compose exec php php artisan test --testsuite=feature
```

### Postman/Newman API Tests

The project includes Postman collections for API testing:

- **Collection**: `docs/postman/moviemind-api.postman_collection.json`
- **Environment**: `docs/postman/environments/local.postman_environment.json`

#### Running tests locally with Newman

```bash
# Install Newman
npm install -g newman

# Run tests
newman run docs/postman/moviemind-api.postman_collection.json \
  -e docs/postman/environments/local.postman_environment.json \
  --env-var "baseUrl=http://localhost:8000"
```

#### CI Integration

Newman tests run automatically in CI on every push to `main` or `develop` branches. Test results are published as JUnit XML reports.

## 🤖 AI Service Modes

The application can switch between deterministic demo data and real OpenAI calls:

- Set `AI_SERVICE=mock` (default) for predictable showcase data generated by `MockGenerateMovieJob` / `MockGeneratePersonJob`.
- Set `AI_SERVICE=real` together with `OPENAI_API_KEY`, `OPENAI_MODEL`, and optional `OPENAI_URL` to enable the `RealGenerate*Job` classes. The jobs resolve `OpenAiClientInterface` and hit the real API.

After changing the environment variables, run `php artisan config:clear` (or restart the container) so the selector picks up the new mode.

## 📈 Performance Considerations

- **Caching Strategy**: Redis cache for frequently accessed content
- **Async Processing**: AI generation doesn't block API responses
- **Database Optimization**: Proper indexing on search fields
- **Rate Limiting**: Built-in protection against abuse

## 🤝 Contributing

This is a **portfolio/demo repository** showcasing full API functionality with local API key management. All features are available for demonstration, including subscription plans, rate limiting, and webhook systems. For production deployment, billing providers (Stripe, PayPal) can be integrated.

### Development Workflow (Trunk-Based)

1. **Sync `main` frequently** – keep the trunk clean, releasable, and up to date.
2. **Use short-lived topic branches (optional)** – create a branch only if needed, keep it alive for hours rather than days, or pair/mob directly on `main`.
3. **Ship in small slices** – break features into incremental, production-safe changes guarded by feature flags or runtime config.
4. **Run the full test/CI suite** – both locally and in the pipeline; merges happen only when CI is green.
5. **Merge back to `main` immediately** – integrate without long-lived PRs; rely on lightweight reviews (pair review) or auto-merge once CI passes.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## ⚠️ Third-Party API Licenses

### TMDB (The Movie Database)

**Portfolio/Demo Use:**
- ✅ Non-commercial use allowed (with attribution)
- Attribution required: TMDB logo + text + link

**Production Use:**
- ❌ **Commercial license REQUIRED**
- Contact: sales@themoviedb.org
- Estimated costs: ~$149/month (small apps) to $42,000/year (enterprise)
- See: [`docs/LEGAL_TMDB_LICENSE.md`](docs/LEGAL_TMDB_LICENSE.md) for full details

### TVmaze

**Portfolio & Production Use:**
- ✅ Commercial use allowed (free, CC BY-SA license)
- Attribution required: Link to TVmaze
- See: [`docs/LEGAL_TVMAZE_LICENSE.md`](docs/LEGAL_TVMAZE_LICENSE.md) for full details

## 🔗 Related Projects

- **Private Repository**: Full commercial version with billing, webhooks, and admin panel
- **API Gateway** (optional): Production API deployment through API Gateway (Kong, Tyk, etc.)
- **Documentation Site**: Comprehensive API documentation

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/lukaszzychal/moviemind-api-public/issues)
- **Discussions**: [GitHub Discussions](https://github.com/lukaszzychal/moviemind-api-public/discussions) *(enable in repository Settings → Features)*
- **Email**: lukasz.zychal.dev@gmail.com

## 🏆 Roadmap

- [ ] Admin panel for content management
- [ ] Webhook system for real-time notifications
- [ ] Advanced analytics and metrics
- [ ] Multi-tenant support
- [ ] Content versioning and A/B testing
- [ ] Integration with popular movie databases

---

**Built with ❤️ by [Łukasz Zychal](https://github.com/lukaszzychal)**

*This is the public demonstration version. For production features, contact us for access to the private repository.*

# 🎬 MovieMind API

**AI-powered Film & Series Metadata API**

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-8.3-blue.svg)](https://php.net)
[![Symfony](https://img.shields.io/badge/Symfony-7.0-green.svg)](https://symfony.com)

## 🎯 Project Overview

MovieMind API is a RESTful service that generates and stores unique descriptions for movies, series, and actors using AI technology. Unlike traditional movie databases that copy content from IMDb or TMDb, MovieMind creates original, AI-generated content with support for multiple languages and contextual styling.

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
| **Backend** | Symfony 7 (PHP 8.3) | API framework |
| **Database** | PostgreSQL | Data persistence |
| **Cache** | Redis | Performance optimization |
| **AI Integration** | OpenAI API | Content generation |
| **Queue System** | Symfony Messenger | Async processing |
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

**Actors & Bios**
```sql
actors
├── id (PK)
├── name
├── birth_date
├── birthplace
└── default_bio_id (FK)

actor_bios
├── id (PK)
├── actor_id (FK)
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
├── entity_type (MOVIE, ACTOR)
├── entity_id
├── locale
├── status (PENDING, DONE, FAILED)
├── payload_json
└── created_at
```

## 🚀 API Endpoints

### Core Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/v1/movies?q=` | Search movies by title, year, genre |
| `GET` | `/v1/movies/{id}` | Get movie details + AI description |
| `GET` | `/v1/actors/{id}` | Get actor information + biography |
| `POST` | `/v1/generate` | Trigger new AI generation |
| `GET` | `/v1/jobs/{id}` | Check generation job status |

### Example Usage

```bash
# Search for movies
curl -H "X-API-Key: <REPLACE_ME>" \
     "https://api.moviemind.com/v1/movies?q=matrix"

# Get movie details
curl -H "X-API-Key: <REPLACE_ME>" \
     "https://api.moviemind.com/v1/movies/123"

# Trigger AI generation
curl -X POST \
     -H "X-API-Key: <REPLACE_ME>" \
     -H "Content-Type: application/json" \
     -d '{"entity_type": "MOVIE", "entity_id": 123, "locale": "pl-PL", "context_tag": "modern"}' \
     "https://api.moviemind.com/v1/generate"
```

## 🔄 Workflow

### Happy Path Flow

1. **Client Request**: `GET /v1/movies/123`
2. **Database Check**: System checks for existing description
3. **AI Generation** (if needed):
   - Creates job record with `PENDING` status
   - Triggers async worker via Symfony Messenger
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

### Prerequisites

- Docker & Docker Compose
- OpenAI API Key

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/lukaszzychal/moviemind-api-public.git
   cd moviemind-api-public
   ```

2. **Environment Setup**
   ```bash
   # Choose template from env/ and copy as .env
   cp env/local.env.example .env
   # Edit .env and add your OpenAI API key
   ```

3. **Start Services (Docker)**
   ```bash
   docker-compose up -d --build
   ```

4. **Initialize Laravel (inside php container, runs as non-root user)**
   ```bash
   docker-compose exec php bash -lc "composer create-project laravel/laravel . || true"
   docker-compose exec php bash -lc "cp -n .env.example .env || true && php artisan key:generate"
   docker-compose exec php php artisan migrate
   ```

5. **Start Horizon (queues)**
   ```bash
   docker-compose logs -f horizon
   ```

### Docker Compose Configuration

See docker-compose.yml in repo for full configuration (PHP-FPM, Nginx, Postgres, Redis, Horizon).

## 📋 MVP Scope

### ✅ Included Features

- Core REST API endpoints
- AI-powered content generation
- PostgreSQL data persistence
- Redis caching layer
- Async job processing
- Multi-language support
- Contextual styling options
- OpenAPI documentation

### ❌ Excluded from MVP

- Admin UI panel
- Webhook system
- Billing/subscription management
- Multi-user authentication
- Advanced monitoring/metrics
- Content version comparison
- Automatic translations

## 🔐 Authentication

The API uses simple API key authentication:

```bash
curl -H "X-API-Key: <REPLACE_ME>" \
     "https://api.moviemind.com/v1/movies"
```

## 📚 Documentation

- **API Documentation**: Available at `/api/doc` when running locally
- **OpenAPI Spec**: `docs/openapi.yaml`
- **Architecture Diagrams**: `docs/c4/` (C4 model diagrams)

## 🧪 Testing

```bash
# Run unit tests
docker-compose exec api php bin/phpunit

# Run integration tests
docker-compose exec api php bin/phpunit --testsuite=integration
```

## 📈 Performance Considerations

- **Caching Strategy**: Redis cache for frequently accessed content
- **Async Processing**: AI generation doesn't block API responses
- **Database Optimization**: Proper indexing on search fields
- **Rate Limiting**: Built-in protection against abuse

## 🤝 Contributing

This is a public demonstration repository. For commercial features and full functionality, see the private repository.

### Development Setup

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests
5. Submit a pull request

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🔗 Related Projects

- **Private Repository**: Full commercial version with billing, webhooks, and admin panel
- **RapidAPI Marketplace**: Production API deployment
- **Documentation Site**: Comprehensive API documentation

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/lukaszzychal/moviemind-api-public/issues)
- **Discussions**: [GitHub Discussions](https://github.com/lukaszzychal/moviemind-api-public/discussions)
- **Email**: support@moviemind.com

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

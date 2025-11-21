# MovieMind API - Project Context

> **This file contains context about the MovieMind API project for the AI assistant.**
> 
> It is automatically loaded by Cursor IDE when the "Include CLAUDE.md in context" option is enabled in settings.

---

## 🎯 Project Overview

MovieMind API is a RESTful API for generating and storing unique descriptions of movies, series, and actors using AI technology. The project creates original, AI-generated content instead of copying content from IMDb or TMDb.

---

## 🏗️ Technology Stack

### Backend
- **Framework:** Laravel 12
- **PHP:** 8.2+
- **Database:** PostgreSQL (production), SQLite (tests)
- **Cache:** Redis
- **Queue:** Laravel Horizon (asynchronous processing)
- **AI Integration:** OpenAI API (gpt-4o-mini)

### Development Tools
- **Tests:** PHPUnit (Feature Tests + Unit Tests)
- **Formatting:** Laravel Pint (PSR-12)
- **Static analysis:** PHPStan (level 5)
- **Security:** GitLeaks (secret detection)
- **Documentation:** OpenAPI/Swagger

---

## 📁 Project Structure

### Main Structure
```
api/                          # Laravel application
├── app/
│   ├── Enums/               # Enumerations (Language, EntityType, etc.)
│   ├── Events/              # Laravel Events
│   ├── Features/            # Feature-based code
│   ├── Helpers/             # Helper functions
│   ├── Http/
│   │   ├── Controllers/     # API Controllers
│   │   ├── Requests/        # Request validators
│   │   └── Resources/        # API Resources
│   ├── Jobs/                # Queue Jobs (ShouldQueue)
│   ├── Listeners/           # Event Listeners
│   ├── Models/              # Eloquent Models
│   ├── Repositories/        # Repository pattern
│   └── Services/            # Business logic services
├── config/                  # Laravel configuration
├── database/
│   ├── migrations/          # Database migrations
│   └── seeders/             # Seeders
├── routes/
│   └── api.php              # Route definitions
└── tests/
    ├── Feature/             # Feature tests (API endpoints)
    └── Unit/                # Unit tests (classes, services)
```

---

## 🗄️ Data Model

### Main Tables

**Movies**
- `id` (PK)
- `title`
- `release_year`
- `director`
- `genres` (array)
- `default_description_id` (FK)

**Movie Descriptions**
- `id` (PK)
- `movie_id` (FK)
- `locale` (pl-PL, en-US, etc.)
- `text` (AI-generated content)
- `context_tag` (modern, critical, humorous)
- `origin` (GENERATED/TRANSLATED)
- `ai_model` (gpt-4o-mini)
- `created_at`

**Actors & Bios**
- Similar structure to Movies/Descriptions
- `actors` - basic actor data
- `actor_bios` - AI-generated biographies

**Jobs (Async Processing)**
- `id` (PK)
- `entity_type` (MOVIE, ACTOR)
- `entity_id`
- `locale`
- `status` (PENDING, DONE, FAILED)
- `payload_json`
- `created_at`

---

## 🔄 Architecture and Flow

### Current Flow (Laravel Events + Jobs)
```
Controller
  ↓
Event (e.g. MovieGenerationRequested)
  ↓
Listener (QueueMovieGenerationJob)
  ↓
Job (GenerateMovieJob implements ShouldQueue)
  ↓
Queue Worker (Laravel Horizon)
  ↓
AI Service (OpenAI API)
  ↓
Database (save result)
```

### Design Patterns
- **Repository Pattern** - data access abstraction
- **Service Layer** - business logic
- **Event-Driven** - Events + Listeners for asynchronous operations
- **Queue Jobs** - long-running operations (AI generation)

---

## 🧪 Tests

### Test Types

1. **Feature Tests** (`tests/Feature/`)
   - Test API endpoints
   - Use test database (SQLite `:memory:`)
   - Example: `MovieControllerTest`, `GenerateApiTest`

2. **Unit Tests** (`tests/Unit/`)
   - Test individual classes and methods
   - Fast, isolated
   - Example: `MovieServiceTest`, `ValidationHelperTest`

### TDD Workflow
- **RED** - Write a test that defines the requirement
- **GREEN** - Write minimal code to pass the test
- **REFACTOR** - Improve code while keeping tests passing

**IMPORTANT:** Always write tests before implementation!

---

## 📝 Naming Conventions

### Classes
- **Controllers:** `MovieController`, `PersonController` (suffix: Controller)
- **Models:** `Movie`, `MovieDescription`, `Actor` (PascalCase, singular)
- **Services:** `MovieService`, `AiService` (suffix: Service)
- **Jobs:** `GenerateMovieJob`, `GenerateActorBioJob` (suffix: Job)
- **Events:** `MovieGenerationRequested` (verb in past tense)
- **Listeners:** `QueueMovieGenerationJob` (action + object)
- **Requests:** `StoreMovieRequest`, `UpdateMovieRequest` (action + object + Request)
- **Resources:** `MovieResource`, `ActorResource` (object + Resource)

### Methods
- **Controllers:** `index()`, `show()`, `store()`, `update()`, `destroy()` (standard REST)
- **Services:** `create()`, `find()`, `update()`, `delete()`, `generate()` (business actions)
- **Tests:** `test_can_create_movie()` (snake_case, prefix: test_)

### Files
- **Migrations:** `2024_01_01_000000_create_movies_table.php` (timestamp_description)
- **Seeders:** `MovieSeeder`, `ActorSeeder` (object + Seeder)

---

## 🔧 Pre-Commit Workflow

Before each commit you MUST run:

1. **Laravel Pint** - formatting
   ```bash
   cd api && vendor/bin/pint
   ```

2. **PHPStan** - static analysis
   ```bash
   cd api && vendor/bin/phpstan analyse --memory-limit=2G
   ```

3. **PHPUnit** - tests
   ```bash
   cd api && php artisan test
   ```

4. **GitLeaks** - secret detection
   ```bash
   gitleaks protect --source . --verbose --no-banner
   ```

5. **Composer Audit** - security audit
   ```bash
   cd api && composer audit
   ```

---

## 🎯 Coding Principles

### SOLID (apply pragmatically)
- **SRP** - One class = one responsibility
- **DIP** - Depend on abstractions (interfaces)

### DRY
- Refactor duplication when it occurs in 3+ places
- Don't overdo abstraction

### Type Safety
- Always use `declare(strict_types=1);` in PHP files
- Always specify type hints for parameters and return types
- Use types instead of `mixed` where possible

### Laravel Conventions
- Use Eloquent Models instead of Query Builder when possible
- Use Form Requests for validation
- Use API Resources for responses
- Use Events + Jobs for asynchronous operations

---

## 📚 Key Documentation Files

- **AI Rules:** `.cursor/rules/*.mdc` (rules in new format) + `docs/AI_AGENT_CONTEXT_RULES.md` (details)
- **Tasks:** `docs/issue/TASKS.md` - ⭐ START HERE
- **Tests:** `docs/TESTING_STRATEGY.md`
- **Tools:** `docs/CODE_QUALITY_TOOLS.md`
- **Architecture:** `docs/ARCHITECTURE_ANALYSIS.md`
- **Cursor explanation:** `docs/CURSOR_RULES_EXPLANATION.md`

---

## 🚀 API Endpoints

### Main Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/movies` | List of movies (with pagination, filtering) |
| `GET` | `/api/v1/movies/{id}` | Movie details + AI description |
| `POST` | `/api/v1/generate` | Trigger AI generation |
| `GET` | `/api/v1/jobs/{id}` | Generation job status |

### Examples

```bash
# Get movie
GET /api/v1/movies/123

# Trigger generation
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "entity_id": 123,
  "locale": "pl-PL",
  "context_tag": "modern"
}
```

---

## 🔐 Security

### Before Commit
- ✅ Check GitLeaks (zero secrets)
- ✅ Check Composer Audit (critical vulnerabilities)
- ✅ Use environment variables for API keys
- ✅ Never commit `.env` with real values

### Secrets
- OpenAI API keys: `OPENAI_API_KEY` (environment variable)
- Database passwords: in `.env` (not in repo)
- All secrets: in `.env` or environment variables

---

## 💡 Important Notes

1. **TDD** - Test before code, always
2. **Tools** - Pint, PHPStan, tests before commit
3. **Readability** - Code must be understandable to others
4. **Pragmatism** - Principles are tools, not goals in themselves
5. **Tasks** - Always start from `docs/issue/TASKS.md`

---

**This file is updated as the project evolves. Check `docs/` for detailed information.**


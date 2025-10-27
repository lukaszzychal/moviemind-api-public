# MovieMind API - Kompletna Specyfikacja i Plan Działania / Complete Specification and Action Plan

> **📝 Note / Uwaga**: This document combines the original ChatGPT thread with comprehensive specifications and action plans for the MovieMind API project.  
> **📝 Uwaga**: Ten dokument łączy oryginalny wątek ChatGPT z kompleksowymi specyfikacjami i planami działania dla projektu MovieMind API.

## 🎬 Przegląd Projektu / Project Overview

**MovieMind API** to AI-powered Film & Series Metadata API, które generuje i przechowuje unikalne opisy filmów, seriali i aktorów wykorzystując sztuczną inteligencję, cache i automatyczny wybór najlepszych wersji treści.

**MovieMind API** is an AI-powered Film & Series Metadata API that generates and stores unique descriptions for movies, series, and actors using artificial intelligence, caching, and automatic selection of the best content versions.

### 🎯 Główne Cele / Main Goals

**Cel projektu / Project Goal:**
Udostępnić API, które:
- generuje i przechowuje unikalne opisy filmów, seriali i aktorów
- wykorzystuje AI (np. ChatGPT / LLM API) do tworzenia treści
- dba o unikalność (żadnego kopiowania z IMDb, TMDb itp.)
- umożliwia cache, wielojęzyczność i tagowanie stylu opisu
- pozwala klientom pobierać dane przez REST API

**Provide an API that:**
- generates and stores unique descriptions for movies, series, and actors
- uses AI (e.g., ChatGPT / LLM API) for content creation
- ensures uniqueness (no copying from IMDb, TMDb, etc.)
- enables cache, multilingualism, and style tagging
- allows clients to retrieve data through REST API

### 💡 Rodzaj Produktu / Product Type

**MVP (Minimum Viable Product)** – pierwsza działająca wersja z minimalnym zakresem funkcji, możliwa do wystawienia na RapidAPI.

**MVP (Minimum Viable Product)** – first working version with minimal feature scope, ready for deployment on RapidAPI.

**Nie PoC, bo:**
- PoC = tylko dowód, że da się generować tekst AI (bez systemu, bazy, API)
- MVP = ma już prawdziwe API, cache, minimalny storage i klucz API

**Not PoC, because:**
- PoC = only proof that AI text generation is possible (without system, database, API)
- MVP = already has real API, cache, minimal storage, and API key

---

## 🏗️ Strategia Dual-Repository / Dual-Repository Strategy

### 🧩 Podejście Dual-Repository / Dual-Repository Approach

| Aspekt / Aspect               | Repozytorium Publiczne / Public Repository                                 | Repozytorium Prywatne / Private Repository                                      |
| -----------------             | ---------------------------------------------                              | ---------------------------------------------                                   |
| **Cel / Goal**                | Portfolio, demonstracja umiejętności / Portfolio, skills demonstration     | Produkcja, komercyjny produkt / Production, commercial product                  |
| **Zawartość / Content**       | Okrojony kod, mock AI, dokumentacja / Trimmed code, mock AI, documentation | Pełny kod, realne AI, billing, webhooki / Full code, real AI, billing, webhooks |
| **Bezpieczeństwo / Security** | Brak kluczy API, przykładowe dane / No API keys, sample data               | Prawdziwe klucze, dane produkcyjne / Real keys, production data                 |
| **Licencja / License**        | MIT / CC-BY-NC                                                             | Własna komercyjna / Custom commercial                                           |
| **Timeline / Harmonogram**    | 6 tygodni (MVP)                                                            | 8-12 tygodni (pełny produkt)                                                    |

### ✅ Dlaczego to dobre rozwiązanie / Why This Is a Good Solution

**🔹 1. Wizerunkowo / Image**
Publiczne repo pokazuje:
- Twoje podejście do architektury (DDD, CQRS, C4)
- strukturę projektu (Docker, README, tests, configi)
- czysty kod (Symfony / FastAPI / Python / SQL / YAML)
- znajomość dobrych praktyk: separacja domen, ENV, clean prompt design

**🔹 2. Bezpieczniej i bardziej elastycznie / More Secure and Flexible**
Prywatne repo może zawierać:
- klucze API (OpenAI, RapidAPI, SMTP, RabbitMQ) — w .env lub Vault
- pełny workflow (CI/CD, webhooki)
- analizy wydajności, monitoringi, testy integracyjne
- AI logic (prompt templates, selection heuristics) — Twoja "tajemnica handlowa"

### 📁 Podział w Praktyce / Practical Division

**Repo publiczne (`moviemind-api-public`):**
```
├── README.md (z opisem projektu, architektury)
├── src/ (tylko podstawowe endpointy)
├── docker-compose.yml
├── docs/ (C4, openapi.yaml)
├── .env.example (bez prawdziwych kluczy)
├── .gitignore
├── .gitleaks.toml
└── LICENSE (MIT lub CC BY-NC)
```

**Repo prywatne (`moviemind-api-private`):**
```
├── all public files
├── .env.production
├── src/AI/ (prompt templates, heurystyka)
├── src/Webhooks/
├── src/Billing/
├── src/Admin/
├── tests/integration/
└── LICENSE (custom commercial)
```

---

## 🧩 Zakres MVP / MVP Scope

### 🔹 1. Zakres Funkcjonalny / Functional Scope

**Użytkownik (klient API) może:**
| Funkcja               | Opis                                                |
| ---------             | ------                                              |
| `GET /v1/movies?q=`   | wyszukać filmy (tytuł, rok, gatunek)                |
| `GET /v1/movies/{id}` | pobrać szczegóły filmu + opis (AI lub cache)        |
| `GET /v1/actors/{id}` | pobrać dane aktora + biografię                      |
| `POST /v1/generate`   | wymusić nowe wygenerowanie opisu lub biografii (AI) |
| `GET /v1/jobs/{id}`   | sprawdzić status generacji (PENDING, DONE, FAILED)  |

**System (wewnętrznie):**
- zapisuje dane w PostgreSQL (movies, actors, descriptions, bios, jobs)
- jeśli danych nie ma → generuje przez AI (np. OpenAI API)
- wynik trzyma w DB i cache (Redis)
- przy kolejnym zapytaniu używa cache (nie pyta AI)
- każde wygenerowanie zapisuje z kontekstem (modern, critical, humorous, …)

### 🔹 2. Technologie MVP / MVP Technologies

#### 🏗️ Architektura Laravel / Laravel Architecture

**Jeden backend dla API i Admin Panel:**

| Kontener         | Technologia             | Odpowiedzialność                                            |
| ----------       | -------------           | ------------------                                          |
| **Laravel API**  | PHP 8.3 + Laravel 11    | Publikuje REST endpointy (filmy, aktorzy, AI generacja)    |
| **Admin Panel**  | Laravel (Nova/Breeze)   | Zarządzanie danymi, modelami AI, monitoring                |
| **AI Service**   | OpenAI SDK (PHP)        | Generuje opisy, biografie, tagi kontekstowe w Laravel Jobs  |
| **Database**     | PostgreSQL              | Przechowuje treści, metadane, wersje, tagi, ratingi jakości |
| **Cache**        | Redis                   | Cache odpowiedzi API i AI wyników                           |
| **Task Queue**   | Laravel Horizon         | Kolejkuje generowanie opisów, async AI processing          |
| **Metadata**      | Laravel Console Commands| Pobiera dane z TMDB/TVMaze, normalizuje, uzupełnia braki    |

#### ⚡ /src-fastapi/ — lekki, publiczny, skalowalny API Core

**Technologia:** Python + FastAPI + Celery + RabbitMQ + Redis  
**Cel:** API-as-a-Service (publiczne endpointy, AI generacja, async jobs)

| Cecha                  | Opis                                                              |
| -------                | ------                                                            |
| **Język**              | Python — prosty, szybki dla ML/AI, łatwy deploy na RapidAPI       |
| **Async**              | obsługuje tysiące requestów, idealny do generacji treści przez AI |
| **Worker (Celery)**    | obsługa kolejek, webhooków, generacji asynchronicznej             |
| **Redis + Prometheus** | cache, rate limiting, metryki                                     |
| **AI Integration**     | to tu trafia request z RapidAPI, generuje opis i zapisuje w bazie |
| **Deployment**         | kontener publiczny (np. RapidAPI, AWS Lambda, Railway, etc.)      |

**📌 Rola:** To zewnętrzna warstwa API-as-a-Service, zorientowana na klientów zewnętrznych i integracje.

#### 🧱 /src-laravel/ — domenowy backend / admin / integracje wewnętrzne

**Technologia:** PHP 8.3 + Laravel 11 + Eloquent + Queue  
**Cel:** wewnętrzny backend domenowy i panel zarządzania danymi (CMS / DDD)

| Cecha                           | Opis                                                 |
| -------                         | ------                                               |
| **DDD / CQRS / Eloquent** | model domenowy: Movie, Actor, AIJob itp.             |
| **Queue (RabbitMQ)**       | integracja event-driven z FastAPI workerem           |
| **Laravel Nova (REST/GraphQL)** | dokumentacja, CRUD-y, back-office                    |
| **Security**                    | admin roles, JWT, OAuth                              |
| **CLI / Cron / Importy**        | zarządzanie danymi zewnętrznymi (IMDb, TMDb, TVMaze) |
| **Deployment**                  | serwis wewnętrzny (np. admin.moviemind.dev)          |

**📌 Rola:** To wewnętrzny CMS / Control Plane, który:
- zarządza bazą filmów, aktorów, opisów, tagów, języków
- weryfikuje wygenerowane dane
- wysyła zadania do FastAPI (AI generacja, webhook, itp.)
- obsługuje multi-language, moderation, curation

#### 🧩 Jak to się łączy (C4 poziom "Container")

```
+--------------------------------------------+
| Public Internet                               |
| --------------------------------------------- |
| [ RapidAPI Gateway ]                          |
| │                                             |
| X-API-Key + JWT + RateLimit                   |
| ▼                                             |
| [ FastAPI Container ] (MovieMind API)         |
| - /v1/movies                                  |
| - /v1/actors                                  |
| - /v1/generate                                |
| - webhook/email/slack                         |
| │                                             |
| (RabbitMQ Queue + Celery)                     |
| ▼                                             |
| [ PostgreSQL + Redis Cache ]                  |
| │                                             |
| [ Symfony Backend (Admin/API) ]               |
| - /admin/movies                               |
| - /admin/actors                               |
| - /api/jobs/status                            |
| - AI moderation, curation, analytics          |
+--------------------------------------------+
```

#### ⚖️ Dlaczego dwa, a nie jedno?

| Powód                   | Wyjaśnienie                                                                                                        |
| -------                 | -------------                                                                                                      |
| **Izolacja ryzyka**     | Publiczne API (FastAPI) jest lekkie i skalowalne, prywatne (Symfony) może mieć bardziej złożoną logikę i walidacje |
| **Zgodność z RapidAPI** | RapidAPI wymaga REST + JSON + szybkiego startu, Python jest tu naturalny                                           |
| **Komfort pracy**       | Ty jako PHP Dev masz w Symfony pełną kontrolę nad domeną, a AI worker nie blokuje requestów                        |
| **Rozdział kosztów**    | Możesz skalować AI worker (Python) niezależnie od panelu admina (PHP)                                              |
| **Rozwój SaaS**         | API publiczne → RapidAPI, API wewnętrzne → Twój panel / portal / integracje                                        |

#### 🧩 Krótko:

| Folder         | Technologia      | Rola                       | Udostępnienie           |
| --------       | -------------    | ------                     | ---------------         |
| `/src-fastapi` | Python (FastAPI) | Public API-as-a-Service    | RapidAPI / Public Cloud |
| `/src-laravel` | PHP (Laravel 11)  | Internal Admin / CMS / DDD | Private / Internal      |

### 🔹 3. Struktura Danych / Data Structure

#### Tabela: movies
| Pole                   | Typ      | Opis                |
| ------                 | -----    | ------              |
| id                     | int      | PK                  |
| title                  | varchar  | Tytuł               |
| release_year           | smallint | Rok produkcji       |
| director               | varchar  | Reżyser             |
| genres                 | text[]   | Gatunki             |
| default_description_id | int      | referencja do opisu |

#### Tabela: movie_descriptions
| Pole        | Typ         | Opis                   |
| ------      | -----       | ------                 |
| id          | int         | PK                     |
| movie_id    | int FK      | -                      |
| locale      | varchar(10) | np. pl-PL, en-US       |
| text        | text        | treść opisu            |
| context_tag | varchar(64) | np. modern, critical   |
| origin      | varchar(32) | GENERATED / TRANSLATED |
| ai_model    | varchar(64) | np. gpt-4o-mini        |
| created_at  | timestamp   | -                      |

#### Tabela: actors
| Pole           | Typ     |
| ------         | -----   |
| id             | int     |
| name           | varchar |
| birth_date     | date    |
| birthplace     | varchar |
| default_bio_id | int     |

#### Tabela: actor_bios
| Pole        | Typ         |
| ------      | -----       |
| id          | int         |
| actor_id    | int         |
| locale      | varchar(10) |
| text        | text        |
| context_tag | varchar(64) |
| origin      | varchar(32) |
| ai_model    | varchar(64) |
| created_at  | timestamp   |

#### Tabela: jobs
| Pole         | Typ                                 |
| ------       | -----                               |
| id           | int                                 |
| entity_type  | varchar(16) (MOVIE, ACTOR)          |
| entity_id    | int                                 |
| locale       | varchar(10)                         |
| status       | varchar(16) (PENDING, DONE, FAILED) |
| payload_json | jsonb                               |
| created_at   | timestamp                           |

### 🔹 4. MVP – Przepływ Działania (Happy Path)

1️⃣ **Klient uderza w:** `GET /v1/movies/123`
→ Symfony sprawdza w DB, czy istnieje opis (movie_descriptions).

2️⃣ **Jeśli brak** → tworzy rekord w jobs i odpala:
```bash
php bin/console messenger:consume async
```

3️⃣ **Worker (Messenger handler)** wywołuje:
API OpenAI → prompt: „Napisz opis filmu „Matrix" w stylu nowoczesnym, max 400 znaków, po polsku"
- zapisuje wynik do movie_descriptions
- ustawia jobs.status = DONE

4️⃣ **Klient pyta:** `GET /v1/jobs/{id}`
→ dostaje {status:"DONE"} i wynik z payload_json

5️⃣ **Następne zapytania** `GET /v1/movies/123` trafiają już w cache/DB, bez AI

### 🔹 5. Przykładowy Prompt (PL)

```
„Napisz zwięzły, unikalny opis filmu {title} z roku {year}.
Styl: {context_tag}.
Długość: 2–3 zdania, naturalny język, bez spoilera.
Język: {locale}.
Zwróć tylko czysty tekst."
```

### 🔹 6. Środowisko Uruchomieniowe / Runtime Environment

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
    environment:
      POSTGRES_USER: moviemind
      POSTGRES_PASSWORD: moviemind
      POSTGRES_DB: moviemind
  redis:
    image: redis:7
```

### 🔹 7. Zakres MVP (co NIE wchodzi)

🚫 **brak UI (panel admin)**  
🚫 **brak webhooków**  
🚫 **brak systemu planów / billingów**  
🚫 **brak multiuser auth**  
🚫 **brak monitoringów i metryk**  
🚫 **brak AI porównywania wersji** – tylko generacja i zapis  
🚫 **brak tłumaczeń automatycznych** (tylko język z Accept-Language)

### 💰 Po co MVP?

- Pozwala sprawdzić realne zapotrzebowanie (np. przez RapidAPI)
- Można zmierzyć koszty generacji i obciążenie cache/AI
- Daje fundament pod wersję PRO (webhooki, rate limit, panel, RapidAPI billing)

### 📘 MVP Output (finalny rezultat)

- 📁 repo moviemind-api
- ⚙️ działający docker-compose up
- 🧠 endpointy /v1/... działające w REST
- 💾 dane w PostgreSQL
- ⚡ async generacja opisów przez AI (Symfony Messenger)
- 🧱 prosty README.md i OpenAPI YAML

---

## 📋 Plan Działania - 10 Faz / Action Plan - 10 Phases

### 📋 Faza 1: Setup i Struktura (Tydzień 1) / Phase 1: Setup and Structure (Week 1)

#### 1.1 Publiczne Repozytorium / Public Repository
- [ ] **Utwórz publiczne repo** `moviemind-api-public` (GitHub)
- [ ] **Skonfiguruj Template Repository** (Settings → General → Template repository)
- [ ] **Włącz security features**:
  - [ ] Dependabot alerts
  - [ ] Secret scanning alerts
  - [ ] Branch protection rules (main)
  - [ ] Code owners (.github/CODEOWNERS)

#### 1.2 Struktura Projektu Publicznego / Public Project Structure
```bash
moviemind-api-public/
├── .github/
│   ├── CODEOWNERS
│   ├── dependabot.yml
│   └── workflows/
│       └── security-scan.yml
├── docs/
│   ├── branch-protection-rules.md
│   └── pre-commit-setup.md
├── scripts/
│   └── setup-pre-commit.sh
├── src-fastapi/          # Python FastAPI (publiczne API)
│   ├── app/
│   │   ├── api/
│   │   ├── core/
│   │   ├── models/
│   │   └── services/
│   ├── requirements.txt
│   └── Dockerfile
├── src-laravel/          # PHP Laravel (admin panel)
│   ├── src/
│   │   ├── Controller/
│   │   ├── Model/
│   │   ├── Service/
│   │   └── Mock/ (mock AI services)
│   ├── composer.json
│   └── Dockerfile
├── tests/
├── docker/
├── .env.example
├── docker-compose.yml
├── .gitleaks.toml
├── .pre-commit-config.yaml
├── LICENSE (MIT)
├── README.md
└── SECURITY.md
```

#### 1.3 Prywatne Repozytorium / Private Repository
- [ ] **Utwórz prywatne repo** `moviemind-api-private` (GitHub Private)
- [ ] **Skopiuj strukturę** z publicznego repo
- [ ] **Dodaj dodatkowe komponenty**:
  - [ ] `.env.production` (prawdziwe klucze)
  - [ ] `src/AI/` (prawdziwe prompty i logika AI)
  - [ ] `src/Billing/` (system płatności)
  - [ ] `src/Webhooks/` (webhooki RapidAPI)
  - [ ] `tests/integration/` (testy end-to-end)

### 📋 Faza 2: Infrastruktura i Docker (Tydzień 2) / Phase 2: Infrastructure and Docker (Week 2)

#### 2.1 Publiczne Repo - Mock Environment / Public Repo - Mock Environment
```yaml
# docker-compose.yml (publiczne repo)
services:
  # FastAPI - Publiczne API
  fastapi:
    build: ./src-fastapi
    ports: ["8000:8000"]
    environment:
      DATABASE_URL: postgresql://moviemind:moviemind@db:5432/moviemind
      REDIS_URL: redis://redis:6379/0
      OPENAI_API_KEY: mock-key-placeholder
      APP_ENV: dev
      APP_MODE: mock
    depends_on: [db, redis, rabbitmq]
  
  # Laravel - Admin Panel
  laravel:
    build: ./src-laravel
    ports: ["8001:80"]
    environment:
      DATABASE_URL: postgresql://moviemind:moviemind@db:5432/moviemind
      REDIS_URL: redis://redis:6379/0
      APP_ENV: dev
      APP_MODE: mock
    depends_on: [db, redis, rabbitmq]
  
  # Celery Worker (Python)
  celery:
    build: ./src-fastapi
    command: celery -A app.celery worker --loglevel=info
    environment:
      DATABASE_URL: postgresql://moviemind:moviemind@db:5432/moviemind
      REDIS_URL: redis://redis:6379/0
      OPENAI_API_KEY: mock-key-placeholder
    depends_on: [db, redis, rabbitmq]
  
  db:
    image: postgres:15
    environment:
      POSTGRES_USER: moviemind
      POSTGRES_PASSWORD: moviemind
      POSTGRES_DB: moviemind
  
  redis:
    image: redis:7
  
  rabbitmq:
    image: rabbitmq:3-management
    ports: ["5672:5672", "15672:15672"]
```

#### 2.2 Prywatne Repo - Production Environment / Private Repo - Production Environment
```yaml
# docker-compose.yml (prywatne repo)
services:
  # FastAPI - Publiczne API (produkcja)
  fastapi:
    build: ./src-fastapi
    ports: ["8000:8000"]
    environment:
      DATABASE_URL: ${DATABASE_URL}
      REDIS_URL: ${REDIS_URL}
      OPENAI_API_KEY: ${OPENAI_API_KEY}
      RAPIDAPI_WEBHOOK_SECRET: ${RAPIDAPI_WEBHOOK_SECRET}
      APP_ENV: production
      APP_MODE: real
    depends_on: [db, redis, rabbitmq]
  
  # Laravel - Admin Panel (produkcja)
  laravel:
    build: ./src-laravel
    ports: ["8001:80"]
    environment:
      DATABASE_URL: ${DATABASE_URL}
      REDIS_URL: ${REDIS_URL}
      APP_ENV: production
      APP_MODE: real
    depends_on: [db, redis, rabbitmq]
  
  # Celery Worker (Python) - produkcja
  celery:
    build: ./src-fastapi
    command: celery -A app.celery worker --loglevel=info
    environment:
      DATABASE_URL: ${DATABASE_URL}
      REDIS_URL: ${REDIS_URL}
      OPENAI_API_KEY: ${OPENAI_API_KEY}
    depends_on: [db, redis, rabbitmq]
  
  db:
    image: postgres:15
    environment:
      POSTGRES_DB: moviemind_prod
      POSTGRES_USER: ${DB_USER}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
  
  redis:
    image: redis:7
    command: redis-server --requirepass ${REDIS_PASSWORD}
  
  rabbitmq:
    image: rabbitmq:3-management
    environment:
      RABBITMQ_DEFAULT_USER: ${RABBITMQ_USER}
      RABBITMQ_DEFAULT_PASS: ${RABBITMQ_PASSWORD}
    ports: ["5672:5672", "15672:15672"]
```

### 📋 Faza 3: Mock API Endpoints (Tydzień 3) / Phase 3: Mock API Endpoints (Week 3)

#### 🐍 FastAPI Endpoints (Publiczne Repo) / FastAPI Endpoints (Public Repo)
```python
# src-fastapi/app/api/movies.py
from fastapi import APIRouter, HTTPException
from typing import List, Optional
from ..models.movie import Movie, MovieResponse
from ..services.movie_service import MovieService

router = APIRouter(prefix="/v1/movies", tags=["movies"])

@router.get("/", response_model=List[MovieResponse])
async def get_movies(q: Optional[str] = None):
    """Search movies by title, year, or genre"""
    movies = await MovieService.search_movies(q)
    return movies

@router.get("/{movie_id}", response_model=MovieResponse)
async def get_movie(movie_id: int):
    """Get movie details with AI-generated description"""
    movie = await MovieService.get_movie_with_description(movie_id)
    if not movie:
        raise HTTPException(status_code=404, detail="Movie not found")
    return movie

@router.post("/{movie_id}/generate")
async def generate_description(movie_id: int, context: str = "modern"):
    """Trigger AI generation of movie description"""
    job_id = await MovieService.generate_description(movie_id, context)
    return {"job_id": job_id, "status": "PENDING"}

@router.get("/jobs/{job_id}")
async def get_job_status(job_id: str):
    """Check generation job status"""
    job = await MovieService.get_job_status(job_id)
    return job
```

#### 🎬 Laravel Controller (Admin Panel) / Laravel Controller (Admin Panel)
```php
<?php
// app/Http/Controllers/MovieController.php (publiczne repo)
class MovieController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Mock data - przykładowe filmy
        $movies = [
            [
                'id' => 1,
                'title' => 'The Matrix',
                'release_year' => 1999,
                'director' => 'The Wachowskis',
                'genres' => ['Action', 'Sci-Fi'],
                'description' => 'This is a demo AI-generated description for The Matrix...',
                'ai_generated' => true,
                'mock_mode' => true
            ],
            [
                'id' => 2,
                'title' => 'Inception',
                'release_year' => 2010,
                'director' => 'Christopher Nolan',
                'genres' => ['Action', 'Sci-Fi', 'Thriller'],
                'description' => 'This is a demo AI-generated description for Inception...',
                'ai_generated' => true,
                'mock_mode' => true
            ]
        ];

        return response()->json([
            'data' => $movies,
            'total' => count($movies),
            'mock_mode' => true
        ]);
    }

    public function show(int $id): JsonResponse
    {
        // Mock implementation
        return response()->json([
            'id' => $id,
            'title' => 'The Matrix',
            'release_year' => 1999,
            'director' => 'The Wachowskis',
            'genres' => ['Action', 'Sci-Fi'],
            'description' => 'This is a demo AI-generated description...',
            'ai_generated' => true,
            'mock_mode' => true
        ]);
    }
}
```

### 📋 Faza 4: Mock AI Integration (Tydzień 4) / Phase 4: Mock AI Integration (Week 4)

#### 🤖 MockAIService (Publiczne Repo) / MockAIService (Public Repo)
```php
<?php
// app/Services/MockAIService.php (publiczne repo)
class MockAIService
{
    public function generateDescription(string $title, string $context = 'modern'): string
    {
        // Mock responses based on context
        $mockDescriptions = [
            'modern' => "This is a modern, engaging description of {$title}...",
            'critical' => "A critical analysis of {$title} reveals...",
            'humorous' => "{$title} is a film that... (insert witty commentary here)"
        ];

        return $mockDescriptions[$context] ?? $mockDescriptions['modern'];
    }

    public function generateBio(string $actorName): string
    {
        return "This is a mock AI-generated biography for {$actorName}...";
    }

    public function isMockMode(): bool
    {
        return true;
    }
}
```

### 📋 Faza 5: Real AI Integration (Tydzień 5-6) / Phase 5: Real AI Integration (Week 5-6)

#### 🤖 RealAIService (Prywatne Repo) / RealAIService (Private Repo)
```php
<?php
// app/Services/RealAIService.php (prywatne repo)
class RealAIService
{
    private string $openaiApiKey;
    private HttpClientInterface $httpClient;

    public function __construct(string $openaiApiKey, HttpClientInterface $httpClient)
    {
        $this->openaiApiKey = $openaiApiKey;
        $this->httpClient = $httpClient;
    }

    public function generateDescription(string $title, string $context = 'modern'): string
    {
        $prompt = $this->buildPrompt($title, $context);
        
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->openaiApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'user', 'content' => $prompt]
                ],
                'max_tokens' => 500,
                'temperature' => 0.7
            ]
        ]);

        $data = json_decode($response->getContent(), true);
        return $data['choices'][0]['message']['content'];
    }

    private function buildPrompt(string $title, string $context): string
    {
        $contextPrompts = [
            'modern' => 'Write a modern, engaging description',
            'critical' => 'Write a critical analysis',
            'humorous' => 'Write a witty, humorous description'
        ];

        return "{$contextPrompts[$context]} for the movie '{$title}'. Make it unique and original, not copied from other sources.";
    }
}
```

### 📋 Faza 6: Caching i Performance (Tydzień 7) / Phase 6: Caching and Performance (Week 7)

#### ⚡ Redis Cache Implementation
```php
<?php
// app/Services/CacheService.php (oba repozytoria)
class CacheService
{
    private Redis $redis;

    public function __construct(Redis $redis)
    {
        $this->redis = $redis;
    }

    public function getDescription(int $entityId, string $entityType, string $locale, string $context): ?string
    {
        $key = "desc:{$entityType}:{$entityId}:{$locale}:{$context}";
        return $this->redis->get($key);
    }

    public function setDescription(int $entityId, string $entityType, string $locale, string $context, string $content): void
    {
        $key = "desc:{$entityType}:{$entityId}:{$locale}:{$context}";
        $this->redis->setex($key, 86400, $content); // 24 hours
    }

    public function invalidateEntity(int $entityId, string $entityType): void
    {
        $pattern = "desc:{$entityType}:{$entityId}:*";
        $keys = $this->redis->keys($pattern);
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }
}
```

### 📋 Faza 7: Multilingual Support (Tydzień 8) / Phase 7: Multilingual Support (Week 8)

#### 🌍 Locale Management
```php
<?php
// app/Services/LocaleService.php (oba repozytoria)
class LocaleService
{
    private array $supportedLocales = [
        'en-US' => 'English (US)',
        'pl-PL' => 'Polski',
        'es-ES' => 'Español',
        'fr-FR' => 'Français',
        'de-DE' => 'Deutsch'
    ];

    public function getSupportedLocales(): array
    {
        return $this->supportedLocales;
    }

    public function isValidLocale(string $locale): bool
    {
        return array_key_exists($locale, $this->supportedLocales);
    }

    public function getDefaultLocale(): string
    {
        return 'en-US';
    }

    public function generateForLocale(string $entityType, int $entityId, string $locale, string $context): string
    {
        // Different logic for different locales
        if ($locale === 'pl-PL') {
            return $this->generatePolishContent($entityType, $entityId, $context);
        }
        
        return $this->generateEnglishContent($entityType, $entityId, $context);
    }
}
```

### 📋 Faza 8: Testing i Quality Assurance (Tydzień 9) / Phase 8: Testing and Quality Assurance (Week 9)

#### 🧪 Test Structure
```bash
tests/
├── Unit/
│   ├── Services/
│   │   ├── MockAIServiceTest.php
│   │   ├── CacheServiceTest.php
│   │   └── LocaleServiceTest.php
│   └── Controllers/
│       ├── MovieControllerTest.php
│       └── ActorControllerTest.php
├── Integration/
│   ├── ApiTest.php
│   └── DatabaseTest.php
└── Functional/
    ├── MovieApiTest.php
    └── ActorApiTest.php
```

#### 📊 Code Quality Metrics
- **Test Coverage**: Minimum 80%
- **Code Quality**: PHPStan level 8
- **Security**: No critical vulnerabilities
- **Performance**: Response time < 200ms

### 📋 Faza 9: Documentation i API Docs (Tydzień 10) / Phase 9: Documentation and API Docs (Week 10)

#### 📚 API Documentation
```yaml
# openapi.yaml (oba repozytoria)
openapi: 3.0.0
info:
  title: MovieMind API
  description: AI-powered Film & Series Metadata API
  version: 1.0.0
  contact:
    name: MovieMind API Support
    email: support@moviemind.com

paths:
  /v1/movies:
    get:
      summary: Search movies
      parameters:
        - name: q
          in: query
          description: Search query
          required: false
          schema:
            type: string
      responses:
        '200':
          description: List of movies
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items:
                      $ref: '#/components/schemas/Movie'
                  total:
                    type: integer
```

### 📋 Faza 10: RapidAPI Preparation i Launch (Tydzień 11-12) / Phase 10: RapidAPI Preparation and Launch (Week 11-12)

#### 🚀 RapidAPI Integration (Prywatne Repo) / RapidAPI Integration (Private Repo)
```php
<?php
// app/Services/RapidAPIService.php (prywatne repo)
class RapidAPIService
{
    private string $webhookSecret;
    private HttpClientInterface $httpClient;

    public function handleWebhook(Request $request): JsonResponse
    {
        $signature = $request->header('X-RapidAPI-Signature');
        $payload = $request->getContent();
        
        if (!$this->verifySignature($signature, $payload)) {
            throw new UnauthorizedHttpException('Invalid signature');
        }

        $data = json_decode($payload, true);
        
        // Handle different webhook events
        switch ($data['event']) {
            case 'subscription.created':
                return $this->handleSubscriptionCreated($data);
            case 'subscription.cancelled':
                return $this->handleSubscriptionCancelled($data);
            case 'usage.exceeded':
                return $this->handleUsageExceeded($data);
        }

        return response()->json(['status' => 'ok']);
    }

    private function verifySignature(string $signature, string $payload): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }
}
```

---

## 🌳 Git Trunk Flow

### 🇵🇱 Strategia Zarządzania Kodem / Code Management Strategy

Używamy **Git Trunk Flow** jako głównej strategii zarządzania kodem dla MovieMind API.

We use **Git Trunk Flow** as the main code management strategy for MovieMind API.

### ✅ Zalety Trunk Flow / Trunk Flow Advantages:
- **Prostszy workflow** - jeden główny branch (main) / **Simpler workflow** - single main branch (main)
- **Szybsze integracje** - częste mergowanie do main / **Faster integrations** - frequent merging to main
- **Mniej konfliktów** - krótsze żywotność feature branchy / **Fewer conflicts** - shorter feature branch lifetime
- **Lepsze CI/CD** - każdy commit na main może być deployowany / **Better CI/CD** - every commit on main can be deployed
- **Feature flags** - kontrola funkcji bez branchy / **Feature flags** - feature control without branches
- **Rollback** - łatwy rollback przez feature flags / **Rollback** - easy rollback through feature flags

### 🔄 Workflow:
1. **Feature branch** - `feature/ai-description-generation`
2. **Pull Request** - code review i testy / code review and tests
3. **Merge do main** - po zatwierdzeniu / **Merge to main** - after approval
4. **Deploy** - automatyczny deploy z feature flags / automatic deploy with feature flags
5. **Feature flag** - kontrola włączenia funkcji / feature enablement control

### 🛠️ Implementacja / Implementation:
- **Main branch** - zawsze deployable / always deployable
- **Feature branchy** - krótkoterminowe (1-3 dni) / **Feature branches** - short-term (1-3 days)
- **Feature flags** - kontrola funkcji w runtime / runtime feature control
- **CI/CD** - automatyczny deploy na każdy merge / automatic deploy on every merge

---

## 🎛️ Feature Flags

### 🇵🇱 Strategia Kontroli Funkcji / Feature Control Strategy

Używamy **oficjalnej integracji Laravel Feature Flags** (`laravel/feature-flags`) zamiast własnej implementacji.

We use **official Laravel Feature Flags integration** (`laravel/feature-flags`) instead of custom implementation.

### ✅ Zalety oficjalnej integracji Laravel / Official Laravel integration advantages:
- **Oficjalne wsparcie** - wspierane przez Laravel team / **Official support** - supported by Laravel team
- **Prostota** - gotowe API i funkcje / **Simplicity** - ready-made API and functions
- **Bezpieczeństwo** - przetestowane przez społeczność / **Security** - tested by community
- **Integracja** - idealna integracja z Laravel / **Integration** - perfect Laravel integration
- **Funkcje** - więcej funkcji out-of-the-box / **Features** - more features out-of-the-box
- **Maintenance** - utrzymywane przez zespół Laravel / **Maintenance** - maintained by Laravel team

### 🎛️ Typy Feature Flags / Feature Flag Types:
1. **Boolean flags** - włącz/wyłącz funkcje / enable/disable features
2. **Percentage flags** - gradual rollout (0-100%)
3. **User-based flags** - dla konkretnych użytkowników / for specific users
4. **Environment flags** - różne ustawienia per środowisko / different settings per environment

### 🔧 Implementacja Laravel Feature Flags / Laravel Feature Flags Implementation:
```php
<?php
// Instalacja / Installation
composer require laravel/feature-flags

// Użycie w kontrolerze / Usage in controller
use Laravel\FeatureFlags\Facades\FeatureFlags;

class MovieController extends Controller
{
    public function generateDescription(Movie $movie, Request $request): JsonResponse
    {
        // Sprawdź czy funkcja jest włączona / Check if feature is enabled
        if (!FeatureFlags::enabled('ai_description_generation')) {
            return response()->json(['error' => 'Feature not available'], 403);
        }

        // Sprawdź gradual rollout dla nowych modeli / Check gradual rollout for new models
        if (FeatureFlags::enabled('gpt4_generation')) {
            $model = 'gpt-4';
        } else {
            $model = 'gpt-3.5-turbo';
        }

        // Generuj opis z wybranym modelem / Generate description with selected model
        GenerateDescriptionJob::dispatch($movie, $request->input('context'), $model);

        return response()->json(['message' => 'Description generation started']);
    }
}
```

### ⚙️ Konfiguracja Feature Flags / Feature Flags Configuration:
```php
<?php
// config/feature-flags.php
return [
    'ai_description_generation' => true,
    'gpt4_generation' => [
        'enabled' => true,
        'percentage' => 25 // 25% użytkowników / 25% of users
    ],
    'multilingual_support' => [
        'enabled' => true,
        'percentage' => 50 // 50% użytkowników / 50% of users
    ],
    'style_packs' => false // Wyłączone / Disabled
];
```

---

## 🔐 Bezpieczeństwo i Zarządzanie Kluczami / Security and Key Management

### 🔒 Zasada Ogólna / General Principle

❌ **Nigdy nie commituj prawdziwych kluczy API** (OpenAI, RapidAPI, SMTP, RabbitMQ)  
✅ **Używaj .env tylko lokalnie/na serwerze**  
✅ **Commituj wyłącznie .env.example** (placeholdery)

❌ **Never commit real API keys** (OpenAI, RapidAPI, SMTP, RabbitMQ)  
✅ **Use .env only locally/on server**  
✅ **Commit only .env.example** (placeholders)

### 📄 Struktura Plików Środowiskowych / Environment Files Structure

**`.env.example` (commitowany):**
```bash
# =========================================
# MovieMind API — Example Environment File
# =========================================
# Skopiuj ten plik do `.env` i uzupełnij własne dane.

APP_ENV=dev
APP_DEBUG=1
APP_MODE=mock   # demo | mock | prod

# Database
DATABASE_URL=postgresql://moviemind:moviemind@db:5432/moviemind

# Cache
REDIS_URL=redis://redis:6379/0

# AI Integrations (OpenAI or other)
OPENAI_API_KEY=sk-REPLACE_ME
OPENAI_MODEL=gpt-4o-mini

# RapidAPI
RAPIDAPI_KEY=your-rapidapi-key-here
RAPIDAPI_PLAN=FREE

# SMTP (Mailhog / sandbox)
SMTP_DSN=smtp://user:pass@mailhog:1025

# RabbitMQ (async queue)
RABBITMQ_DSN=amqp://guest:guest@rabbitmq:5672/%2f

# API Settings
API_RATE_LIMIT=60/minute
DEFAULT_LANGUAGE=pl-PL

# Security
JWT_SECRET=change-me-in-prod
ADMIN_DEFAULT_USER=admin
ADMIN_DEFAULT_PASS=admin123
```

**`.gitignore` (commitowany):**
```gitignore
# Environment files
.env
.env.local
.env.prod
.env.production

# Secrets / Keys
*.pem
*.key
*.crt
secrets/
jwt/
```

### 🛡️ Security Checklist (przed publikacją)

**Brak sekretów w repo:**
- [ ] `.env`, `.env.*` są w `.gitignore`
- [ ] `gitleaks detect --source .` przechodzi na czysto
- [ ] Brak kluczy w README, issue, commitach i historii

**Pliki środowiskowe:**
- [ ] Jest tylko `.env.example` z placeholderami
- [ ] README jasno opisuje kopiowanie do `.env` i tryb `APP_MODE=mock`

**Konfiguracja CI/CD:**
- [ ] Wszystkie klucze w GitHub Actions → Secrets
- [ ] Workflow nie wypisuje zmiennych środowisk w logach
- [ ] Artefakty buildów nie zawierają `.env`

---

## 💰 Monetyzacja (RapidAPI) / Monetization (RapidAPI)

| Plan           | Limit                  | Features                                           |
| ------         | -------                | ----------                                         |
| **Free**       | 100 zapytań/miesiąc    | Dostęp tylko do danych w bazie (bez generowania)   |
| **Pro**        | 10 000 zapytań/miesiąc | Możliwość regeneracji opisów AI i wyboru kontekstu |
| **Enterprise** | Nielimitowany          | API + dedykowane modele AI + webhooki              |

---

## ⚖️ Strategia Licencjonowania / Licensing Strategy

### Scenariusz A: Portfolio (tylko do wglądu)
**Licencja**: "No License" lub Creative Commons BY-NC (non-commercial)

### Scenariusz B: Open Source w Portfolio
**Licencja**: MIT lub Apache 2.0

### Scenariusz C: Komercyjny SaaS (RapidAPI / płatne API)
**Strategia dual-license:**
- **Public repo**: MIT / CC-BY-NC (non-commercial)
- **Private repo**: własna licencja komercyjna (np. "MovieMind Commercial License 1.0")

### 🧠 Rekomendacja dla MovieMind API

| Element                         | Wersja Publiczna   | Wersja Prywatna                         |
| ---------                       | ------------------ | -----------------                       |
| **Backend**                     | Laravel (MVP)      | Laravel + AI Workers                    |
| **AI generacja**                | stub/mock          | pełny prompt i model                    |
| **Cache + DB**                  | ✅                  | ✅                                       |
| **Rate Limit, Billing**         | ❌                  | ✅                                       |
| **Webhooki, Jobs, Admin Panel** | ❌                  | ✅                                       |
| **Licencja**                    | MIT lub CC-BY-NC   | własna ("MovieMind Commercial License") |

---

## 🎯 Podsumowanie Strategii / Strategy Summary

### 📊 Porównanie Repozytoriów / Repository Comparison

| Aspekt / Aspect                  | Publiczne / Public              | Prywatne / Private             |
| -----------------                | -------------------             | -------------------            |
| **Kod / Code**                   | Mock services, przykładowe dane | Prawdziwe AI, produkcyjne dane |
| **Bezpieczeństwo / Security**    | Brak kluczy API                 | Prawdziwe klucze, webhooki     |
| **Testy / Tests**                | Unit tests, mock tests          | Integration tests, E2E tests   |
| **Dokumentacja / Documentation** | Portfolio, architektura         | API docs, deployment guides    |
| **Licencja / License**           | MIT (open source)               | Custom commercial              |
| **Cel / Purpose**                | Demonstracja umiejętności       | Komercyjny produkt             |

### 🚀 Następne Kroki / Next Steps

1. **Tydzień 1-2**: Setup repozytoriów i podstawowej infrastruktury
2. **Tydzień 3-4**: Implementacja mock API w publicznym repo
3. **Tydzień 5-6**: Implementacja prawdziwego AI w prywatnym repo
4. **Tydzień 7-8**: Caching i wielojęzyczność
5. **Tydzień 9-10**: Testy i dokumentacja
6. **Tydzień 11-12**: RapidAPI i launch

### 💡 Kluczowe Zasady / Key Principles

- **Bezpieczeństwo**: Nigdy nie commituj prawdziwych kluczy API
- **Separacja**: Publiczne repo = portfolio, Prywatne repo = produkt
- **Jakość**: Wysokie standardy kodu w obu repozytoriach
- **Dokumentacja**: Kompletna dokumentacja dla każdego komponentu

---

**📝 Note**: Ten dokument stanowi kompletną specyfikację projektu MovieMind API, łącząc oryginalny wątek ChatGPT z aktualnymi specyfikacjami i planami działania. Zapewnia elastyczność rozwoju od MVP do pełnego produktu komercyjnego, zachowując bezpieczeństwo i profesjonalizm w obu repozytoriach.

**📝 Note**: This document constitutes a complete specification of the MovieMind API project, combining the original ChatGPT thread with current specifications and action plans. It provides flexibility for development from MVP to full commercial product, maintaining security and professionalism in both repositories.

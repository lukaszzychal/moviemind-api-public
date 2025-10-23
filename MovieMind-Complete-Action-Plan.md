# MovieMind API - Kompleksowy Plan Działania / Comprehensive Action Plan

> **📝 Note / Uwaga**: This document provides a complete action plan for implementing the MovieMind API project with dual-repository strategy from concept to production.  
> **📝 Uwaga**: Ten dokument zawiera kompletny plan działania dla implementacji projektu MovieMind API ze strategią dual-repository od koncepcji do produkcji.

## 🎯 Przegląd Strategii / Strategy Overview

### 🏗️ Dual-Repository Approach / Podejście Dual-Repository

| Aspekt / Aspect | Repozytorium Publiczne / Public Repository | Repozytorium Prywatne / Private Repository |
|-----------------|---------------------------------------------|---------------------------------------------|
| **Cel / Goal** | Portfolio, demonstracja umiejętności / Portfolio, skills demonstration | Produkcja, komercyjny produkt / Production, commercial product |
| **Zawartość / Content** | Okrojony kod, mock AI, dokumentacja / Trimmed code, mock AI, documentation | Pełny kod, realne AI, billing, webhooki / Full code, real AI, billing, webhooks |
| **Bezpieczeństwo / Security** | Brak kluczy API, przykładowe dane / No API keys, sample data | Prawdziwe klucze, dane produkcyjne / Real keys, production data |
| **Licencja / License** | MIT / CC-BY-NC | Własna komercyjna / Custom commercial |
| **Timeline / Harmonogram** | 6 tygodni (MVP) | 8-12 tygodni (pełny produkt) |

---

## 📋 Faza 1: Setup i Struktura (Tydzień 1) / Phase 1: Setup and Structure (Week 1)

### 🏗️ Repozytoria i Bezpieczeństwo / Repositories and Security

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
├── src/
│   ├── Controller/
│   ├── Entity/
│   ├── Service/
│   └── Mock/ (mock AI services)
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

---

## 📋 Faza 2: Infrastruktura i Docker (Tydzień 2) / Phase 2: Infrastructure and Docker (Week 2)

### 🐳 Docker Configuration

#### 2.1 Publiczne Repo - Mock Environment / Public Repo - Mock Environment
```yaml
# docker-compose.yml (publiczne repo)
services:
  api:
    build: .
    ports: ["8000:80"]
    environment:
      DATABASE_URL: postgresql://moviemind:moviemind@db:5432/moviemind
      REDIS_URL: redis://redis:6379/0
      OPENAI_API_KEY: mock-key-placeholder
      APP_ENV: dev
      APP_MODE: mock
  db:
    image: postgres:15
  redis:
    image: redis:7
```

#### 2.2 Prywatne Repo - Production Environment / Private Repo - Production Environment
```yaml
# docker-compose.yml (prywatne repo)
services:
  api:
    build: .
    ports: ["8000:80"]
    environment:
      DATABASE_URL: ${DATABASE_URL}
      REDIS_URL: ${REDIS_URL}
      OPENAI_API_KEY: ${OPENAI_API_KEY}
      RAPIDAPI_WEBHOOK_SECRET: ${RAPIDAPI_WEBHOOK_SECRET}
      APP_ENV: production
      APP_MODE: real
  db:
    image: postgres:15
    environment:
      POSTGRES_DB: moviemind_prod
      POSTGRES_USER: ${DB_USER}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
  redis:
    image: redis:7
    command: redis-server --requirepass ${REDIS_PASSWORD}
```

### 🗄️ Database Schema / Schemat Bazy Danych
```sql
-- Tabele podstawowe (w obu repozytoriach)
CREATE TABLE movies (
    id SERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    release_year SMALLINT,
    director VARCHAR(255),
    genres TEXT[],
    default_description_id INTEGER,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE actors (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    birth_year SMALLINT,
    nationality VARCHAR(100),
    default_bio_id INTEGER,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE descriptions (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(20) NOT NULL, -- 'MOVIE' or 'ACTOR'
    entity_id INTEGER NOT NULL,
    content TEXT NOT NULL,
    locale VARCHAR(10) DEFAULT 'en-US',
    context_tag VARCHAR(50), -- 'modern', 'critical', 'humorous'
    ai_generated BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW()
);

CREATE TABLE generation_jobs (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(20) NOT NULL,
    entity_id INTEGER NOT NULL,
    locale VARCHAR(10) NOT NULL,
    context_tag VARCHAR(50),
    status VARCHAR(20) DEFAULT 'PENDING', -- 'PENDING', 'PROCESSING', 'DONE', 'FAILED'
    created_at TIMESTAMP DEFAULT NOW(),
    completed_at TIMESTAMP
);
```

---

## 📋 Faza 3: Mock API Endpoints (Tydzień 3) / Phase 3: Mock API Endpoints (Week 3)

### 🎬 Movie Controller (Publiczne Repo) / Movie Controller (Public Repo)
```php
<?php
// src/Controller/MovieController.php (publiczne repo)
class MovieController extends AbstractController
{
    public function getMovies(Request $request): JsonResponse
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

        return $this->json([
            'data' => $movies,
            'total' => count($movies),
            'mock_mode' => true
        ]);
    }

    public function getMovie(int $id): JsonResponse
    {
        // Mock implementation
        return $this->json([
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

### 🎭 Actor Controller (Publiczne Repo) / Actor Controller (Public Repo)
```php
<?php
// src/Controller/ActorController.php (publiczne repo)
class ActorController extends AbstractController
{
    public function getActor(int $id): JsonResponse
    {
        // Mock implementation
        return $this->json([
            'id' => $id,
            'name' => 'Keanu Reeves',
            'birth_year' => 1964,
            'nationality' => 'Canadian',
            'bio' => 'This is a demo AI-generated biography...',
            'ai_generated' => true,
            'mock_mode' => true
        ]);
    }
}
```

---

## 📋 Faza 4: Mock AI Integration (Tydzień 4) / Phase 4: Mock AI Integration (Week 4)

### 🤖 MockAIService (Publiczne Repo) / MockAIService (Public Repo)
```php
<?php
// src/Service/MockAIService.php (publiczne repo)
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

### 🔄 Generation Controller (Publiczne Repo) / Generation Controller (Public Repo)
```php
<?php
// src/Controller/GenerationController.php (publiczne repo)
class GenerationController extends AbstractController
{
    public function generate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Mock job creation
        $jobId = rand(1000, 9999);
        
        return $this->json([
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Mock generation job created',
            'mock_mode' => true
        ]);
    }

    public function getJobStatus(int $jobId): JsonResponse
    {
        // Mock job status
        return $this->json([
            'job_id' => $jobId,
            'status' => 'DONE',
            'result' => 'Mock AI-generated content',
            'mock_mode' => true
        ]);
    }
}
```

---

## 📋 Faza 5: Real AI Integration (Tydzień 5-6) / Phase 5: Real AI Integration (Week 5-6)

### 🤖 RealAIService (Prywatne Repo) / RealAIService (Private Repo)
```php
<?php
// src/Service/RealAIService.php (prywatne repo)
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

---

## 📋 Faza 6: Caching i Performance (Tydzień 7) / Phase 6: Caching and Performance (Week 7)

### ⚡ Redis Cache Implementation
```php
<?php
// src/Service/CacheService.php (oba repozytoria)
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

---

## 📋 Faza 7: Multilingual Support (Tydzień 8) / Phase 7: Multilingual Support (Week 8)

### 🌍 Locale Management
```php
<?php
// src/Service/LocaleService.php (oba repozytoria)
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

---

## 📋 Faza 8: Testing i Quality Assurance (Tydzień 9) / Phase 8: Testing and Quality Assurance (Week 9)

### 🧪 Test Structure
```bash
tests/
├── Unit/
│   ├── Service/
│   │   ├── MockAIServiceTest.php
│   │   ├── CacheServiceTest.php
│   │   └── LocaleServiceTest.php
│   └── Controller/
│       ├── MovieControllerTest.php
│       └── ActorControllerTest.php
├── Integration/
│   ├── ApiTest.php
│   └── DatabaseTest.php
└── Functional/
    ├── MovieApiTest.php
    └── ActorApiTest.php
```

### 📊 Code Quality Metrics
- **Test Coverage**: Minimum 80%
- **Code Quality**: PHPStan level 8
- **Security**: No critical vulnerabilities
- **Performance**: Response time < 200ms

---

## 📋 Faza 9: Documentation i API Docs (Tydzień 10) / Phase 9: Documentation and API Docs (Week 10)

### 📚 API Documentation
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

---

## 📋 Faza 10: RapidAPI Preparation i Launch (Tydzień 11-12) / Phase 10: RapidAPI Preparation and Launch (Week 11-12)

### 🚀 RapidAPI Integration (Prywatne Repo) / RapidAPI Integration (Private Repo)
```php
<?php
// src/Service/RapidAPIService.php (prywatne repo)
class RapidAPIService
{
    private string $webhookSecret;
    private HttpClientInterface $httpClient;

    public function handleWebhook(Request $request): JsonResponse
    {
        $signature = $request->headers->get('X-RapidAPI-Signature');
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

        return $this->json(['status' => 'ok']);
    }

    private function verifySignature(string $signature, string $payload): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $this->webhookSecret);
        return hash_equals($expectedSignature, $signature);
    }
}
```

### 💰 Billing Integration (Prywatne Repo) / Billing Integration (Private Repo)
```php
<?php
// src/Service/BillingService.php (prywatne repo)
class BillingService
{
    public function checkRateLimit(string $apiKey, string $endpoint): bool
    {
        $subscription = $this->getSubscription($apiKey);
        
        switch ($subscription['plan']) {
            case 'free':
                return $this->checkFreePlanLimit($apiKey, $endpoint);
            case 'pro':
                return $this->checkProPlanLimit($apiKey, $endpoint);
            case 'enterprise':
                return true; // No limits
        }
        
        return false;
    }

    private function checkFreePlanLimit(string $apiKey, string $endpoint): bool
    {
        $usage = $this->getDailyUsage($apiKey);
        $limit = $this->getFreePlanLimit($endpoint);
        
        return $usage < $limit;
    }
}
```

---

## 🎯 Podsumowanie Strategii / Strategy Summary

### 📊 Porównanie Repozytoriów / Repository Comparison

| Aspekt / Aspect | Publiczne / Public | Prywatne / Private |
|-----------------|-------------------|-------------------|
| **Kod / Code** | Mock services, przykładowe dane | Prawdziwe AI, produkcyjne dane |
| **Bezpieczeństwo / Security** | Brak kluczy API | Prawdziwe klucze, webhooki |
| **Testy / Tests** | Unit tests, mock tests | Integration tests, E2E tests |
| **Dokumentacja / Documentation** | Portfolio, architektura | API docs, deployment guides |
| **Licencja / License** | MIT (open source) | Custom commercial |
| **Cel / Purpose** | Demonstracja umiejętności | Komercyjny produkt |

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

**📝 Note**: Ten plan zapewnia elastyczność rozwoju od MVP do pełnego produktu komercyjnego, zachowując bezpieczeństwo i profesjonalizm w obu repozytoriach.

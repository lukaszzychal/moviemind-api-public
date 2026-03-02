# MovieMind API - Kontekst Projektu

> **Ten plik zawiera kontekst o projekcie MovieMind API dla AI asystenta.**
> 
> Jest automatycznie wczytywany przez Cursor IDE gdy opcja "Include CLAUDE.md in context" jest włączona w ustawieniach.

---

## 🎯 Przegląd Projektu

MovieMind API to RESTful API do generowania i przechowywania unikalnych opisów filmów, seriali i aktorów przy użyciu technologii AI. Projekt tworzy oryginalną, wygenerowaną przez AI treść zamiast kopiować zawartość z IMDb czy TMDb.

---

## 🏗️ Stack Technologiczny

### Backend
- **Framework:** Laravel 12
- **PHP:** 8.2+
- **Baza danych:** PostgreSQL (produkcja i testy)
- **Cache:** Redis
- **Queue:** Laravel Horizon (asynchroniczne przetwarzanie)
- **AI Integration:** OpenAI API (gpt-4o-mini)

### Narzędzia Rozwojowe
- **Testy:** PHPUnit (Feature Tests + Unit Tests)
- **Formatowanie:** Laravel Pint (PSR-12)
- **Analiza statyczna:** PHPStan (poziom 5)
- **Bezpieczeństwo:** GitLeaks (wykrywanie sekretów)
- **Dokumentacja:** OpenAPI/Swagger

---

## 📁 Struktura Projektu

### Główna Struktura
```
api/                          # Aplikacja Laravel
├── app/
│   ├── Enums/               # Enumeracje (Language, EntityType, etc.)
│   ├── Events/              # Eventy Laravel
│   ├── Features/            # Feature-based code
│   ├── Helpers/             # Helper functions
│   ├── Http/
│   │   ├── Controllers/     # Controllers API
│   │   ├── Requests/        # Request validators
│   │   └── Resources/        # API Resources
│   ├── Jobs/                # Queue Jobs (ShouldQueue)
│   ├── Listeners/           # Event Listeners
│   ├── Models/              # Eloquent Models
│   ├── Repositories/        # Repository pattern
│   └── Services/            # Business logic services
├── config/                  # Konfiguracja Laravel
├── database/
│   ├── migrations/          # Migracje bazy danych
│   └── seeders/             # Seedery
├── routes/
│   └── api.php              # Route definitions
└── tests/
    ├── Feature/             # Feature tests (API endpoints)
    └── Unit/                # Unit tests (classes, services)
```

---

## 🗄️ Model Danych

### Główne Tabele

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
- Podobna struktura do Movies/Descriptions
- `actors` - podstawowe dane aktora
- `actor_bios` - AI-generated biografie

**Jobs (Async Processing)**
- `id` (PK)
- `entity_type` (MOVIE, ACTOR)
- `entity_id`
- `locale`
- `status` (PENDING, DONE, FAILED)
- `payload_json`
- `created_at`

---

## 🔄 Architektura i Flow

### Obecny Flow (Laravel Events + Jobs)
```
Controller
  ↓
Event (np. MovieGenerationRequested)
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

### Wzorce Projektowe
- **Repository Pattern** - abstrakcja dostępu do danych
- **Service Layer** - logika biznesowa
- **Event-Driven** - Events + Listeners dla asynchronicznych operacji
- **Queue Jobs** - długotrwałe operacje (AI generation)

---

## 🧪 Testy

### Rodzaje Testów

1. **Feature Tests** (`tests/Feature/`)
   - Testują endpointy API
   - Używają bazy testowej (PostgreSQL, wymagany Docker – zobacz docs/knowledge/reference/TESTING_DATABASE.md)
   - Przykład: `MovieControllerTest`, `GenerateApiTest`

2. **Unit Tests** (`tests/Unit/`)
   - Testują pojedyncze klasy i metody
   - Szybkie, izolowane
   - Przykład: `MovieServiceTest`, `ValidationHelperTest`

### TDD Workflow
- **RED** - Napisz test, który definiuje wymaganie
- **GREEN** - Napisz minimalny kod do przejścia testu
- **REFACTOR** - Popraw kod, zachowując przechodzące testy

**WAŻNE:** Zawsze pisz testy przed implementacją!

---

## 📝 Konwencje Nazewnictwa

### Klasy
- **Controllers:** `MovieController`, `PersonController` (sufiks: Controller)
- **Models:** `Movie`, `MovieDescription`, `Actor` (PascalCase, singular)
- **Services:** `MovieService`, `AiService` (sufiks: Service)
- **Jobs:** `GenerateMovieJob`, `GenerateActorBioJob` (sufiks: Job)
- **Events:** `MovieGenerationRequested` (czasownik w czasie przeszłym)
- **Listeners:** `QueueMovieGenerationJob` (akcja + obiekt)
- **Requests:** `StoreMovieRequest`, `UpdateMovieRequest` (akcja + obiekt + Request)
- **Resources:** `MovieResource`, `ActorResource` (obiekt + Resource)

### Metody
- **Controllers:** `index()`, `show()`, `store()`, `update()`, `destroy()` (standardowe REST)
- **Services:** `create()`, `find()`, `update()`, `delete()`, `generate()` (akcje biznesowe)
- **Tests:** `test_can_create_movie()` (snake_case, prefiks: test_)

### Pliki
- **Migrations:** `2024_01_01_000000_create_movies_table.php` (timestamp_description)
- **Seeders:** `MovieSeeder`, `ActorSeeder` (obiekt + Seeder)

---

## 🔧 Workflow Przed Commitem

Przed każdym commitem MUSISZ uruchomić:

1. **Laravel Pint** - formatowanie
   ```bash
   cd api && vendor/bin/pint
   ```

2. **PHPStan** - analiza statyczna
   ```bash
   cd api && vendor/bin/phpstan analyse --memory-limit=2G
   ```

3. **PHPUnit** - testy
   ```bash
   cd api && php artisan test
   ```

4. **GitLeaks** - wykrywanie sekretów
   ```bash
   gitleaks protect --source . --verbose --no-banner
   ```

5. **Composer Audit** - audyt bezpieczeństwa
   ```bash
   cd api && composer audit
   ```

---

## 🎯 Zasady Kodowania

### SOLID (stosuj pragmatycznie)
- **SRP** - Jedna klasa = jedna odpowiedzialność
- **DIP** - Zależność od abstrakcji (interfejsy)

### DRY
- Refaktoryzuj duplikację gdy występuje w 3+ miejscach
- Nie przesadzaj z abstrakcją

### Type Safety
- Zawsze używaj `declare(strict_types=1);` w plikach PHP
- Zawsze określaj type hints dla parametrów i return types
- Używaj typów zamiast `mixed` gdzie to możliwe

### Laravel Conventions
- Używaj Eloquent Models zamiast Query Builder gdy możliwe
- Używaj Form Requests dla walidacji
- Używaj API Resources dla odpowiedzi
- Używaj Events + Jobs dla asynchronicznych operacji

---

## 📚 Kluczowe Pliki Dokumentacji

- **Reguły AI:** `.cursor/rules/*.mdc` (reguły w nowym formacie) + `docs/AI_AGENT_CONTEXT_RULES.md` (szczegóły)
- **Zadania:** `docs/issue/TASKS.md` - ⭐ ZACZYNAJ OD TEGO
- **Testy:** `docs/TESTING_STRATEGY.md`
- **Narzędzia:** `docs/CODE_QUALITY_TOOLS.md`
- **Architektura:** `docs/ARCHITECTURE_ANALYSIS.md`
- **Wyjaśnienie Cursor:** `docs/CURSOR_RULES_EXPLANATION.md`

---

## 🚀 API Endpoints

### Główne Endpointy

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/movies` | Lista filmów (z paginacją, filtrowaniem) |
| `GET` | `/api/v1/movies/{id}` | Szczegóły filmu + opis AI |
| `POST` | `/api/v1/generate` | Wyzwól generowanie AI |
| `GET` | `/api/v1/jobs/{id}` | Status zadania generowania |

### Przykłady

```bash
# Pobierz film
GET /api/v1/movies/123

# Wyzwól generowanie
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "entity_id": 123,
  "locale": "pl-PL",
  "context_tag": "modern"
}
```

---

## 🔐 Bezpieczeństwo

### Przed Commitem
- ✅ Sprawdź GitLeaks (zero sekretów)
- ✅ Sprawdź Composer Audit (krytyczne luki)
- ✅ Używaj zmiennych środowiskowych dla kluczy API
- ✅ Nigdy nie commituj `.env` z prawdziwymi wartościami

### Sekrety
- OpenAI API keys: `OPENAI_API_KEY` (zmienna środowiskowa)
- Database passwords: w `.env` (nie w repo)
- Wszystkie sekrety: w `.env` lub zmiennych środowiskowych

---

## 💡 Ważne Uwagi

1. **TDD** - Test przed kodem, zawsze
2. **Narzędzia** - Pint, PHPStan, testy przed commitem
3. **Czytelność** - Kod ma być zrozumiały dla innych
4. **Pragmatyzm** - Zasady są narzędziami, nie celem samym w sobie
5. **Zadania** - Zawsze zaczynaj od `docs/issue/TASKS.md`

---

**Ten plik jest aktualizowany wraz z rozwojem projektu. Sprawdzaj `docs/` dla szczegółowych informacji.**


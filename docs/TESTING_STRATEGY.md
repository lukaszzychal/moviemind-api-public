# Strategia testowania: SQLite vs Produkcja

## Problem: Różnica między środowiskiem testowym a produkcyjnym

### Obecna sytuacja

**Testy:**
- SQLite `:memory:` (w RAM)
- Zero setupu
- Szybkie
- Działają w CI bez dodatkowej konfiguracji

**Produkcja:**
- Prawdopodobnie PostgreSQL lub MySQL
- Inne SQL dialect
- Inne features i ograniczenia
- Różne zachowania

## Czy testy są wiarygodne?

### ✅ **TAK** - dla większości przypadków

**Kiedy SQLite jest wystarczający:**

1. **Logika aplikacji** (nie SQL)
   - Testy jednostkowe
   - Testy funkcjonalne API
   - Business logic
   - Walidacja, routing, controllers

2. **Eloquent ORM**
   - Laravel Eloquent abstrahuje różnice
   - Większość operacji działa tak samo
   - Migracje są kompatybilne

3. **Standardowe operacje CRUD**
   - `create()`, `find()`, `update()`, `delete()`
   - Relationships (hasMany, belongsTo, etc.)
   - Eager loading
   - Query scopes

### ⚠️ **NIE** - dla specyficznych przypadków

**Kiedy SQLite może się różnić:**

1. **Zaawansowane SQL features**
   ```sql
   -- PostgreSQL specific
   SELECT * FROM users WHERE name ILIKE '%test%';  -- Case-insensitive
   SELECT array_agg(id) FROM users;  -- Arrays
   ```

2. **Constraints i walidacja**
   ```sql
   -- PostgreSQL ma więcej typów
   CHECK (email ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$')
   ```

3. **Full-text search**
   ```sql
   -- PostgreSQL
   SELECT * FROM movies WHERE to_tsvector('english', title) @@ to_tsquery('matrix');
   
   -- SQLite ma inne FTS
   SELECT * FROM movies WHERE title MATCH 'matrix';
   ```

4. **JSON operations**
   ```sql
   -- PostgreSQL
   SELECT data->>'key' FROM json_table;
   
   -- SQLite
   SELECT json_extract(data, '$.key') FROM json_table;
   ```

5. **Foreign key enforcement**
   - SQLite: wymaga `PRAGMA foreign_keys = ON`
   - PostgreSQL: zawsze włączone

## Strategia testowania - Piramida testów

```
        /\
       /  \        E2E Tests (PostgreSQL)
      /    \       - Rzadkie, powolne
     /------\      - Testują cały flow
    /        \     - Najbliższe produkcji
   /----------\   
  / Integration \  Integration Tests (PostgreSQL)
 /     Tests      \ - Główne features
/------------------\
   Unit Tests     SQLite :memory:
   - Szybkie       - Większość testów
   - Izolowane     - Logika aplikacji
```

### Poziomy testów

#### 1. **Unit Tests** (80% testów)
- **Środowisko:** SQLite `:memory:`
- **Cel:** Testowanie logiki biznesowej
- **Przykład:** Walidacja, obliczenia, formatowanie
- **Czas:** < 1s na test
- **Wiara:** ✅ Wysoka dla logiki

#### 2. **Feature Tests** (15% testów)
- **Środowisko:** SQLite `:memory:` (obecnie)
- **Cel:** Testowanie endpointów API
- **Przykład:** `POST /api/v1/generate`, `GET /api/v1/movies`
- **Czas:** 1-5s na test
- **Wiara:** ✅ Wysoka dla standardowych operacji

#### 3. **Integration Tests** (4% testów)
- **Środowisko:** PostgreSQL/MySQL (jak produkcja)
- **Cel:** Testowanie specyficznych SQL features
- **Przykład:** Full-text search, JSON queries, advanced constraints
- **Czas:** 5-30s na test
- **Wiara:** ✅ Bardzo wysoka

#### 4. **E2E Tests** (1% testów)
- **Środowisko:** PostgreSQL/MySQL + prawdziwe serwisy
- **Cel:** Testowanie całego flow
- **Przykład:** Full user journey
- **Czas:** Minuty
- **Wiara:** ✅ Najwyższa, ale powolne

## Obecna konfiguracja projektu

### Co jest testowane (SQLite)

✅ **Działa identycznie:**
- Routing i controllers
- Eloquent ORM operations
- Migracje Laravel
- Relationships (hasMany, belongsTo)
- Query builders
- Form requests i walidacja
- Feature flags
- Jobs i queues

### Czego może brakować

❓ **Nie wiemy bez testów na PostgreSQL:**
- Specyficzne SQL queries (jeśli są raw queries)
- Full-text search (jeśli używane)
- JSON operations (jeśli używane)
- Constraints (jeśli są custom)
- Performance na dużych danych

## Rekomendacje

### Dla obecnego projektu

**1. Kontynuuj SQLite dla większości testów** ✅
- Szybkie
- Wystarczające dla większości funkcji
- Działa w CI bez setupu

**2. Dodaj testy integracyjne na PostgreSQL** ⚠️
- Dla krytycznych ścieżek
- Dla specyficznych SQL features
- Raz w nocy / przed release

**3. Monitor różnice**
```php
// Jeśli używasz raw SQL, testuj na obu:
DB::select('SELECT COUNT(*) FROM movies WHERE ...'); // Różne na SQLite vs PostgreSQL?
```

### Implementacja - Dodanie testów na PostgreSQL

#### Opcja 1: Matrix w CI (zalecane)

```yaml
# .github/workflows/ci.yml
jobs:
  test:
    strategy:
      matrix:
        php-versions: ['8.2', '8.3', '8.4']
        database: ['sqlite', 'postgresql']
    services:
      postgres:
        image: postgres:16
        env:
          POSTGRES_PASSWORD: postgres
          POSTGRES_DB: testing
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5
```

#### Opcja 2: Osobny workflow dla PostgreSQL

```yaml
# .github/workflows/integration-tests.yml
on:
  schedule:
    - cron: '0 2 * * *'  # Codziennie o 2 AM
  workflow_dispatch:     # Ręczne uruchomienie
```

## Porównanie: SQLite vs PostgreSQL w testach

| Aspekt | SQLite `:memory:` | PostgreSQL |
|--------|-------------------|------------|
| **Setup** | ✅ Zero | ❌ Wymaga serwera |
| **Szybkość** | ⚡ Błyskawiczna | 🐢 Wolniejsza |
| **Kompatybilność z Eloquent** | ✅ 95% | ✅ 100% |
| **Raw SQL** | ⚠️ Różni się | ✅ Tak jak produkcja |
| **Constraints** | ⚠️ Ograniczone | ✅ Pełne |
| **CI Setup** | ✅ Zero konfiguracji | ⚠️ Wymaga services |
| **Koszt** | ✅ Darmowe | ✅ Darmowe (CI) |

## Kiedy potrzebujesz testów na PostgreSQL?

### ✅ **Potrzebujesz** jeśli:

1. **Używasz raw SQL**
   ```php
   DB::select("SELECT * FROM movies WHERE title ILIKE ?", [$term]);
   ```

2. **Używasz specyficznych features PostgreSQL**
   - Full-text search
   - JSON operators (`->`, `->>`)
   - Arrays
   - Custom types

3. **Masz złożone constraints**
   ```php
   Schema::table('movies', function (Blueprint $table) {
       $table->check('release_year > 1880');
   });
   ```

4. **Performance jest krytyczny**
   - Testy load/performance
   - Query optimization

### ❌ **Nie potrzebujesz** jeśli:

1. **Używasz tylko Eloquent**
   - Większość operacji działa tak samo

2. **Standardowe CRUD**
   - Create, Read, Update, Delete
   - Relationships

3. **Logika aplikacji**
   - Controllers, Services, Validators

## Praktyczne podejście

### Faza 1: Obecna (SQLite tylko)
- ✅ 80% pokrycia
- ✅ Szybkie testy
- ✅ Działa w CI

### Faza 2: Dodaj PostgreSQL dla krytycznych (opcjonalnie)
- ✅ Testy integration na PostgreSQL
- ✅ Uruchamiane rzadziej (co noc / przed release)
- ✅ Pokrywają edge cases

### Faza 3: Monitoring produkcji
- ✅ Logi błędów SQL
- ✅ Różnice w behavior
- ✅ Dodaj testy gdy znajdziesz różnice

## Wnioski

**Obecne testy SQLite są wiarygodne dla:**
- ✅ Logiki aplikacji
- ✅ Standardowych operacji Eloquent
- ✅ API endpoints
- ✅ Walidacji i routing

**Ale nie pokrywają:**
- ⚠️ Specyficznych SQL features
- ⚠️ Raw queries (jeśli różnią się między DB)
- ⚠️ Zaawansowanych constraints

**Rekomendacja:**
1. **Kontynuuj** SQLite dla większości testów (szybkie, wystarczające)
2. **Dodaj** testy PostgreSQL dla krytycznych ścieżek (jeśli masz specyficzne SQL)
3. **Monitoruj** różnice w produkcji i dodawaj testy ad-hoc

**Dla twojego projektu:** Obecne testy SQLite są prawdopodobnie **wystarczające**, bo używacie głównie Eloquent, nie raw SQL. 


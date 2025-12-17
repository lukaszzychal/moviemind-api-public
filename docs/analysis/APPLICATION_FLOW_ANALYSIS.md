# 📊 Analiza Flow Aplikacji MovieMind API

**Data:** 2025-12-17  
**Status:** 🔄 Analiza i Planowanie

---

## 🎯 Cel Analizy

Przeanalizować obecny flow aplikacji i zaproponować optymalizacje dla:
1. Format endpointów i obsługa niejednoznaczności
2. Flow generowania danych (2a, 2b, 3)
3. Weryfikacja w TMDb/IMDb i obsługa niejednoznaczności
4. Zapisywanie surowych danych z zewnętrznych źródeł

---

## 📋 Obecny Flow Aplikacji

### 1. **Klient żąda danych o filmie/osobie**

#### Endpointy:
- `GET /api/v1/movies/{slug}` - Pobranie filmu
- `GET /api/v1/people/{slug}` - Pobranie osoby
- `GET /api/v1/movies?q=query` - Wyszukiwanie filmów
- `GET /api/v1/people?q=query` - Wyszukiwanie osób

#### Format Slug:
```
Format: {title-slug}-{year}[-{director-slug}]
Przykłady:
- "the-matrix-1999"
- "bad-boys-1995"
- "the-prestige-2006-christopher-nolan" (jeśli duplikat)
- "heat-1995-2" (jeśli duplikat i brak reżysera)
```

**✅ Zalety obecnego formatu:**
- Czytelny i przewidywalny
- Automatyczne rozwiązywanie duplikatów
- Zawiera kluczowe informacje (tytuł, rok, opcjonalnie reżyser)

**⚠️ Problemy:**
- Nie obsługuje seriali/programów (brak typu entity)
- Nie obsługuje sezonów/odcinków
- Dla osób: format `{name-slug}-{birth-year}[-{birthplace-slug}]` - może być niejednoznaczny

---

### 2a. **Dane istnieją w bazie**

#### Flow:
```
GET /api/v1/movies/{slug}
  ↓
Cache check (TTL: 3600s)
  ↓
Database lookup (findBySlugWithRelations)
  ↓
If found:
  - Return MovieResource with descriptions
  - Cache response
  - Add HATEOAS links
  - Add disambiguation metadata (if ambiguous)
```

**✅ Działa dobrze:**
- Szybki response (cache + database)
- Obsługa wyboru konkretnego opisu (`?description_id=X`)
- Metadata dla niejednoznacznych slugów

**⚠️ Problemy:**
- Brak informacji o źródle danych (TMDb ID, data pobrania)
- Brak możliwości odświeżenia danych z TMDb

---

### 2b. **Dane nie istnieją - rozpoczyna się generowanie**

#### Flow dla Filmów:
```
GET /api/v1/movies/{slug}
  ↓
Slug validation (SlugValidator)
  ↓
Feature flag check (ai_description_generation)
  ↓
TMDb verification (verifyMovie):
  - If found → queue job with TMDb data
  - If not found:
    - If feature flag OFF → allow generation without TMDb
    - If feature flag ON:
      - searchMovies() → if >1 result → disambiguation (300)
      - If 0 results → 404
  ↓
Queue job (RealGenerateMovieJob)
  ↓
Return 202 Accepted with job_id
```

#### Flow dla Osób:
```
GET /api/v1/people/{slug}
  ↓
Slug validation
  ↓
Feature flag check (ai_bio_generation)
  ↓
TMDb verification (verifyPerson):
  - If found → queue job with TMDb data
  - If not found:
    - If feature flag OFF → allow generation without TMDb
    - If feature flag ON → 404 (BRAK DISAMBIGUATION!)
  ↓
Queue job (RealGeneratePersonJob)
  ↓
Return 202 Accepted with job_id
```

**✅ Działa dobrze:**
- Asynchroniczne przetwarzanie
- Weryfikacja przed generowaniem (zapobiega halucynacjom)
- Disambiguation dla filmów

**❌ Problemy:**
1. **PersonController nie ma disambiguation** - jeśli jest wiele osób o tym samym imieniu, zwraca 404
2. **Sugerowane slugi (TASK-051) są w jobie, ale nie w controllerze** - jeśli AI zwróci "not found", job zwraca suggested_slugs, ale controller już zwrócił 202
3. **Brak obsługi seriali/programów** - tylko filmy i osoby

---

### 3. **Proces generowania - weryfikacja i tworzenie danych**

#### Flow w Jobie (RealGenerateMovieJob):
```
RealGenerateMovieJob::handle()
  ↓
Check if movie exists (refresh if exists)
  ↓
createMovieRecord():
  1. Call AI API (OpenAiClient::generateMovie)
     - Input: slug + TMDb data (if available)
     - Output: AI-generated movie data
  2. If AI returns "not found":
     - If TMDb data available → use TMDb as fallback
     - If no TMDb data → findSuggestedSlugs() → return error with suggestions
  3. Validate AI response (validateAiResponse)
  4. Create Movie + MovieDescription
  5. Return movie data
  ↓
Update cache (DONE/FAILED)
```

**✅ Działa dobrze:**
- Walidacja danych AI
- Fallback do TMDb jeśli AI nie znajdzie
- Sugerowane slugi w odpowiedzi błędu (TASK-051)

**❌ Problemy:**
1. **Brak zapisywania surowych danych TMDb** - dane są tylko przekazywane, nie zapisywane
2. **Brak obsługi IMDb** - tylko TMDb
3. **Brak możliwości odświeżenia danych** - jeśli TMDb ma nowsze dane, nie są pobierane
4. **TMDb data nie jest weryfikowana w jobie** - jeśli controller przekazał TMDb data, job ufa im bez weryfikacji

---

## 🔍 Zidentyfikowane Problemy

### 🔴 Krytyczne

1. **PersonController brak disambiguation**
   - Jeśli jest wiele osób o tym samym imieniu, system zwraca 404
   - Powinien działać jak MovieController (300 Multiple Choices)

2. **Sugerowane slugi nie są wykorzystywane w controllerze**
   - TASK-051 dodał suggested_slugs w jobie, ale controller już zwrócił 202
   - Klient nie widzi sugestii, dopóki nie sprawdzi statusu joba

3. **Brak zapisywania surowych danych TMDb**
   - Dane są tylko przekazywane do joba, nie zapisywane
   - Brak możliwości odświeżenia danych z TMDb
   - Brak historii zmian danych

### 🟡 Średnie

4. **Brak obsługi seriali/programów**
   - System obsługuje tylko filmy i osoby
   - README mówi o "series", ale nie ma implementacji

5. **Brak obsługi IMDb**
   - Tylko TMDb jest używane
   - Może być przydatne jako fallback lub dodatkowe źródło

6. **Brak możliwości odświeżenia danych**
   - Jeśli TMDb ma nowsze dane, nie są pobierane
   - Brak mechanizmu re-sync z TMDb

7. **Niejednoznaczność w jobie vs controllerze**
   - Controller sprawdza disambiguation przed jobem
   - Job też może znaleźć niejednoznaczność (suggested_slugs)
   - Dwa różne mechanizmy dla tego samego problemu

---

## 💡 Propozycje Rozwiązań

### 1. **Format Endpointów i Obsługa Niejednoznaczności**

#### Opcja A: Zwracać wiele wyników (Rekomendowane)
```json
GET /api/v1/movies?q=matrix
Response: 200 OK
{
  "data": [
    {
      "id": 1,
      "slug": "the-matrix-1999",
      "title": "The Matrix",
      "release_year": 1999,
      ...
    },
    {
      "id": 2,
      "slug": "matrix-reloaded-2003",
      "title": "The Matrix Reloaded",
      "release_year": 2003,
      ...
    }
  ],
  "count": 2
}
```

**✅ Zalety:**
- Proste i intuicyjne
- Klient wybiera właściwy wynik
- Spójne z endpointem `?q=query`

**❌ Wady:**
- Wymaga zmiany w API (może być breaking change)
- Klient musi przetworzyć wiele wyników

#### Opcja B: Disambiguation z 300 Multiple Choices (Obecne)
```json
GET /api/v1/movies/matrix
Response: 300 Multiple Choices
{
  "error": "Multiple movies found",
  "message": "Multiple movies match this slug. Please select one:",
  "slug": "matrix",
  "options": [
    {
      "tmdb_id": 603,
      "title": "The Matrix",
      "release_year": 1999,
      "director": "The Wachowskis",
      "select_url": "/api/v1/movies/matrix?tmdb_id=603"
    },
    ...
  ]
}
```

**✅ Zalety:**
- Już zaimplementowane dla filmów
- Standardowy kod HTTP (300)
- Klient wybiera przez `?tmdb_id=X`

**❌ Wady:**
- Wymaga dodatkowego requestu
- Nie jest standardowe dla REST API

#### Opcja C: Sugerowane slugi w odpowiedzi (TASK-051)
```json
GET /api/v1/movies/matrix-2003
Response: 202 Accepted
{
  "job_id": "...",
  "status": "PENDING",
  "suggested_slugs": [
    {
      "slug": "the-matrix-reloaded-2003",
      "title": "The Matrix Reloaded",
      "release_year": 2003,
      "director": "The Wachowskis"
    },
    ...
  ]
}
```

**✅ Zalety:**
- Informacja dostępna od razu
- Nie wymaga dodatkowego requestu
- Działa z asynchronicznym generowaniem

**❌ Wady:**
- Wymaga sprawdzenia statusu joba
- Sugerowane slugi mogą być dostępne dopiero po przetworzeniu joba

**🎯 Rekomendacja: Opcja A + Opcja C**
- Endpoint `?q=query` zwraca wiele wyników (Opcja A)
- Endpoint `/{slug}` z niejednoznacznością zwraca suggested_slugs w odpowiedzi (Opcja C)
- Disambiguation (Opcja B) jako fallback dla edge cases

---

### 2. **Zapisywanie Surowych Danych TMDb**

#### Propozycja: Tabela `tmdb_snapshots`
```sql
CREATE TABLE tmdb_snapshots (
    id BIGSERIAL PRIMARY KEY,
    entity_type VARCHAR(20) NOT NULL, -- 'MOVIE', 'PERSON', 'TV_SERIES'
    entity_id BIGINT NOT NULL, -- FK to movies/people/etc
    tmdb_id INTEGER NOT NULL,
    tmdb_type VARCHAR(20) NOT NULL, -- 'movie', 'person', 'tv'
    raw_data JSONB NOT NULL, -- Full TMDb response
    fetched_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    
    UNIQUE(entity_type, entity_id, tmdb_id)
);

CREATE INDEX idx_tmdb_snapshots_entity ON tmdb_snapshots(entity_type, entity_id);
CREATE INDEX idx_tmdb_snapshots_tmdb_id ON tmdb_snapshots(tmdb_id);
```

**✅ Zalety:**
- Historia zmian danych
- Możliwość odświeżenia danych
- Debugging i analiza
- Możliwość porównania danych AI vs TMDb

**❌ Wady:**
- Dodatkowe miejsce w bazie
- Wymaga migracji

**🎯 Rekomendacja: Zaimplementować**
- Zapisywać snapshot przy każdym pobraniu danych z TMDb
- Dodać endpoint do odświeżenia danych (`POST /api/v1/movies/{slug}/refresh`)

---

### 3. **Obsługa Niejednoznaczności w PersonController**

#### Propozycja: Dodać disambiguation jak w MovieController
```php
// PersonController::show()
if (! $tmdbData) {
    if (! Feature::active('tmdb_verification')) {
        // Allow generation without TMDb
    } else {
        // Check for disambiguation
        $searchResults = $this->tmdbVerificationService->searchPeople($slug, 5);
        if (count($searchResults) > 1) {
            return $this->respondWithDisambiguation($slug, $searchResults);
        }
        return response()->json(['error' => 'Person not found'], 404);
    }
}
```

**🎯 Rekomendacja: Zaimplementować**
- Skopiować logikę z MovieController
- Dodać `respondWithDisambiguation()` dla osób
- Dodać `handleDisambiguationSelection()` dla osób

---

### 4. **Obsługa Seriali/Programów**

#### Propozycja: Dodać typy entity
```php
enum EntityType: string {
    case MOVIE = 'MOVIE';
    case TV_SERIES = 'TV_SERIES';
    case TV_EPISODE = 'TV_EPISODE';
    case PERSON = 'PERSON';
}
```

**Struktura:**
```sql
CREATE TABLE tv_series (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    first_air_date DATE,
    last_air_date DATE,
    number_of_seasons INTEGER,
    number_of_episodes INTEGER,
    ...
);

CREATE TABLE tv_episodes (
    id BIGSERIAL PRIMARY KEY,
    tv_series_id BIGINT NOT NULL,
    season_number INTEGER NOT NULL,
    episode_number INTEGER NOT NULL,
    title VARCHAR(255),
    air_date DATE,
    ...
);
```

**🎯 Rekomendacja: Faza 1 - Analiza, Faza 2 - Implementacja**
- Najpierw przeanalizować wymagania
- Sprawdzić czy TMDb API obsługuje seriale
- Zaimplementować w osobnej fazie (nie blokuje MVP)

---

### 5. **Obsługa IMDb**

#### Propozycja: Multi-source verification
```php
interface EntityVerificationServiceInterface {
    public function verifyMovie(string $slug): ?array;
    public function searchMovies(string $slug, int $limit = 5): array;
    
    // Nowe metody
    public function verifyMovieInImdb(string $slug): ?array;
    public function searchMoviesInImdb(string $slug, int $limit = 5): array;
}
```

**Strategia:**
1. Najpierw TMDb (główne źródło)
2. Jeśli TMDb nie znajdzie → IMDb (fallback)
3. Jeśli oba nie znajdą → zwróć suggested_slugs z obu źródeł

**🎯 Rekomendacja: Faza 2 - Po MVP**
- TMDb jest wystarczające dla MVP
- IMDb jako dodatkowe źródło w przyszłości

---

### 6. **Odświeżenie Danych z TMDb**

#### Propozycja: Endpoint do odświeżenia
```php
POST /api/v1/movies/{slug}/refresh
POST /api/v1/people/{slug}/refresh
```

**Flow:**
1. Pobierz najnowsze dane z TMDb
2. Zapisz snapshot
3. Porównaj z obecnymi danymi
4. Jeśli różnice → zaktualizuj dane + wygeneruj nowy opis (opcjonalnie)

**🎯 Rekomendacja: Zaimplementować**
- Przydatne dla utrzymania aktualności danych
- Można dodać automatyczne odświeżenie (cron job)

---

## 📝 Plan Implementacji

### Faza 1: Krytyczne Naprawy (🔴 Wysoki Priorytet)

1. **TASK-052: Disambiguation dla PersonController**
   - Skopiować logikę z MovieController
   - Dodać `respondWithDisambiguation()` dla osób
   - Dodać `handleDisambiguationSelection()` dla osób
   - **Czas:** 2-3h
   - **Priorytet:** 🔴 Wysoki

2. **TASK-053: Wykorzystanie suggested_slugs w controllerze**
   - Jeśli `searchMovies()` zwraca wyniki, ale `verifyMovie()` nie → zwróć suggested_slugs w odpowiedzi 202
   - Zintegrować z TASK-051
   - **Czas:** 3-4h
   - **Priorytet:** 🔴 Wysoki

### Faza 2: Zapisywanie Danych TMDb (🟡 Średni Priorytet)

3. **TASK-054: Tabela tmdb_snapshots**
   - Migracja do utworzenia tabeli
   - Model `TmdbSnapshot`
   - **Czas:** 2-3h
   - **Priorytet:** 🟡 Średni

4. **TASK-055: Zapisywanie snapshotów TMDb**
   - Modyfikacja `TmdbVerificationService` do zapisywania snapshotów
   - Zapisywać przy każdym pobraniu danych
   - **Czas:** 3-4h
   - **Priorytet:** 🟡 Średni

5. **TASK-056: Endpoint do odświeżenia danych**
   - `POST /api/v1/movies/{slug}/refresh`
   - `POST /api/v1/people/{slug}/refresh`
   - Pobierz najnowsze dane z TMDb i zaktualizuj
   - **Czas:** 4-5h
   - **Priorytet:** 🟡 Średni

### Faza 3: Rozszerzenia (🟢 Niski Priorytet)

6. **TASK-057: Obsługa seriali/programów**
   - Analiza wymagań
   - Implementacja tabel i modeli
   - Endpointy API
   - **Czas:** 20-30h
   - **Priorytet:** 🟢 Niski (po MVP)

7. **TASK-058: Obsługa IMDb**
   - Integracja z IMDb API
   - Multi-source verification
   - **Czas:** 15-20h
   - **Priorytet:** 🟢 Niski (po MVP)

---

## ❓ Pytania do Użytkownika

1. **Format endpointów:**
   - Czy preferujesz zwracać wiele wyników w `GET /api/v1/movies/{slug}` gdy jest niejednoznaczność?
   - Czy obecny format z disambiguation (300 Multiple Choices) jest OK?
   - Czy sugerowane slugi w odpowiedzi 202 są wystarczające?

2. **Zapisywanie danych TMDb:**
   - Czy zapisywać surowe dane TMDb lokalnie?
   - Czy potrzebujesz historii zmian danych?
   - Czy automatyczne odświeżenie danych (np. raz w tygodniu)?

3. **Seriale/Programy:**
   - Czy są priorytetem dla MVP?
   - Jakie informacje powinny zawierać (sezony, odcinki, etc.)?
   - Czy format slug powinien być inny dla seriali?

4. **IMDb:**
   - Czy IMDb jest potrzebne dla MVP?
   - Czy jako główne źródło czy fallback?

5. **Odświeżenie danych:**
   - Czy endpoint do ręcznego odświeżenia jest wystarczający?
   - Czy automatyczne odświeżenie (cron job)?

---

## 📊 Podsumowanie

### Obecny Stan:
- ✅ Działa dobrze dla filmów z disambiguation
- ✅ Asynchroniczne generowanie
- ✅ Weryfikacja przed generowaniem
- ❌ Brak disambiguation dla osób
- ❌ Brak zapisywania danych TMDb
- ❌ Brak obsługi seriali

### Rekomendowane Działania:
1. **Natychmiast:** Naprawić disambiguation dla osób (TASK-052)
2. **Krótki termin:** Wykorzystać suggested_slugs w controllerze (TASK-053)
3. **Średni termin:** Zapisywanie snapshotów TMDb (TASK-054, TASK-055, TASK-056)
4. **Długi termin:** Seriale i IMDb (TASK-057, TASK-058)

---

**Następne kroki:** Oczekiwanie na odpowiedzi użytkownika na pytania powyżej, następnie implementacja zgodnie z priorytetami.


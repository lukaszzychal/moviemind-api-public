# Plan Ulepszeń i Dodatkowych Funkcjonalności dla Movie Endpoints

**Data utworzenia:** 2025-01-XX  
**Status:** DRAFT - Plan (nie implementacja)  
**Cel:** Analiza możliwych ulepszeń i dodatkowych funkcjonalności dla Movie endpoints

---

## 📋 Spis Treści

1. [Obecny Stan](#obecny-stan)
2. [Zidentyfikowane Braki i Możliwości Ulepszenia](#zidentyfikowane-braki-i-możliwości-ulepszenia)
3. [Propozycje Ulepszeń](#propozycje-ulepszeń)
4. [Pytania do Rozstrzygnięcia](#pytania-do-rozstrzygnięcia)
5. [Plan Implementacji](#plan-implementacji)

---

## Obecny Stan

### Obecne Endpointy Movie

```
GET  /api/v1/movies              # Lista filmów (proste wyszukiwanie ?q=)
GET  /api/v1/movies/search       # Zaawansowane wyszukiwanie (filtry, paginacja)
GET  /api/v1/movies/{slug}       # Szczegóły filmu
GET  /api/v1/movies/{slug}/related  # Powiązane filmy (sequels, prequels, similar)
POST /api/v1/movies/{slug}/refresh  # Odświeżenie danych z TMDb
POST /api/v1/movies/{slug}/report   # Zgłaszanie błędów w opisach
```

### Obecne Funkcjonalności

- ✅ Zaawansowane wyszukiwanie (local + external, cache, paginacja)
- ✅ Filtry wyszukiwania: `?q=`, `?year=`, `?director=`, `?actor=`
- ✅ Related movies (sequels, prequels, series, similar)
- ✅ Reports (zgłaszanie błędów, weryfikacja, regeneracja)
- ✅ Rate limiting (search, report, generate)
- ✅ Cache'owanie wyników
- ✅ Disambiguation
- ✅ Refresh danych z TMDb

---

## Zidentyfikowane Braki i Możliwości Ulepszenia

### 🔴 Krytyczne (consistency, funkcjonalność)

1. **Brak rate limiting dla `GET /movies/{slug}`**
   - Endpoint `show()` nie ma rate limiting (inconsistency z Person planem)
   - Person plan zakłada `adaptive.rate.limit:show` dla consistency

2. **Brak wersjonowania opisów (Description Versioning)**
   - Gdy opis jest regenerowany po raporcie (`RegenerateMovieDescriptionJob`), stary opis jest **tracony** (update zamiast insert)
   - Brak historii zmian opisów
   - Brak możliwości przywrócenia poprzedniej wersji
   - Potencjalny problem: jeśli nowy opis jest gorszy, nie można wrócić do starego

### 🟡 Ważne (UX, performance)

3. **Brak sortowania w wyszukiwaniu**
   - Wyniki wyszukiwania nie mogą być sortowane (np. po roku, tytule, dacie dodania)
   - Wspomniane w planie Person jako opcjonalne

4. **Brak bulk operations**
   - Nie można pobrać wielu filmów naraz (np. lista slugów)
   - Wymaga wielu requestów: `GET /movies/slug1`, `GET /movies/slug2`, etc.
   - Może być przydatne dla klientów, którzy potrzebują wielu filmów

5. **Brak endpointu Collections**
   - Synchronizacja kolekcji z TMDb istnieje (`SyncMovieRelationshipsJob`)
   - Brak endpointu do przeglądania kolekcji (np. `GET /movies/collections/{collection_id}`)
   - Brak listy wszystkich kolekcji

### 🟢 Mniejsze (nice to have)

6. **Brak filtrów po gatunkach w Related**
   - Wspomniane w planie Person jako opcjonalne
   - `GET /movies/{slug}/related?genre=science-fiction`

7. **Brak limitu per source w search**
   - Wspomniane w planie Person jako opcjonalne
   - `?local_limit=20&external_limit=10`

8. **Brak endpointu do porównywania filmów**
   - `GET /movies/compare?slug1=x&slug2=y` - porównanie dwóch filmów
   - Może być przydatne dla analityków, ale może być nisza

---

## Propozycje Ulepszeń

### 1. Rate Limiting dla `GET /movies/{slug}`

**Cel:** Consistency z Person planem i ochrona przed nadmiernym obciążeniem.

**Implementacja:**
- Dodanie middleware `adaptive.rate.limit:show` do route `GET /movies/{slug}`
- Konfiguracja w `api/config/rate-limiting.php`:
  ```php
  'defaults' => [
      'search' => 60,
      'show' => 120, // Wyższy limit niż search (prostsze zapytanie)
      'generate' => 10,
      'report' => 20,
  ],
  ```

**Pliki do modyfikacji:**
- `api/routes/api.php` - dodanie middleware
- `api/config/rate-limiting.php` - dodanie konfiguracji dla `show`

**Testy:**
- `api/tests/Feature/MovieRateLimitingTest.php` - rozszerzenie o test dla `show()`

**Priorytet:** Wysoki (consistency)

---

### 2. Wersjonowanie Opisów (Description Versioning)

**Cel:** Zachowanie historii zmian opisów, możliwość przywrócenia poprzedniej wersji.

**Problem:**
Obecnie `RegenerateMovieDescriptionJob` wykonuje `update()` na istniejącym opisie, co powoduje utratę starej wersji:

```php
// Obecnie (RegenerateMovieDescriptionJob):
$description->update([
    'text' => $validation['sanitized'],
    'ai_model' => $result['model'] ?? 'gpt-4o-mini',
]);
```

**Opcje implementacji:**

#### Opcja A: Soft Delete + Insert Nowego Opisu

- Oznaczenie starego opisu jako "archived" (soft delete)
- Utworzenie nowego opisu z tym samym `(movie_id, locale, context_tag)`
- Aktualizacja `default_description_id` do nowego opisu

**Zalety:**
- Prosta implementacja
- Zachowuje historię w tej samej tabeli
- Łatwe do zapytania (`where('deleted_at', null)`)

**Wady:**
- Unique constraint `(movie_id, locale, context_tag)` może być problemem (trzeba zmienić na partial unique index)
- Mieszanie aktywnych i archiwalnych rekordów w jednej tabeli

#### Opcja B: Osobna Tabela `movie_description_versions`

- Utworzenie tabeli `movie_description_versions` z historią
- `movie_descriptions` zawiera tylko aktualne wersje
- Przed update: kopiuj stary opis do `movie_description_versions`
- Po update: zaktualizuj `movie_descriptions`

**Zalety:**
- Czysta separacja (aktywne vs historia)
- Unique constraint pozostaje bez zmian
- Łatwe do zapytania (nie trzeba filtrować `deleted_at`)

**Wady:**
- Więcej złożoności (dwie tabele)
- Więcej miejsca w bazie danych

#### Opcja C: Pole `version_number` + Soft Delete

- Dodanie pola `version_number` do `movie_descriptions`
- Przed update: zwiększ `version_number` starego opisu, oznacz jako archived
- Utworzenie nowego opisu z `version_number = 1`

**Zalety:**
- Historia w jednej tabeli (jak Opcja A)
- `version_number` ułatwia sortowanie wersji
- Można łatwo znaleźć najnowszą wersję (`max(version_number)`)

**Wady:**
- Unique constraint wymaga zmiany (dodać `version_number` lub użyć partial index)

**Rekomendacja:** Opcja C (pole `version_number` + soft delete) - najlepszy balans między prostotą a funkcjonalnością.

**Implementacja (Opcja C):**

**Migration:**
```php
Schema::table('movie_descriptions', function (Blueprint $table) {
    $table->integer('version_number')->default(1)->after('ai_model');
    $table->timestamp('archived_at')->nullable()->after('updated_at');
    $table->index(['movie_id', 'locale', 'context_tag', 'version_number']);
    // Zmiana unique constraint na partial unique index (tylko dla nie-archived)
});
```

**Modyfikacje:**
- `api/app/Models/MovieDescription.php` - dodanie `version_number`, `archived_at`
- `api/app/Jobs/RegenerateMovieDescriptionJob.php` - zmiana logiki:
  1. Znajdź aktualny opis
  2. Oznacz jako archived: `$description->update(['archived_at' => now()])`
  3. Utwórz nowy opis z `version_number = 1` (lub `max(version_number) + 1` dla tego samego `(movie_id, locale, context_tag)`)
  4. Zaktualizuj `default_description_id` jeśli potrzeba

**Nowy endpoint (opcjonalny):**
- `GET /api/v1/movies/{slug}/descriptions/{description_id}/versions` - historia wersji opisu

**Pliki do utworzenia:**
- Migration: `XXXX_XX_XX_add_versioning_to_movie_descriptions.php`
- `api/tests/Unit/Jobs/RegenerateMovieDescriptionJobVersioningTest.php`
- `api/tests/Feature/MovieDescriptionVersioningTest.php`

**Modyfikacje:**
- `api/app/Models/MovieDescription.php`
- `api/app/Jobs/RegenerateMovieDescriptionJob.php`
- `api/database/migrations/2025_10_30_000110_create_movie_descriptions_table.php` - zmiana unique constraint

**Priorytet:** Średni/Wysoki (zachowanie danych, możliwość rollback)

---

### 3. Sortowanie w Wyszukiwaniu

**Cel:** Umożliwienie sortowania wyników wyszukiwania.

**Parametry:**
- `?sort=title|release_year|created_at` (default: relevance/confidence)
- `?order=asc|desc` (default: `asc` dla `title`, `desc` dla `release_year` i `created_at`)

**Implementacja:**
- Rozszerzenie `MovieSearchService::search()` o sortowanie
- Sortowanie lokalnych wyników (przed merge z external)
- Sortowanie external wyników (jeśli możliwe)
- Merge zachowuje sortowanie (lokalne pierwsze, potem external, lub według sortowania)

**Ograniczenia:**
- External results (TMDb) mogą nie obsługiwać sortowania - wtedy sortować tylko lokalne
- Merge może wymagać re-sortowania po scaleniu

**Pliki do modyfikacji:**
- `api/app/Services/MovieSearchService.php` - dodanie logiki sortowania
- `api/app/Http/Requests/SearchMovieRequest.php` - dodanie walidacji `sort` i `order`
- `api/tests/Unit/Services/MovieSearchServiceSortingTest.php`
- `api/tests/Feature/MovieSearchSortingTest.php`

**Priorytet:** Średni (UX improvement)

---

### 4. Bulk Operations (Pobieranie wielu filmów naraz)

**Cel:** Umożliwienie pobrania wielu filmów w jednym requestcie.

**Endpoint:**
```
POST /api/v1/movies/bulk
Content-Type: application/json
{
  "slugs": ["the-matrix-1999", "inception-2010", "interstellar-2014"],
  "include": ["descriptions", "people", "genres"] // opcjonalne
}
```

**Response:**
```json
{
  "data": [
    { /* movie 1 */ },
    { /* movie 2 */ },
    { /* movie 3 */ }
  ],
  "not_found": ["non-existent-slug"],
  "count": 3,
  "requested_count": 3
}
```

**Ograniczenia:**
- Limit slugów na request (np. max 50)
- Rate limiting (może być osobny limit dla bulk)
- Cache'owanie (może być trudne dla wielu slugów)

**Implementacja:**
- Nowa metoda `MovieController::bulk()`
- Request validator `BulkMoviesRequest`
- Service `MovieBulkService` (opcjonalnie, jeśli logika jest złożona)
- Użycie `MovieRepository::findBySlugs()` (nowa metoda)

**Pliki do utworzenia:**
- `api/app/Http/Requests/BulkMoviesRequest.php`
- `api/app/Http/Controllers/Api/MovieController.php` - metoda `bulk()`
- `api/tests/Feature/MovieBulkTest.php`

**Modyfikacje:**
- `api/routes/api.php` - dodanie route
- `api/app/Repositories/MovieRepository.php` - metoda `findBySlugs(array $slugs)`
- `api/config/rate-limiting.php` - opcjonalnie osobny limit dla bulk

**Priorytet:** Średni (UX improvement, może być przydatne)

---

### 5. Collections Endpoint

**Cel:** Umożliwienie przeglądania kolekcji filmów (np. "The Matrix Collection", "Marvel Cinematic Universe").

**Tło:**
- Synchronizacja kolekcji z TMDb już istnieje (`SyncMovieRelationshipsJob`)
- Relacje między filmami są przechowywane w `movie_relationships` z `relationship_type=SERIES`
- Ale brak endpointu do przeglądania kolekcji jako całości

**Opcje:**

#### Opcja A: Endpoint przez Movie Relationship

- Użycie istniejących relacji `SERIES` w `movie_relationships`
- Grupowanie filmów po kolekcji (np. przez TMDb collection_id w snapshot)

**Problemy:**
- `movie_relationships` nie przechowuje informacji o kolekcji (tylko relacje między filmami)
- Trudno określić, które filmy należą do tej samej kolekcji

#### Opcja B: Nowa Tabela `collections`

- Utworzenie tabeli `collections` z informacjami o kolekcjach
- Tabela pivot `collection_movie` (many-to-many)
- Endpoint: `GET /api/v1/collections/{collection_slug}`

**Zalety:**
- Czysta struktura danych
- Łatwe do zapytania
- Można dodać metadata kolekcji (nazwa, opis, etc.)

**Wady:**
- Wymaga nowej tabeli i synchronizacji

#### Opcja C: Endpoint przez TMDb Snapshot

- Użycie `tmdb_snapshots.raw_data->belongs_to_collection` do grupowania
- Endpoint: `GET /api/v1/movies/{slug}/collection` - zwraca filmy z tej samej kolekcji

**Zalety:**
- Używa istniejących danych (nie wymaga nowej tabeli)
- Prostsze do implementacji

**Wady:**
- Zależy od TMDb snapshot (jeśli snapshot nie istnieje, kolekcja nie jest dostępna)
- Trudniejsze zapytania (JSON w PostgreSQL)

**Rekomendacja:** Opcja C (przez TMDb Snapshot) dla MVP, Opcja B (nowa tabela) jeśli potrzeba więcej funkcjonalności.

**Implementacja (Opcja C):**

**Endpoint:**
```
GET /api/v1/movies/{slug}/collection
```

**Response:**
```json
{
  "collection": {
    "name": "The Matrix Collection",
    "tmdb_collection_id": 234,
    "count": 4
  },
  "movies": [
    { /* movie 1 */ },
    { /* movie 2 */ },
    { /* movie 3 */ },
    { /* movie 4 */ }
  ]
}
```

**Implementacja:**
- Nowa metoda `MovieController::collection(string $slug)`
- Service `MovieCollectionService` (opcjonalnie)
- Query: Znajdź collection_id z snapshot, znajdź wszystkie filmy z tym samym collection_id

**Pliki do utworzenia:**
- `api/app/Services/MovieCollectionService.php` (opcjonalnie)
- `api/tests/Feature/MovieCollectionTest.php`

**Modyfikacje:**
- `api/app/Http/Controllers/Api/MovieController.php` - metoda `collection()`
- `api/routes/api.php` - dodanie route

**Priorytet:** Średni/Niski (nice to have)

---

### 6. Filtry po Gatunkach w Related

**Cel:** Filtrowanie powiązanych filmów po gatunku.

**Parametry:**
- `?genre=slug` - gatunek (np. `science-fiction`)
- `?genres[]=slug1&genres[]=slug2` - wiele gatunków (AND logic)

**Implementacja:**
- Rozszerzenie `MovieController::related()` o filtrowanie po `genres`
- Użycie `whereHas('genres', function($q) { $q->where('slug', $genre); })`

**Pliki do modyfikacji:**
- `api/app/Http/Controllers/Api/MovieController.php` - metoda `related()`
- `api/tests/Feature/MovieRelatedFilteringTest.php`

**Priorytet:** Niski (nice to have)

---

### 7. Limit per Source w Search

**Cel:** Kontrola liczby wyników z każdego źródła (local vs external).

**Parametry:**
- `?local_limit=20` - limit wyników lokalnych (default: `per_page`)
- `?external_limit=10` - limit wyników external (default: `per_page`)

**Implementacja:**
- Rozszerzenie `MovieSearchService::search()` o osobne limity
- Przekazanie limitów do `searchLocal()` i `searchTmdbIfEnabled()`

**Pliki do modyfikacji:**
- `api/app/Services/MovieSearchService.php`
- `api/app/Http/Requests/SearchMovieRequest.php`
- `api/tests/Unit/Services/MovieSearchServiceLimitTest.php`

**Priorytet:** Niski (nice to have)

---

### 8. Porównywanie Filmów (Compare Endpoint)

**Cel:** Porównanie dwóch filmów (wspólne elementy, różnice).

**Endpoint:**
```
GET /api/v1/movies/compare?slug1=the-matrix-1999&slug2=inception-2010
```

**Response:**
```json
{
  "movie1": { /* movie 1 */ },
  "movie2": { /* movie 2 */ },
  "comparison": {
    "common_genres": ["Science Fiction", "Action"],
    "common_people": [
      { "person": {...}, "roles_in_movie1": ["ACTOR"], "roles_in_movie2": ["DIRECTOR"] }
    ],
    "year_difference": 11,
    "similarity_score": 0.75
  }
}
```

**Implementacja:**
- Nowa metoda `MovieController::compare()`
- Service `MovieComparisonService`
- Request validator `CompareMoviesRequest`

**Pliki do utworzenia:**
- `api/app/Services/MovieComparisonService.php`
- `api/app/Http/Requests/CompareMoviesRequest.php`
- `api/app/Http/Controllers/Api/MovieController.php` - metoda `compare()`
- `api/tests/Feature/MovieComparisonTest.php`

**Priorytet:** Niski (może być nisza, małe zapotrzebowanie)

---

## Pytania do Rozstrzygnięcia

### 1. Wersjonowanie Opisów - Która Opcja?

**Pytanie:** Która opcja wersjonowania opisów powinna być zaimplementowana?

**Opcje:**
- A) Soft Delete + Insert Nowego Opisu (prosta, ale zmiana unique constraint)
- B) Osobna Tabela `movie_description_versions` (czysta separacja, więcej złożoności)
- C) Pole `version_number` + Soft Delete (balans, wymaga zmiany unique constraint)

**Rekomendacja:** C) Pole `version_number` + Soft Delete - najlepszy balans między prostotą a funkcjonalnością.

**Pytania dodatkowe:**
- Czy endpoint historii wersji jest potrzebny? (`GET /movies/{slug}/descriptions/{id}/versions`)
- Czy admin powinien móc przywrócić poprzednią wersję? (`POST /admin/descriptions/{id}/restore`)

---

### 2. Bulk Operations - Limit i Rate Limiting?

**Pytanie:** Jakie limity powinny być dla bulk operations?

- Maksymalna liczba slugów na request? (np. 50, 100, 200)
- Osobny rate limit dla bulk? (np. `adaptive.rate.limit:bulk` z limitem 10/min)
- Czy bulk powinien być cache'owany? (może być trudne dla wielu kombinacji slugów)

**Rekomendacja:**
- Limit: 50 slugów na request (rozsądny kompromis między użytecznością a performance)
- Osobny rate limit: Tak, `adaptive.rate.limit:bulk` z limitem niższym niż `show` (np. 20/min)
- Cache: Nie (zbyt wiele kombinacji, cache hit rate byłby niski)

---

### 3. Collections - Która Opcja?

**Pytanie:** Która opcja implementacji Collections powinna być użyta?

**Opcje:**
- A) Endpoint przez Movie Relationship (problemy z identyfikacją kolekcji)
- B) Nowa Tabela `collections` (czysta struktura, wymaga synchronizacji)
- C) Endpoint przez TMDb Snapshot (prostsze, zależy od snapshot)

**Rekomendacja:** C) Endpoint przez TMDb Snapshot dla MVP (prostsze, używa istniejących danych). Jeśli potrzeba więcej funkcjonalności (np. metadata kolekcji, ręczne zarządzanie), przejść na Opcję B.

**Pytania dodatkowe:**
- Czy endpoint listy wszystkich kolekcji jest potrzebny? (`GET /api/v1/collections`)
- Czy kolekcje powinny mieć własne slugi? (wymaga Opcji B)

---

### 4. Sortowanie - Jak Obsłużyć External Results?

**Pytanie:** Jak sortować wyniki, gdy są zarówno lokalne jak i external?

**Opcje:**
- A) Sortować osobno (lokalne według sortowania, external po confidence), potem merge
- B) Sortować wszystko razem po merge (wymaga re-sortowania)
- C) Tylko lokalne wyniki są sortowane (external zawsze na końcu, według confidence)

**Rekomendacja:** B) Sortować wszystko razem po merge - najbardziej spójne dla użytkownika. Jeśli external results nie mogą być sortowane przez TMDb API, sortować tylko lokalne przed merge, potem dodać external na końcu.

---

### 5. Porównywanie Filmów - Czy W Ogóle Potrzebne?

**Pytanie:** Czy endpoint porównywania filmów jest w ogóle potrzebny?

**Uzasadnienie:**
- Może być przydatne dla analityków, badaczy filmu
- Może być przydatne dla aplikacji porównujących filmy
- Ale może być nisza (małe zapotrzebowanie)

**Rekomendacja:** Niski priorytet - dodać tylko jeśli jest konkretne zapotrzebowanie od użytkowników. Na razie można pominąć.

---

## Plan Implementacji

### Faza 1: Krytyczne Ulepszenia (consistency, zachowanie danych)

**Priorytet:** Wysoki  
**Szacowany czas:** 2-3 tygodnie

1. ✅ **Rate Limiting dla `GET /movies/{slug}`**
   - Dodanie middleware
   - Konfiguracja
   - Testy

2. ✅ **Wersjonowanie Opisów (Opcja C: version_number + soft delete)**
   - Migration
   - Modyfikacja `RegenerateMovieDescriptionJob`
   - Testy
   - Opcjonalnie: endpoint historii wersji

---

### Faza 2: Ważne Ulepszenia (UX, performance)

**Priorytet:** Średni  
**Szacowany czas:** 2-3 tygodnie

3. ✅ **Sortowanie w Wyszukiwaniu**
   - Implementacja w `MovieSearchService`
   - Request validator
   - Testy

4. ✅ **Bulk Operations**
   - Endpoint `POST /movies/bulk`
   - Request validator
   - Repository method
   - Testy

---

### Faza 3: Nice to Have (opcjonalne)

**Priorytet:** Niski  
**Szacowany czas:** 1-2 tygodnie

5. ✅ **Collections Endpoint (Opcja C: przez TMDb Snapshot)**
   - Endpoint `GET /movies/{slug}/collection`
   - Service (opcjonalnie)
   - Testy

6. ✅ **Filtry po Gatunkach w Related**
   - Rozszerzenie `related()`
   - Testy

7. ✅ **Limit per Source w Search**
   - Rozszerzenie `MovieSearchService`
   - Testy

8. ❓ **Porównywanie Filmów**
   - Tylko jeśli jest zapotrzebowanie
   - Niski priorytet

---

## Podsumowanie

### Główne Cele

1. **Consistency** - Rate limiting dla `show()` (consistency z Person planem)
2. **Zachowanie Danych** - Wersjonowanie opisów (nie tracić historii)
3. **UX** - Sortowanie, bulk operations, collections
4. **Performance** - Limit per source, optymalizacje

### Priorytetyzacja

**Faza 1 (Wysoki priorytet):** Rate limiting + Wersjonowanie opisów  
**Faza 2 (Średni priorytet):** Sortowanie + Bulk operations  
**Faza 3 (Niski priorytet):** Collections + Filtry + Porównywanie

### Szacowany Czas Całkowity

- Faza 1: 2-3 tygodnie
- Faza 2: 2-3 tygodnie
- Faza 3: 1-2 tygodnie
- **Razem: 5-8 tygodni** (w zależności od priorytetyzacji)

---

## Notatki

- Dokument został utworzony jako plan, nie jako implementacja
- Przed implementacją należy rozstrzygnąć pytania z sekcji "Pytania do Rozstrzygnięcia"
- Zalecane jest wykonanie w fazach (nie wszystko naraz)
- Każda faza powinna być zakończona testami i code review
- Po każdej fazie warto zaktualizować dokumentację (API docs, manual testing guide)

---

**Autor:** AI Assistant  
**Data ostatniej aktualizacji:** 2025-01-XX


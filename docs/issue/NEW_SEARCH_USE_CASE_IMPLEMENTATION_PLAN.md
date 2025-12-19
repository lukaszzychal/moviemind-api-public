# Plan Implementacji - Nowy Use Case Wyszukiwania Filmów

## 📋 Przegląd

Plan podzielony na **8 niezależnych etapów**, każdy realizowany w osobnym branchu. Etapy są zaprojektowane tak, aby można je było mergować osobno i testować niezależnie.

---

## 🌳 Struktura Branchy

```
main
├── feature/search-endpoint (Etap 1)
├── feature/hide-tmdb-ids (Etap 2)
├── feature/movie-metadata-sync (Etap 3)
├── feature/movie-relationships (Etap 4)
├── feature/multiple-context-generation (Etap 5)
├── feature/movie-reports (Etap 6)
├── feature/adaptive-rate-limiting (Etap 7)
└── feature/search-caching (Etap 8 - może być w Etapie 1)
```

---

## 📦 Etap 1: Endpoint Wyszukiwania Filmów

**Branch:** `feature/search-endpoint`  
**Priorytet:** Wysoki  
**Zależności:** Brak  
**Status:** ✅ **UKOŃCZONY**

### Zadania:

1. **Utworzyć `MovieSearchService`:**
   - [x] Klasa `App\Services\MovieSearchService` ✅
   - [x] Metoda `search(array $criteria): SearchResult` ✅
   - [x] Wyszukiwanie lokalne (MovieRepository) ✅
   - [x] Wyszukiwanie TMDB (TmdbVerificationService) ✅
   - [x] Merge wyników (bez tmdb_id w odpowiedzi) ✅
   - [x] Obsługa różnych scenariuszy (exact, ambiguous, none) ✅

2. **Utworzyć `SearchResult` DTO:**
   - [x] Klasa `App\Support\SearchResult` ✅
   - [x] Właściwości: `results`, `total`, `local_count`, `external_count`, `match_type`, `confidence` ✅
   - [x] Metody pomocnicze ✅

3. **Dodać endpoint:**
   - [x] Route: `GET /api/v1/movies/search` ✅
   - [x] Controller method: `MovieController::search()` ✅
   - [x] Request validation: `SearchMovieRequest` ✅
   - [x] Query params: `q`, `year`, `director`, `actor[]`, `limit` ✅

4. **Cache'owanie wyników:**
   - [x] Cache key generation ✅
   - [x] TTL: 1h dla wyników TMDB ✅
   - [x] Cache invalidation ✅

5. **Testy:**
   - [x] Feature test: `SearchMoviesTest` ✅
   - [x] Unit test: `MovieSearchServiceTest` ✅
   - [x] Testy dla różnych scenariuszy (exact, ambiguous, none) ✅
   - [x] Testy cache'owania ✅

### Akceptacja:
- ✅ Endpoint zwraca wyniki lokalne + zewnętrzne (bez tmdb_id)
- ✅ Obsługuje wszystkie scenariusze (200, 300, 404)
- ✅ Cache działa poprawnie
- ✅ Wszystkie testy przechodzą

### Merge do: `main` ✅

---

## 📦 Etap 2: Ukrycie TMDB ID w API

**Branch:** `feature/hide-tmdb-ids`  
**Priorytet:** Wysoki  
**Zależności:** Brak (może być równolegle z Etapem 1)  
**Status:** ✅ **UKOŃCZONY**

### Zadania:

1. **Zaktualizować `MovieController`:**
   - [x] Usunąć `tmdb_id` z odpowiedzi disambiguation ✅
   - [x] Użyć slugów zamiast `tmdb_id` w `handleDisambiguationSelection()` ✅
   - [x] Zaktualizować `respondWithDisambiguation()` - bez `tmdb_id` ✅

2. **Zaktualizować `PersonController`:**
   - [x] Analogiczne zmiany jak w MovieController ✅

3. **Zaktualizować Resources:**
   - [x] `MovieResource` - sprawdzić czy nie zwraca `tmdb_id` ✅
   - [x] `PersonResource` - sprawdzić czy nie zwraca `tmdb_id` ✅

4. **Zaktualizować dokumentację:**
   - [x] OpenAPI spec - usunąć `tmdb_id` z przykładów ✅
   - [x] README - zaktualizować przykłady ✅

5. **Testy:**
   - [x] Feature test: sprawdzić że odpowiedzi nie zawierają `tmdb_id` ✅ (`TmdbIdHiddenTest`)
   - [x] Test disambiguation - używa slugów zamiast `tmdb_id` ✅

### Akceptacja:
- ✅ Żadna odpowiedź API nie zawiera `tmdb_id`
- ✅ Disambiguation używa slugów
- ✅ Wszystkie testy przechodzą (7 passed, 52 assertions)

### Merge do: `main` ✅

---

## 📦 Etap 3: Synchronizacja Metadanych Filmów (Aktorzy/Crew)

**Branch:** `feature/movie-metadata-sync`  
**Priorytet:** Średni  
**Zależności:** Brak  
**Status:** ✅ **UKOŃCZONY**

### Zadania:

1. **Utworzyć `SyncMovieMetadataJob`:**
   - [x] Klasa `App\Jobs\SyncMovieMetadataJob implements ShouldQueue` ✅
   - [x] Pobieranie pełnych danych z TMDB (cast, crew) ✅
   - [x] Tworzenie/znajdowanie obiektów Person ✅
   - [x] Łączenie z filmem (movie_person pivot) ✅
   - [x] Obsługa błędów i retry ✅

2. **Rozszerzyć `TmdbMovieCreationService`:**
   - [x] Wywołanie `SyncMovieMetadataJob` po utworzeniu filmu ✅
   - [x] Tylko metadane przy pierwszym utworzeniu ✅

3. **Zaktualizować `/refresh` endpoint:**
   - [x] Tylko metadane filmu (tytuł, rok, reżyser, genres) ✅
   - [x] **NIE** synchronizować aktorów ponownie ✅

4. **Sprawdzić model `Person`:**
   - [x] Czy ma `tmdb_id`? ✅ (migracja: `2025_12_17_220207_add_tmdb_id_to_people_table.php`)
   - [x] Metoda `generateSlug()` ✅ (istnieje w modelu Person)

5. **Testy:**
   - [x] Feature test: `MovieMetadataSyncTest` ✅ (9 passed, 58 assertions)
   - [x] Test tworzenia filmu z aktorami ✅
   - [x] Test `/refresh` - tylko metadane ✅
   - [x] Unit test: `SyncMovieMetadataJobTest` ✅
   - [x] Testy edge cases (duplikaty, brak danych, puste tablice) ✅

### Akceptacja:
- ✅ Film tworzy się z metadanymi
- ✅ Aktorzy synchronizują się asynchronicznie
- ✅ `/refresh` nie synchronizuje aktorów
- ✅ Wszystkie testy przechodzą (9 passed, 58 assertions)

### Merge do: `main` ✅

**Dodatkowe informacje:**
- Migracja dla `tmdb_id` w tabeli `movies`: `2025_12_17_220440_add_tmdb_id_to_movies_table.php`
- Migracja dla `tmdb_id` w tabeli `people`: `2025_12_17_220207_add_tmdb_id_to_people_table.php`
- Dokumentacja testów: `docs/TEST_RESULTS_ETAP3.md`
- Skrypt testowy: `docs/test-etap3-sync-metadata.sh`

---

## 📦 Etap 4: Powiązane Filmy (Relationships)

**Branch:** `feature/movie-relationships`  
**Priorytet:** Średni  
**Zależności:** Brak  
**Status:** ✅ **UKOŃCZONY**

### Zadania:

1. **Migracja:**
   - [x] Utworzyć tabelę `movie_relationships` ✅
   - [x] Kolumny: `id`, `movie_id`, `related_movie_id`, `relationship_type`, `order`, `timestamps` ✅
   - [x] Foreign keys i indeksy ✅

2. **Model:**
   - [x] Klasa `App\Models\MovieRelationship` ✅
   - [x] Relacje: `movie()`, `relatedMovie()` ✅
   - [x] Enum dla `relationship_type`: SEQUEL, PREQUEL, REMAKE, SERIES, SPINOFF, SAME_UNIVERSE ✅

3. **Rozszerzyć model `Movie`:**
   - [x] Relacja `relatedMovies()` (BelongsToMany) ✅
   - [x] Metoda pomocnicza do pobierania powiązanych ✅ (`getRelatedMovies()`)

4. **Utworzyć `SyncMovieRelationshipsJob`:**
   - [x] Wykrywanie z TMDB (collection_id, related movies) ✅
   - [x] Tworzenie relacji ✅
   - [x] Obsługa różnych typów relacji ✅

5. **Endpoint:**
   - [x] Route: `GET /api/v1/movies/{slug}/related` ✅
   - [x] Controller method: `MovieController::related()` ✅
   - [x] Query param: `type[]` (filtrowanie) ✅
   - [x] Domyślnie wszystkie typy ✅

6. **Integracja:**
   - [x] Wywołanie `SyncMovieRelationshipsJob` po utworzeniu filmu ✅
   - [x] W `TmdbMovieCreationService` ✅

7. **Testy:**
   - [x] Feature test: `MovieRelationshipsTest` ✅ (4 passed, 32 assertions)
   - [x] Test endpointu `/related` z filtrowaniem ✅
   - [x] Unit test: `SyncMovieRelationshipsJobTest` ✅
   - [x] Test wykrywania różnych typów relacji ✅

### Akceptacja:
- ✅ Tabela i model działają
- ✅ Endpoint zwraca powiązane filmy
- ✅ Filtrowanie po typie działa
- ✅ Wszystkie testy przechodzą (4 passed, 32 assertions)

### Merge do: `main` ✅

---

## 📦 Etap 5: Wielokrotne Generowanie Opisów (Context Tags)

**Branch:** `feature/multiple-context-generation`  
**Priorytet:** Średni  
**Zależności:** Brak  
**Status:** 🔄 **W TRAKCIE** (częściowo ukończony)

### Zadania:

1. **Rozszerzyć `QueueMovieGenerationAction`:**
   - [x] Obsługa wielu context_tag jednocześnie ✅
   - [x] Queue wielu jobów dla różnych context_tag ✅
   - [x] Walidacja dostępnych context_tag ✅

2. **Zaktualizować `GenerateController`:**
   - [x] Parametr `context_tag` może być array ✅
   - [x] Obsługa pojedynczego i wielu context_tag ✅

3. **Zabezpieczenia AI:** (osobny branch: `feature/ai-security` - PR #147 zmergowany + uzupełnienia)
   - [x] Serwis `HtmlSanitizer` stworzony ✅ (PR #147)
   - [x] Unit testy dla `HtmlSanitizer` (20+ test cases) ✅ (PR #147)
   - [x] **Integracja `HtmlSanitizer` z `RealGenerateMovieJob`** ✅ (poprzez `AiOutputValidator`)
   - [x] Serwis `AiOutputValidator` stworzony ✅
   - [x] Rozszerzyć `RealGenerateMovieJob` o walidację outputu ✅
   - [x] Sprawdzenie podobieństwa z oryginałem (anti-hallucination) ✅
   - [x] Wykrywanie AI injection w outputcie ✅

4. **System prompts:** (osobny branch: `feature/ai-security`)
   - [x] Metoda `generateMovieDescription()` z obsługą context_tag ✅
   - [x] Zaktualizować prompty dla różnych context_tag ✅
   - [x] Dodanie zabezpieczeń w system promptach ✅

5. **Testy:**
   - [x] Feature test: generowanie wielu context_tag ✅ (18 passed, 88 assertions)
   - [x] Unit testy XSS (`HtmlSanitizerTest`) ✅ (PR #147)
   - [x] **Integracja zabezpieczeń w `RealGenerateMovieJob`** ✅
   - [x] Testy integracyjne: XSS, AI injection, walidacja outputu ✅ (`AiOutputValidationIntegrationTest` - 4 testy)
   - [x] Unit test: `AiOutputValidator` ✅ (`AiOutputValidatorTest` - 13 testów, 1 skipped)

### Akceptacja:
- ✅ Można generować wiele context_tag jednocześnie
- ⏳ Zabezpieczenia działają (osobny branch)
- ✅ Wszystkie testy przechodzą (18 passed, 88 assertions)

### Merge do: `main` (częściowy - podstawowa funkcjonalność)

---

## 📦 Etap 6: Zgłaszanie Błędów (Movie Reports)

**Branch:** `feature/movie-reports`  
**Priorytet:** Średni  
**Zależności:** Brak  
**Szacowany czas:** 3-4 dni

### Zadania:

1. **Migracja:**
   - [x] Utworzyć tabelę `movie_reports` ✅
   - [x] Kolumny: `id`, `movie_id`, `description_id`, `type`, `message`, `suggested_fix`, `status`, `priority_score`, `verified_by`, `verified_at`, `resolved_at`, `timestamps` ✅
   - [x] Foreign keys i indeksy ✅

2. **Model:**
   - [x] Klasa `App\Models\MovieReport` ✅
   - [x] Relacje: `movie()`, `description()` ✅
   - [x] Enum dla `type` i `status` (`ReportType`, `ReportStatus`) ✅
   - [x] Metody pomocnicze: `isPending()`, `isVerified()`, `isResolved()` ✅

3. **Service:**
   - [x] Klasa `App\Services\MovieReportService` ✅
   - [x] Metoda `calculatePriorityScore(MovieReport $report): float` ✅
   - [x] Wzór: `count(pending reports of same type) * weight(type)` ✅
   - [x] Wagi typów błędów w `ReportType::weight()` ✅

4. **Endpoint użytkownika:**
   - [x] Route: `POST /api/v1/movies/{slug}/report` ✅
   - [x] Controller method: `MovieController::report()` ✅
   - [x] Request validation: `ReportMovieRequest` ✅
   - [x] Tworzenie reportu z automatycznym obliczeniem priority_score ✅

5. **Endpoint admina:**
   - [x] Route: `GET /api/v1/admin/reports` ✅
   - [x] Controller: `Admin\ReportController::index()` ✅
   - [x] Repository: `MovieReportRepository` dla filtrowania ✅
   - [x] Filtrowanie: `status`, `priority` (high/medium/low) ✅
   - [x] Sortowanie: `priority_score DESC, created_at DESC` ✅
   - [x] **Priorytet widoczny w odpowiedzi** ✅

6. **Weryfikacja i regeneracja:**
   - [x] Endpoint: `POST /api/v1/admin/reports/{id}/verify` ✅
   - [x] Action: `VerifyMovieReportAction` (thin controller) ✅
   - [x] Zmiana statusu na `verified` ✅
   - [x] Automatyczna regeneracja (queue job `RegenerateMovieDescriptionJob`) ✅

7. **Job regeneracji:**
   - [x] Utworzyć `RegenerateMovieDescriptionJob` ✅
   - [x] Wywołanie po weryfikacji ✅
   - [x] Aktualizacja statusu na `resolved` po regeneracji ✅
   - [x] Integracja z `AiOutputValidator` dla sanitizacji ✅

8. **Testy:**
   - [x] Feature test: zgłaszanie błędów (`MovieReportTest` - 6 testów) ✅
   - [x] Feature test: admin endpoints (`AdminMovieReportsTest` - 5 testów) ✅
   - [x] Feature test: weryfikacja (`AdminReportVerificationTest` - 4 testy) ✅
   - [x] Test priorytetyzacji (`MovieReportServiceTest` - 5 testów) ✅
   - [x] Test automatycznej regeneracji (w `AdminReportVerificationTest`) ✅
   - [x] Unit test: `MovieReportServiceTest` ✅

### Akceptacja:
- ✅ Użytkownik może zgłosić błąd (`POST /api/v1/movies/{slug}/report`)
- ✅ Admin widzi zgłoszenia z priorytetem (`GET /api/v1/admin/reports` z filtrowaniem i sortowaniem)
- ✅ Po weryfikacji automatyczna regeneracja (`POST /api/v1/admin/reports/{id}/verify` → `RegenerateMovieDescriptionJob`)
- ✅ Wszystkie testy przechodzą (20 testów, 97 assertions)

### Merge do: `main`

---

## 📦 Etap 7: Adaptive Rate Limiting

**Branch:** `feature/adaptive-rate-limiting`  
**Priorytet:** Niski  
**Zależności:** Brak  
**Szacowany czas:** 2-3 dni

### Zadania:

1. **Konfiguracja:**
   - [ ] Utworzyć `config/rate-limiting.php`
   - [ ] Domyślne wartości: SEARCH=100/min, GENERATE=10/min, REPORT=20/min
   - [ ] Konfiguracja adaptive (min, max, thresholds)

2. **Service:**
   - [ ] Klasa `App\Services\AdaptiveRateLimiter`
   - [ ] Metoda `getMaxAttempts(string $endpoint): int`
   - [ ] Monitorowanie: CPU load, queue size, active jobs
   - [ ] **Weryfikacja CPU load w Docker** (zobacz `docs/ADAPTIVE_RATE_LIMITING_METRICS.md`)
   - [ ] Obliczanie load factor (auto-detection: CPU jeśli dostępne, w przeciwnym razie tylko Queue + Active Jobs)
   - [ ] Zmniejszanie limitów przy obciążeniu > 70%

3. **Middleware:**
   - [ ] Klasa `App\Http\Middleware\AdaptiveRateLimit`
   - [ ] Zastosowanie dynamicznych limitów
   - [ ] Response 429 z `retry_after`

4. **Zastosowanie:**
   - [ ] Dodać middleware do routes
   - [ ] `/api/v1/movies/search` - endpoint `search`
   - [ ] `/api/v1/generate` - endpoint `generate`
   - [ ] `/api/v1/movies/{slug}/report` - endpoint `report`

5. **Monitoring:**
   - [ ] Logowanie zmian limitów
   - [ ] Metryki obciążenia (opcjonalnie)
   - [ ] **Weryfikacja CPU load** - wykonaj testy z `docs/ADAPTIVE_RATE_LIMITING_METRICS.md` przed implementacją

6. **Testy:**
   - [ ] Feature test: rate limiting działa
   - [ ] Test adaptive - zmniejszanie przy obciążeniu
   - [ ] Unit test: `AdaptiveRateLimiterTest`
   - [ ] Test różnych endpointów

### Akceptacja:
- ✅ Rate limiting działa
- ✅ Auto-dostosowanie do obciążenia
- ✅ Wszystkie testy przechodzą

### Merge do: `main`

---

## 📦 Etap 8: Cache'owanie Wyszukiwania (opcjonalnie w Etapie 1)

**Branch:** `feature/search-caching`  
**Priorytet:** Niski (może być częścią Etapu 1)  
**Zależności:** Etap 1  
**Szacowany czas:** 1 dzień

### Zadania:

1. **Cache service:**
   - [ ] Rozszerzyć `MovieSearchService` o cache
   - [ ] Cache key generation z parametrów wyszukiwania
   - [ ] TTL: 1h dla wyników TMDB

2. **Cache invalidation:**
   - [ ] Po utworzeniu nowego filmu
   - [ ] Po aktualizacji filmu
   - [ ] Strategia cache tags (opcjonalnie)

3. **Testy:**
   - [ ] Test cache'owania
   - [ ] Test invalidation

### Akceptacja:
- ✅ Cache działa poprawnie
- ✅ Invalidation działa
- ✅ Wszystkie testy przechodzą

### Merge do: `main`

---

## 🔄 Kolejność Realizacji

### Faza 1: Podstawy (Tydzień 1-2)
1. **Etap 1:** Endpoint wyszukiwania
2. **Etap 2:** Ukrycie TMDB ID

### Faza 2: Rozszerzenia (Tydzień 3-4)
3. **Etap 3:** Synchronizacja metadanych
4. **Etap 4:** Powiązane filmy

### Faza 3: Zaawansowane (Tydzień 5-6)
5. **Etap 5:** Wielokrotne generowanie
6. **Etap 6:** Zgłaszanie błędów

### Faza 4: Optymalizacja (Tydzień 7)
7. **Etap 7:** Adaptive rate limiting
8. **Etap 8:** Cache'owanie (jeśli nie w Etapie 1)

---

## ✅ Checklist przed każdym merge'em

Dla każdego brancha przed merge'em do `main`:

- [ ] Wszystkie testy przechodzą (`php artisan test`)
- [ ] PHPStan bez błędów (`vendor/bin/phpstan analyse`)
- [ ] Laravel Pint bez błędów (`vendor/bin/pint`)
- [ ] Brak nieużywanych plików/funkcji (oznaczone komentarzami jeśli znalezione)
- [ ] Dokumentacja zaktualizowana (jeśli potrzeba)
- [ ] OpenAPI spec zaktualizowany (jeśli nowe endpointy)
- [ ] Code review wykonany
- [ ] Feature flag dodany (jeśli potrzeba)

---

## 🧪 Testy Integracyjne (po wszystkich etapach)

Po zmergowaniu wszystkich branchy:

- [ ] End-to-end test: pełny flow wyszukiwania → tworzenia → generowania
- [ ] Test wydajności: wyszukiwanie pod obciążeniem
- [ ] Test bezpieczeństwa: AI injection, XSS
- [ ] Test rate limiting pod obciążeniem
- [ ] Test cache'owania

---

## 📝 Notatki

- Każdy etap powinien być niezależny i możliwy do mergowania osobno
- Jeśli znajdziesz nieużywane pliki/funkcje - oznacz komentarzami (patrz: `NEW_SEARCH_USE_CASE_ANALYSIS.md`)
- Testy są obowiązkowe dla każdego etapu
- Dokumentacja powinna być aktualizowana na bieżąco

---

---

## 📊 Status Implementacji

**Ostatnia aktualizacja:** 2025-12-18

### ✅ Ukończone Etapy

| Etap | Status | Testy | Dokumentacja |
|------|--------|-------|---------------|
| **Etap 1:** Endpoint Wyszukiwania Filmów | ✅ UKOŃCZONY | ✅ SearchMoviesTest | ✅ OpenAPI |
| **Etap 2:** Ukrycie TMDB ID w API | ✅ UKOŃCZONY | ✅ TmdbIdHiddenTest (7 passed) | ✅ OpenAPI |
| **Etap 3:** Synchronizacja Metadanych | ✅ UKOŃCZONY | ✅ MovieMetadataSyncTest (9 passed) | ✅ OpenAPI + TEST_RESULTS_ETAP3.md |
| **Etap 4:** Powiązane Filmy (Relationships) | ✅ UKOŃCZONY | ✅ MovieRelationshipsTest (4 passed) | ✅ OpenAPI + MANUAL_TESTING_RELATIONSHIPS.md |

### ⏳ Pozostałe Etapy

- **Etap 5:** Wielokrotne Generowanie Opisów - PENDING
- **Etap 6:** Zgłaszanie Błędów (Movie Reports) - PENDING
- **Etap 7:** Adaptive Rate Limiting - PENDING
- **Etap 8:** Cache'owanie Wyszukiwania - PENDING

### 📈 Postęp

- **Ukończone:** 4/8 etapów (50%)
- **W trakcie:** 0/8 etapów
- **Oczekujące:** 4/8 etapów (50%)

---

**Gotowe do rozpoczęcia implementacji! 🚀**


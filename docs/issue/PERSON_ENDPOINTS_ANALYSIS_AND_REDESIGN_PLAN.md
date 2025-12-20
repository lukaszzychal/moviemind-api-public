# Analiza i Plan Refaktoryzacji/Redesignu Endpointów People

**Data utworzenia:** 2025-01-XX  
**Status:** DRAFT - Plan (nie implementacja)  
**Cel:** Analiza różnic między Movie a Person, identyfikacja braków, propozycja refaktoryzacji i dodatkowych funkcjonalności

---

## 📋 Spis Treści

1. [Obecny Stan](#obecny-stan)
2. [Porównanie Movie vs Person](#porównanie-movie-vs-person)
3. [Zidentyfikowane Problemy](#zidentyfikowane-problemy)
4. [Propozycje Refaktoryzacji](#propozycje-refaktoryzacji)
5. [Dodatkowe Funkcjonalności dla Person](#dodatkowe-funkcjonalności-dla-person)
6. [Dodatkowe Funkcjonalności dla Movie](#dodatkowe-funkcjonalności-dla-movie)
7. [Plan Implementacji](#plan-implementacji)
8. [Pytania do Rozstrzygnięcia](#pytania-do-rozstrzygnięcia)

---

## Obecny Stan

### Endpointy Person (obecnie)

```
GET  /api/v1/people              # Lista osób z prostym wyszukiwaniem (?q=)
GET  /api/v1/people/{slug}       # Szczegóły osoby
POST /api/v1/people/{slug}/refresh  # Odświeżenie danych z TMDb
```

### Endpointy Movie (obecnie)

```
GET  /api/v1/movies              # Lista filmów z prostym wyszukiwaniem (?q=)
GET  /api/v1/movies/search       # Zaawansowane wyszukiwanie (local + external, paginacja, cache)
GET  /api/v1/movies/{slug}       # Szczegóły filmu
GET  /api/v1/movies/{slug}/related  # Powiązane filmy (sequels, prequels, similar)
POST /api/v1/movies/{slug}/refresh  # Odświeżenie danych z TMDb
POST /api/v1/movies/{slug}/report   # Zgłaszanie błędów w opisach
```

### Architektura Movie

- ✅ `MovieSearchService` - zaawansowane wyszukiwanie (local + external, cache, paginacja)
- ✅ `MovieRetrievalService` - scentralizowana logika pobierania filmu
- ✅ `MovieResponseFormatter` - scentralizowane formatowanie odpowiedzi
- ✅ `SearchMovieRequest` - walidacja parametrów wyszukiwania
- ✅ `ReportMovieRequest` - walidacja raportów
- ✅ `MovieReportService` - logika priorytetyzacji raportów
- ✅ `MovieDisambiguationService` - obsługa disambiguation
- ✅ Admin endpoints dla raportów (`/api/v1/admin/reports`)

### Architektura Person

- ❌ Brak dedykowanego Search Service (tylko `PersonRepository::searchPeople`)
- ❌ Brak Retrieval Service (logika bezpośrednio w kontrolerze)
- ❌ Brak Response Formatter (formatowanie bezpośrednio w kontrolerze)
- ❌ Brak Request validators (walidacja bezpośrednio w kontrolerze)
- ✅ `PersonDisambiguationService` - obsługa disambiguation
- ❌ Brak endpointu `/people/search` (tylko `/people?q=`)
- ❌ Brak endpointu `/people/{slug}/report`
- ❌ Brak admin endpoints dla raportów Person

---

## Porównanie Movie vs Person

### 1. Wyszukiwanie

| Aspekt | Movie | Person |
|--------|-------|--------|
| **Dedykowany endpoint** | ✅ `/movies/search` | ❌ Tylko `/people?q=` |
| **Wyszukiwanie lokalne** | ✅ `MovieRepository::searchMovies` | ✅ `PersonRepository::searchPeople` |
| **Wyszukiwanie external (TMDb)** | ✅ W `MovieSearchService` | ❌ Brak |
| **Paginacja** | ✅ `?page=`, `?per_page=` | ❌ Brak |
| **Zaawansowane filtry** | ✅ `?year=`, `?director=`, `?actor=` | ❌ Brak |
| **Cache** | ✅ Tagged cache (`movie_search`) | ❌ Brak |
| **Confidence scoring** | ✅ `matchType`, `confidence` | ❌ Brak |
| **Walidacja parametrów** | ✅ `SearchMovieRequest` | ❌ Brak |
| **Rate limiting** | ✅ `adaptive.rate.limit:search` | ❌ Brak |

### 2. Pobieranie pojedynczego zasobu

| Aspekt | Movie | Person |
|--------|-------|--------|
| **Dedykowany Service** | ✅ `MovieRetrievalService` | ❌ Logika w kontrolerze |
| **Response Formatter** | ✅ `MovieResponseFormatter` | ❌ Formatowanie w kontrolerze |
| **Cache** | ✅ `movie:{slug}:desc:{id}` | ✅ `person:{slug}:bio:{id}` |
| **Disambiguation** | ✅ `MovieDisambiguationService` | ✅ `PersonDisambiguationService` |
| **Select by ID** | ✅ `?description_id=` | ✅ `?bio_id=` |

### 3. Zgłaszanie błędów

| Aspekt | Movie | Person |
|--------|-------|--------|
| **Endpoint** | ✅ `POST /movies/{slug}/report` | ❌ Brak |
| **Request validator** | ✅ `ReportMovieRequest` | ❌ Brak |
| **Service** | ✅ `MovieReportService` | ❌ Brak |
| **Model** | ✅ `MovieReport` | ❌ Brak |
| **Admin endpoints** | ✅ `/admin/reports` | ❌ Brak |
| **Regeneracja po weryfikacji** | ✅ `RegenerateMovieDescriptionJob` | ❌ Brak |

### 4. Powiązane zasoby

| Aspekt | Movie | Person |
|--------|-------|--------|
| **Endpoint** | ✅ `/movies/{slug}/related` | ❌ Brak |
| **Typy relacji** | ✅ SEQUEL, PREQUEL, SERIES, SPINOFF, REMAKE, SIMILAR | ❌ Brak (ale istnieje relacja `movies()` w modelu) |
| **Filtrowanie** | ✅ `?type=collection|similar|all` | ❌ Brak |

### 5. Architektura kodu

| Aspekt | Movie | Person |
|--------|-------|--------|
| **Thin Controller** | ✅ Controller deleguje do Services/Actions | ⚠️ Częściowo (ma Actions, ale logika też w kontrolerze) |
| **Service Layer** | ✅ `MovieSearchService`, `MovieRetrievalService` | ❌ Brak |
| **Response Formatter** | ✅ `MovieResponseFormatter` | ❌ Brak |
| **Request Validators** | ✅ `SearchMovieRequest`, `ReportMovieRequest` | ❌ Brak |

---

## Zidentyfikowane Problemy

### 🔴 Krytyczne (consistency, brak funkcji)

1. **Brak zaawansowanego wyszukiwania dla Person**
   - Person nie ma endpointu `/people/search` z zaawansowanymi kryteriami
   - Brak wyszukiwania w TMDb (tylko local)
   - Brak paginacji
   - Brak cache'owania wyników wyszukiwania
   - Brak confidence scoring

2. **Brak endpointu raportowania błędów dla Person**
   - Użytkownicy nie mogą zgłaszać błędów w biografiach Person
   - Brak admin endpoints do zarządzania raportami Person
   - Brak możliwości regeneracji biografii po weryfikacji raportu

3. **Niespójna architektura**
   - Person nie ma Service Layer (logika w kontrolerze)
   - Person nie ma Response Formatter (formatowanie w kontrolerze)
   - Person nie ma Request Validators (walidacja w kontrolerze)
   - Narusza zasadę "Thin Controllers"

### 🟡 Ważne (consistency, UX)

4. **Brak endpointu related dla Person**
   - Person nie ma endpointu `/people/{slug}/related` do pobierania powiązanych osób
   - Możliwe relacje: współpracownicy (wspólne filmy), osoby z tym samym imieniem/nazwiskiem, itp.

5. **Brak rate limiting dla Person**
   - Endpoint `/people` nie ma rate limiting (Movie ma `adaptive.rate.limit:search`)
   - Endpoint `/people/{slug}` nie ma rate limiting

6. **Brak zaawansowanych filtrów wyszukiwania**
   - Person nie obsługuje filtrów typu `?birth_year=`, `?birthplace=`, `?role=` (ACTOR, DIRECTOR, etc.)

### 🟢 Mniejsze (code quality, maintainability)

7. **Duplikacja kodu**
   - Logika formatowania odpowiedzi duplikowana między Movie a Person
   - Brak wspólnego interfejsu/abstrakcji dla Entity Search/Retrieval

8. **Brak testów dla zaawansowanych scenariuszy**
   - Person nie ma testów dla zaawansowanego wyszukiwania (bo nie istnieje)
   - Brak testów dla rate limiting (bo nie istnieje)

---

## Propozycje Refaktoryzacji

### 1. Dodanie PersonSearchService (wzorowany na MovieSearchService)

**Cel:** Zapewnienie spójnego, zaawansowanego wyszukiwania dla Person.

**Funkcjonalności:**
- Wyszukiwanie lokalne (baza danych)
- Wyszukiwanie external (TMDb API)
- Paginacja (`?page=`, `?per_page=`)
- Zaawansowane filtry:
  - `?birth_year=` - rok urodzenia
  - `?birthplace=` - miejsce urodzenia
  - `?role=` - rola (ACTOR, DIRECTOR, WRITER, PRODUCER)
  - `?movie=` - filmy, w których osoba grała/realizowała
- Cache'owanie wyników (tagged cache: `person_search`)
- Confidence scoring (exact, ambiguous, none)
- Merge lokalnych i external wyników (deduplikacja)

**Nowy endpoint:**
```
GET /api/v1/people/search
```

**Pliki do utworzenia:**
- `api/app/Services/PersonSearchService.php`
- `api/app/Http/Requests/SearchPersonRequest.php`
- `api/tests/Unit/Services/PersonSearchServiceTest.php`
- `api/tests/Feature/PersonSearchTest.php`

**Modyfikacje:**
- `api/app/Http/Controllers/Api/PersonController.php` - dodanie metody `search()`
- `api/routes/api.php` - dodanie route z rate limiting
- `api/app/Repositories/PersonRepository.php` - rozszerzenie metod wyszukiwania (opcjonalnie)

---

### 2. Dodanie PersonRetrievalService (wzorowany na MovieRetrievalService)

**Cel:** Scentralizowanie logiki pobierania Person, zgodnie z zasadą "Thin Controllers".

**Funkcjonalności:**
- Pobieranie Person po slug
- Obsługa disambiguation (przez PersonDisambiguationService)
- Obsługa `bio_id` (wybór konkretnej biografii)
- Cache'owanie wyników
- Obsługa braku Person (generacja w kolejce)

**Pliki do utworzenia:**
- `api/app/Services/PersonRetrievalService.php`
- `api/app/Support/PersonRetrievalResult.php` (podobny do MovieRetrievalResult)
- `api/tests/Unit/Services/PersonRetrievalServiceTest.php`

**Modyfikacje:**
- `api/app/Http/Controllers/Api/PersonController.php` - refaktoryzacja metody `show()`

---

### 3. Dodanie PersonResponseFormatter (wzorowany na MovieResponseFormatter)

**Cel:** Scentralizowanie formatowania odpowiedzi Person, zgodnie z zasadą DRY.

**Funkcjonalności:**
- `formatSuccess()` - sukces
- `formatError()` - błąd
- `formatNotFound()` - nie znaleziono
- `formatGenerationQueued()` - generacja w kolejce
- `formatDisambiguation()` - disambiguation (300 Multiple Choices)
- `formatPersonList()` - lista osób

**Pliki do utworzenia:**
- `api/app/Http/Responses/PersonResponseFormatter.php`
- `api/tests/Unit/Http/Responses/PersonResponseFormatterTest.php`

**Modyfikacje:**
- `api/app/Http/Controllers/Api/PersonController.php` - użycie formattera zamiast bezpośredniego formatowania

---

### 4. Dodanie Person Reports (wzorowane na Movie Reports)

**Cel:** Umożliwienie użytkownikom zgłaszania błędów w biografiach Person.

**Funkcjonalności:**
- Endpoint `POST /api/v1/people/{slug}/report`
- Request validator `ReportPersonRequest`
- Model `PersonReport` (podobny do `MovieReport`)
- Service `PersonReportService` (priorytetyzacja raportów)
- Admin endpoints (uniwersalny kontroler z `entity_type`):
  - `GET /api/v1/admin/reports?entity_type=PERSON` - lista raportów Person
  - `POST /api/v1/admin/reports/{id}/verify` - weryfikacja raportu (działa dla MOVIE i PERSON)
- Job `RegeneratePersonBioJob` (regeneracja biografii po weryfikacji)
- Repository: rozszerzenie `MovieReportRepository` do uniwersalnego `ReportRepository` (lub Strategy pattern)

**Nowe pliki:**
- `api/app/Models/PersonReport.php`
- `api/database/migrations/XXXX_XX_XX_create_person_reports_table.php`
- **REFACTOR:** `api/app/Enums/MovieReportType.php` → `api/app/Enums/ReportType.php` (używany przez Movie i Person)
- `api/app/Services/PersonReportService.php` (może być uniwersalny `ReportService` z Strategy pattern)
- `api/app/Http/Requests/ReportPersonRequest.php`
- `api/app/Http/Controllers/Api/PersonController.php` - metoda `report()`
- `api/app/Actions/VerifyPersonReportAction.php` (może być uniwersalny z Strategy pattern)
- `api/app/Jobs/RegeneratePersonBioJob.php`
- `api/tests/Feature/PersonReportTest.php`
- `api/tests/Feature/AdminPersonReportsTest.php`

**Modyfikacje:**
- `api/routes/api.php` - dodanie routes z rate limiting
- `api/app/Http/Controllers/Admin/ReportController.php` - refaktoryzacja do uniwersalnego kontrolera z `?entity_type=PERSON|MOVIE`
- `api/app/Models/MovieReport.php` - zmiana `MovieReportType` na `ReportType`
- `api/app/Repositories/MovieReportRepository.php` - rozszerzenie do uniwersalnego lub użycie Strategy pattern

**Decyzja:** Używamy **jednego wspólnego enum `ReportType`** (zamiast `MovieReportType` i `PersonReportType`) - typy błędów są uniwersalne.

---

### 5. Dodanie Rate Limiting dla Person

**Cel:** Ochrona przed nadmiernym obciążeniem endpointów Person.

**Modyfikacje:**
- `api/routes/api.php`:
  ```php
  Route::get('people/search', [PersonController::class, 'search'])->middleware('adaptive.rate.limit:search');
  Route::get('people/{slug}', [PersonController::class, 'show'])->middleware('adaptive.rate.limit:show'); // osobny limit
  Route::post('people/{slug}/report', [PersonController::class, 'report'])->middleware('adaptive.rate.limit:report');
  ```
- `api/config/rate-limiting.php` - dodanie konfiguracji dla `show` endpoint

**Decyzja:** Używamy **osobnego limitu `adaptive.rate.limit:show`** - endpoint `show()` jest prostszy (jeden rekord) niż search, więc może mieć wyższy limit. Consistency z Movie (jeśli Movie również używa osobnego limitu dla `show()`).

---

## Relacje People-Movies (Szczegóły)

### Obecna Struktura

Osoby (Person) są powiązane z filmami (Movie) poprzez tabelę pivot `movie_person` z następującymi kolumnami:

| Kolumna | Typ | Opis |
|---------|-----|------|
| `movie_id` | UUID | ID filmu (FK do `movies`) |
| `person_id` | UUID | ID osoby (FK do `people`) |
| `role` | VARCHAR(16) | Rola osoby w filmie (ACTOR, DIRECTOR, WRITER, PRODUCER) |
| `character_name` | VARCHAR (nullable) | Nazwa postaci (dla ACTOR, np. "Neo", "Trinity") |
| `job` | VARCHAR (nullable) | Konkretna funkcja (dla crew, np. "Director", "Screenwriter", "Composer") |
| `billing_order` | SMALLINT (nullable) | Kolejność w napisach końcowych |

**Primary Key:** `(movie_id, person_id, role)` - osoba może mieć wiele ról w tym samym filmie (np. aktor + reżyser).

### Obecne Role (CHECK constraint)

Obecnie obsługiwane role (z constraint w bazie danych):
- `ACTOR` - aktor/aktorka (z polem `character_name` dla nazwy postaci)
- `DIRECTOR` - reżyser/reżyserka
- `WRITER` - scenarzysta/scenarzystka
- `PRODUCER` - producent/producentka

### Pola Dodatkowe

- **`character_name`** - używane głównie dla `ACTOR`, przechowuje nazwę postaci (np. "Neo", "Trinity", "Morpheus")
- **`job`** - używane dla crew, może zawierać szczegółowe funkcje:
  - Dla DIRECTOR: "Director", "Co-Director", "Executive Producer"
  - Dla WRITER: "Screenwriter", "Story Writer", "Dialogue Writer"
  - Dla PRODUCER: "Producer", "Executive Producer", "Line Producer"
  - Dla innych: "Composer", "Cinematographer", "Editor", "Production Designer"
- **`billing_order`** - kolejność w napisach końcowych (niższy numer = wyżej w napisach)

### Przyszłe Rozszerzenia Ról

Użytkownik wspomniał o dodatkowych relacjach, które mogą być dodane w przyszłości:

1. **Voice Acting (Podkładanie głosów)**
   - Opcja A: Nowa rola `VOICE_ACTOR` w enumie
   - Opcja B: Użycie `role=ACTOR` z `job="Voice Actor"` lub `character_name` wskazującym na postać głosową

2. **Inne role załogi**
   - Mogą być przechowywane w polu `job`:
     - "Composer" (kompozytor)
     - "Cinematographer" (operator kamery)
     - "Editor" (montażysta)
     - "Production Designer" (scenograf)
     - "Costume Designer" (kostiumograf)
     - "Makeup Artist" (charmakier)
     - itp.

3. **Rozszerzenie enumu ról**
   - Jeśli potrzeba osobnych ról (nie tylko w `job`), można dodać:
     - `VOICE_ACTOR`, `COMPOSER`, `CINEMATOGRAPHER`, etc.
   - Wymaga zmiany CHECK constraint w bazie danych

### Przykłady Użycia

**Przykład 1: Aktor z nazwą postaci**
```php
$person->movies()->attach($movie->id, [
    'role' => 'ACTOR',
    'character_name' => 'Neo',
    'billing_order' => 1
]);
```

**Przykład 2: Reżyser z funkcją**
```php
$person->movies()->attach($movie->id, [
    'role' => 'DIRECTOR',
    'job' => 'Director',
    'billing_order' => 1
]);
```

**Przykład 3: Osoba z wieloma rolami**
```php
// Ta sama osoba jako reżyser i producent
$person->movies()->attach($movie->id, [
    'role' => 'DIRECTOR',
    'job' => 'Director',
    'billing_order' => 1
]);
$person->movies()->attach($movie->id, [
    'role' => 'PRODUCER',
    'job' => 'Executive Producer',
    'billing_order' => 2
]);
```

### Wpływ na Related People (Collaborators)

Dla endpointu `/people/{slug}/related?type=collaborators`:

- **Wyszukiwanie współpracowników:** Znajdujemy osoby, które pracowały z daną osobą w tych samych filmach, ale w **różnych rolach**
- **Filtrowanie po roli:** Parametr `?collaborator_role=DIRECTOR` filtruje współpracowników, którzy mieli określoną rolę (np. tylko reżyserzy)
- **Wspólne filmy:** Relacja jest ustalana poprzez `movie_person` - osoby z tym samym `movie_id` ale różnym `person_id` i różnym `role`

---

## Dodatkowe Funkcjonalności dla Person

### 1. Endpoint Related People (`GET /api/v1/people/{slug}/related`)

**Cel:** Pobieranie powiązanych osób (współpracownicy, osoby o podobnym imieniu, etc.).

**Definicja Collaborators:**

**Collaborators** to osoby, które pracowały z daną osobą w tych samych filmach, ale w **różnych rolach**. 

**Przykłady:**
- Dla **aktora:** Reżyserzy, scenarzyści, producenci, którzy pracowali z tym aktorem w tych samych filmach
- Dla **reżysera:** Aktorzy, scenarzyści, producenci, którzy pracowali z tym reżyserem w tych samych filmach
- Dla **scenarzysty:** Reżyserzy, aktorzy, producenci, którzy pracowali z tym scenarzystą w tych samych filmach
- Dla **producenta:** Reżyserzy, aktorzy, scenarzyści, którzy pracowali z tym producentem w tych samych filmach

**Obecne role w systemie (z tabeli `movie_person`):**
- `ACTOR` - aktor (z polem `character_name` dla nazwy postaci)
- `DIRECTOR` - reżyser
- `WRITER` - scenarzysta
- `PRODUCER` - producent

**Relacje people-movies:**

Osoby są powiązane z filmami poprzez tabelę `movie_person` z następującymi danymi:
- `role` - rola (ACTOR, DIRECTOR, WRITER, PRODUCER)
- `character_name` - nazwa postaci (dla ACTOR, np. "Neo", "Trinity")
- `job` - konkretna funkcja (dla crew, np. "Director", "Screenwriter", "Composer", "Cinematographer")
- `billing_order` - kolejność w napisach końcowych

**Uwaga o przyszłych rozszerzeniach ról:**
- **Podkładanie głosów (Voice Acting)** - może być dodane jako nowa rola `VOICE_ACTOR` lub użycie `job="Voice Actor"` dla `role=ACTOR`
- **Inne role załogi** - mogą być przechowywane w polu `job` (np. "Composer", "Cinematographer", "Editor", "Production Designer")

**Możliwe relacje w endpointzie:**
- **Collaborators** - osoby, które pracowały z daną osobą (wspólne filmy, różne role)
  - Filtrowanie po roli: `?collaborator_role=DIRECTOR` (osoby, które reżyserowały filmy z tą osobą)
- **Same Name** - osoby o tym samym imieniu/nazwisku (disambiguation)
- **Similar Movies** - osoby z podobnych filmów (np. z tego samego gatunku) - **Faza 4 (opcjonalne)**

**Przykładowe zapytania:**
```
GET /api/v1/people/{slug}/related?type=collaborators
GET /api/v1/people/{slug}/related?type=collaborators&collaborator_role=DIRECTOR
GET /api/v1/people/{slug}/related?type=same_name
GET /api/v1/people/{slug}/related?type=all
```

**Parametry:**
- `?type=collaborators|same_name|all` (default: `all`)
  - `collaborators` - tylko współpracownicy (wspólne filmy, różne role)
  - `same_name` - tylko osoby o tym samym imieniu/nazwisku (disambiguation)
  - `all` - wszystkie relacje
- `?collaborator_role=ACTOR|DIRECTOR|WRITER|PRODUCER` (tylko dla `type=collaborators` lub `type=all` - filtruje role współpracowników)
- `?limit=10` (limit wyników, default: 20)

**Implementacja Collaborators:**

1. Znajdź wszystkie filmy, w których dana osoba brała udział (poprzez `movie_person` z jej `person_id`)
2. Dla każdego filmu znajdź inne osoby (różne `person_id`) w różnych rolach (różne `role`)
3. Grupuj współpracowników po roli (opcjonalnie filtruj przez `collaborator_role`)
4. Sortuj po liczbie wspólnych filmów (osoby z większą liczbą wspólnych filmów wyżej)

**Implementacja Same Name:**

1. Użyj `PersonRepository::findAllByNameSlug()` (już istnieje)
2. Zwróć osoby o tym samym imieniu/nazwisku (z wykluczeniem danej osoby)

**Implementacja:**
- Nowa metoda w `PersonController::related()`
- Użycie relacji `movies()` do znalezienia współpracowników
- Query: `Person::whereHas('movies', function($q) use ($personMovies) { $q->whereIn('movies.id', $personMovies)->where('person_id', '!=', $personId); })`
- Cache'owanie wyników (tagged cache: `person_related`, TTL: 1 godzina)

**Przykładowa struktura odpowiedzi:**
```json
{
  "person": {
    "id": "...",
    "slug": "keanu-reeves-1964",
    "name": "Keanu Reeves"
  },
  "related_people": [
    {
      "id": "...",
      "slug": "lana-wachowski-1965",
      "name": "Lana Wachowski",
      "relationship_type": "COLLABORATOR",
      "relationship_label": "Collaborator (Director)",
      "collaborations": [
        {
          "movie_id": "...",
          "movie_slug": "the-matrix-1999",
          "movie_title": "The Matrix",
          "person_role": "ACTOR",
          "collaborator_role": "DIRECTOR"
        }
      ],
      "collaborations_count": 3
    }
  ],
  "count": 15,
  "filters": {
    "type": "collaborators",
    "collaborator_role": "DIRECTOR",
    "collaborators_count": 5,
    "same_name_count": 0
  }
}
```

**Pliki do utworzenia:**
- `api/tests/Feature/PersonRelatedTest.php`
- Rozszerzenie `PersonController::related()`
- Opcjonalnie: `PersonRelatedService` (jeśli logika stanie się złożona)

---

### 2. Rozszerzenie wyszukiwania Person o filtry po rolach

**Cel:** Umożliwienie wyszukiwania osób po roli (np. tylko reżyserów, tylko aktorów).

**Parametry:**
- `?role=ACTOR|DIRECTOR|WRITER|PRODUCER` - pojedyncza rola
- `?roles[]=ACTOR&roles[]=DIRECTOR` - wiele ról (OR logic)

**Implementacja:**
- Rozszerzenie `PersonSearchService::search()` o filtrowanie po `movie_person.role`
- Użycie `whereHas('movies', function($q) use ($role) { $q->wherePivot('role', $role); })`
- Obsługa pojedynczego `?role=ACTOR` oraz wielu `?roles[]=ACTOR&roles[]=DIRECTOR` (OR logic)

**Przykład użycia:**
```
GET /api/v1/people/search?q=Christopher&role=DIRECTOR
GET /api/v1/people/search?q=Christopher&roles[]=DIRECTOR&roles[]=WRITER
```

---

### 3. Rozszerzenie wyszukiwania Person o filtry po filmach

**Cel:** Wyszukiwanie osób, które grały/realizowały w konkretnych filmach.

**Parametry:**
- `?movie=slug` - slug filmu
- `?movies[]=slug1&movies[]=slug2` - wiele filmów (OR logic)

**Implementacja:**
- Rozszerzenie `PersonSearchService::search()` o filtrowanie po `movie_person.movie_id`

---

## Dodatkowe Funkcjonalności dla Movie

### 1. Rozszerzenie endpointu Related o filtry po gatunkach

**Cel:** Filtrowanie powiązanych filmów po gatunku.

**Parametry:**
- `?genre=slug` - gatunek (np. `science-fiction`)
- `?genres[]=slug1&genres[]=slug2` - wiele gatunków

**Implementacja:**
- Rozszerzenie `MovieController::related()` o filtrowanie po `genres`

---

### 2. Rozszerzenie wyszukiwania Movie o sortowanie

**Cel:** Sortowanie wyników wyszukiwania.

**Parametry:**
- `?sort=title|release_year|created_at` (default: relevance/confidence)
- `?order=asc|desc` (default: `desc` dla `release_year`, `asc` dla `title`)

**Implementacja:**
- Rozszerzenie `MovieSearchService::search()` o sortowanie

---

### 3. Rozszerzenie wyszukiwania Movie o limit per source

**Cel:** Kontrola liczby wyników z każdego źródła (local vs external).

**Parametry:**
- `?local_limit=20` - limit wyników lokalnych (default: `per_page`)
- `?external_limit=10` - limit wyników external (default: `per_page`)

**Implementacja:**
- Rozszerzenie `MovieSearchService::search()` o osobne limity

---

## Plan Implementacji

### Faza 1: Refaktoryzacja (consistency, code quality)

**Priorytet:** Wysoki  
**Szacowany czas:** 2-3 tygodnie

1. ✅ **PersonSearchService** + endpoint `/people/search`
   - Implementacja `PersonSearchService` (wzorowany na `MovieSearchService`)
   - Utworzenie `SearchPersonRequest`
   - Dodanie endpointu z rate limiting
   - Testy (Unit + Feature)

2. ✅ **PersonRetrievalService**
   - Implementacja `PersonRetrievalService`
   - Utworzenie `PersonRetrievalResult`
   - Refaktoryzacja `PersonController::show()`
   - Testy (Unit + Feature)

3. ✅ **PersonResponseFormatter**
   - Implementacja `PersonResponseFormatter`
   - Refaktoryzacja `PersonController` do użycia formattera
   - Testy (Unit)

4. ✅ **Rate Limiting dla Person**
   - Dodanie middleware do routes
   - Testy (Feature)

---

### Faza 2: Person Reports (nowa funkcjonalność)

**Priorytet:** Wysoki  
**Szacowany czas:** 2-3 tygodnie

5. ✅ **Person Reports - Backend**
   - Migration `person_reports`
   - Model `PersonReport`
   - Service `PersonReportService`
   - Repository `PersonReportRepository`
   - Request `ReportPersonRequest`

6. ✅ **Person Reports - Endpoints**
   - `POST /api/v1/people/{slug}/report`
   - Testy (Feature)

7. ✅ **Person Reports - Admin**
   - `PersonReportController` (admin)
   - Endpoints: `GET /admin/reports/people`, `POST /admin/reports/people/{id}/verify`
   - Action `VerifyPersonReportAction`
   - Job `RegeneratePersonBioJob`
   - Testy (Feature)

---

### Faza 3: Related People (nowa funkcjonalność)

**Priorytet:** Średni  
**Szacowany czas:** 1-2 tygodnie

8. ✅ **Related People Endpoint**
   - `GET /api/v1/people/{slug}/related`
   - Implementacja logiki współpracowników
   - Cache'owanie
   - Testy (Feature)

---

### Faza 4: Rozszerzenia wyszukiwania (UX improvements)

**Priorytet:** Średni/Niski  
**Szacowany czas:** 1-2 tygodnie

9. ✅ **Filtry wyszukiwania Person**
   - `?role=`, `?roles[]=`
   - `?movie=`, `?movies[]=`
   - `?birth_year=`, `?birthplace=`
   - Testy (Feature)

10. ✅ **Rozszerzenia Movie (opcjonalne)**
    - Sortowanie w wyszukiwaniu
    - Filtry po gatunkach w Related
    - Limit per source

---

## Pytania do Rozstrzygnięcia

### 1. PersonReportType vs MovieReportType

**Pytanie:** Czy `PersonReportType` powinien być taki sam jak `MovieReportType` (FACTUAL_ERROR, GRAMMAR_ERROR, INAPPROPRIATE, INCOMPLETE, INCORRECT_INFO, OTHER), czy osobny enum?

**Opcje:**
- A) Jeden wspólny enum `ReportType` (używany przez Movie i Person)
- B) Osobne enumy `MovieReportType` i `PersonReportType` (możliwość różnych typów w przyszłości)
- C) Osobne enumy, ale z podobnymi wartościami (consistency, ale flexibility)

**✅ DECYZJA: A) Jeden wspólny enum `ReportType`**

**Uzasadnienie:** Typy błędów są uniwersalne (błąd faktualny, błąd gramatyczny, nieodpowiednia treść, niekompletna, nieprawidłowe info, inne). Jeśli w przyszłości potrzeba różnych typów, można dodać `category` lub użyć dziedziczenia.

**Akcja:** Refaktoryzacja `MovieReportType` na `ReportType` i użycie go zarówno dla Movie jak i Person.

---

### 2. Rate Limiting dla `GET /people/{slug}`

**Pytanie:** Czy endpoint `GET /api/v1/people/{slug}` powinien mieć ten sam limit co search, czy osobny?

**Opcje:**
- A) Ten sam limit co search (`adaptive.rate.limit:search`)
- B) Osobny limit (np. `adaptive.rate.limit:show` lub `adaptive.rate.limit:person`)
- C) Wyższy limit niż search (bo to prostsze zapytanie)

**✅ DECYZJA: B) Osobny limit `adaptive.rate.limit:show`**

**Uzasadnienie:** Endpoint `show()` jest prostszy (jeden rekord) niż search (wielokrotne zapytania, cache), więc może mieć wyższy limit. Movie już używa różnych limitów dla różnych endpointów. Consistency z Movie.

**Akcja:** Dodanie `adaptive.rate.limit:show` do konfiguracji rate limiting i użycie go dla `GET /people/{slug}` oraz `GET /movies/{slug}`.

---

### 3. Admin Endpoints dla Reports

**Pytanie:** Czy admin endpoints dla Person Reports powinny być w tym samym kontrolerze co Movie Reports, czy osobny?

**Opcje:**
- A) Rozszerzyć `ReportController` o metody dla Person (`indexPeople()`, `verifyPeople()`)
- B) Utworzyć osobny `PersonReportController`
- C) Jeden kontroler, ale uniwersalny (`ReportController::index()` przyjmuje `?entity_type=PERSON|MOVIE`)

**✅ DECYZJA: C) Jeden uniwersalny kontroler z parametrem `entity_type`**

**Uzasadnienie:** Upraszcza kod, zachowuje spójność API, łatwiejsze w utrzymaniu. Jeśli w przyszłości potrzeba różnych logik dla różnych typów, można użyć Strategy pattern.

**Akcja:** Refaktoryzacja `ReportController` aby obsługiwał `?entity_type=PERSON|MOVIE` oraz rozszerzenie `MovieReportRepository` do uniwersalnego `ReportRepository` (lub użycie Strategy pattern).

---

### 4. Cache Key dla Person Search

**Pytanie:** Czy cache key dla Person Search powinien używać tagged cache (`person_search`) jak Movie, czy regular cache?

**✅ DECYZJA: Tagged cache (`person_search`)**

**Uzasadnienie:** Consistency z Movie, łatwiejsze invalidowanie całego cache'u wyszukiwania (np. po dodaniu nowej osoby).

**Akcja:** Użycie `Cache::tags(['person_search'])` w `PersonSearchService`.

---

### 5. Related People - Scope i Collaborators

**Pytanie:** Jakie relacje powinny być obsługiwane w `/people/{slug}/related`? Kim są "Collaborators"?

**✅ DECYZJA: B) Collaborators + Same Name**

**Definicja "Collaborators":**

**Collaborators** to osoby, które pracowały z daną osobą w tych samych filmach, ale w **różnych rolach**. Przykłady:

- **Dla aktora:** Reżyserzy, scenarzyści, producenci, którzy pracowali z tym aktorem w tych samych filmach
- **Dla reżysera:** Aktorzy, scenarzyści, producenci, którzy pracowali z tym reżyserem w tych samych filmach
- **Dla scenarzysty:** Reżyserzy, aktorzy, producenci, którzy pracowali z tym scenarzystą w tych samych filmach
- **Dla producenta:** Reżyserzy, aktorzy, scenarzyści, którzy pracowali z tym producentem w tych samych filmach

**Obecne role w systemie (z tabeli `movie_person`):**
- `ACTOR` - aktor (z polem `character_name` dla nazwy postaci)
- `DIRECTOR` - reżyser
- `WRITER` - scenarzysta
- `PRODUCER` - producent

**Relacje people-movies (obecnie obsługiwane):**

Osoby są powiązane z filmami poprzez tabelę `movie_person` z następującymi danymi:
- `role` - rola (ACTOR, DIRECTOR, WRITER, PRODUCER)
- `character_name` - nazwa postaci (dla ACTOR)
- `job` - konkretna funkcja (dla crew, np. "Director", "Screenwriter")
- `billing_order` - kolejność w napisach końcowych

**Przykład Collaborators:**

Jeśli szukamy powiązanych osób dla **Keanu Reeves** (ACTOR w "The Matrix"):
- **Collaborators:** Lana i Lilly Wachowski (DIRECTOR), Laurence Fishburne (ACTOR w tym samym filmie), itp.

Jeśli szukamy dla **Christopher Nolan** (DIRECTOR w "Inception"):
- **Collaborators:** Leonardo DiCaprio (ACTOR), Hans Zimmer (kompozytor - obecnie nie obsługiwany, ale może być w `job`), itp.

**Parametry endpointu `/people/{slug}/related`:**

```
GET /api/v1/people/{slug}/related?type=collaborators&collaborator_role=DIRECTOR
```

- `?type=collaborators|same_name|all` (default: `all`)
- `?collaborator_role=ACTOR|DIRECTOR|WRITER|PRODUCER` (tylko dla `type=collaborators` - filtruje role współpracowników)
- `?limit=10` (limit wyników)

**Uwaga o przyszłych rozszerzeniach:**

Użytkownik wspomniał o dodatkowych rolach/relacjach:
- **Podkładanie głosów (Voice Acting)** - obecnie nie obsługiwane, ale może być dodane jako nowa rola `VOICE_ACTOR` lub użycie `job="Voice Actor"` dla `role=ACTOR`
- **Inne role załogi** - mogą być przechowywane w polu `job` (np. "Composer", "Cinematographer", "Editor")

**Rekomendacja:** B) Collaborators + Same Name - najprostsze do implementacji, najbardziej użyteczne. Similar Movies można dodać w przyszłości (Faza 4).

**Akcja:** Implementacja endpointu `/people/{slug}/related` z obsługą Collaborators (przez `movie_person` - wspólne filmy, różne role) oraz Same Name (disambiguation).

---

## Podsumowanie

### Główne Cele

1. **Consistency** - Person powinien mieć taką samą architekturę jak Movie (Services, Formatters, Validators)
2. **Feature Parity** - Person powinien mieć takie same funkcjonalności co Movie (search, reports, rate limiting)
3. **Code Quality** - Refaktoryzacja zgodna z zasadami "Thin Controllers", DRY, SOLID
4. **UX** - Dodanie przydatnych funkcjonalności (related, zaawansowane filtry)

### Priorytetyzacja

**Faza 1 (Wysoki priorytet):** Refaktoryzacja - consistency i code quality  
**Faza 2 (Wysoki priorytet):** Person Reports - feature parity  
**Faza 3 (Średni priorytet):** Related People - nowa funkcjonalność  
**Faza 4 (Niski priorytet):** Rozszerzenia - UX improvements

### Szacowany Czas Całkowity

- Faza 1: 2-3 tygodnie
- Faza 2: 2-3 tygodnie
- Faza 3: 1-2 tygodnie
- Faza 4: 1-2 tygodnie
- **Razem: 6-10 tygodni** (w zależności od priorytetyzacji i dostępności czasu)

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


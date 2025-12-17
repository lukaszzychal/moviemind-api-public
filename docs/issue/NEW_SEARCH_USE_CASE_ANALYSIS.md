# Analiza i Propozycje Rozwiązań - Nowy Use Case Wyszukiwania Filmów

> 📋 **Plan implementacji:** Zobacz [NEW_SEARCH_USE_CASE_IMPLEMENTATION_PLAN.md](./NEW_SEARCH_USE_CASE_IMPLEMENTATION_PLAN.md) dla szczegółowego planu podzielonego na etapy i branche.

## 📋 Przegląd Use Case'a

### Główne wymagania:
1. **Wyszukiwanie elastyczne** - użytkownik może podać tytuł, pełną nazwę, rok, reżysera, aktora
2. **Wyszukiwanie hybrydowe** - lokalne + TMDB (główne źródło prawdy)
3. **Obsługa wielu scenariuszy** - jednoznaczne trafienie, wiele wyników, brak wyników
4. **Tworzenie wpisów lokalnych** - uzupełnianie metadanych (aktorzy, producenci, powiązane filmy)
5. **Generowanie opisów AI** - modyfikacja opisu z TMDB zgodnie z context_tag
6. **Zabezpieczenia** - przed halucynacjami AI i XSS/AI injection
7. **Zgłaszanie błędów** - mechanizm raportowania nieprawidłowych danych

---

## 🎯 Propozycje Rozwiązań

### 1. Wyszukiwanie Elastyczne

#### Problem:
Użytkownik może podać różne informacje: "Matrix", "The Matrix Reloaded", "Matrix 2003", "Matrix Wachowski", "Matrix Keanu Reeves"

#### Rozwiązanie A: Endpoint z parametrami query (REKOMENDOWANE)
```http
GET /api/v1/movies/search?q=matrix&year=2003&director=wachowski&actor=keanu
```

**Zalety:**
- Elastyczne - można podać dowolne kombinacje parametrów
- RESTful - zgodne z konwencjami
- Łatwe do cache'owania
- Można rozszerzyć o dodatkowe parametry

**Wady:**
- Dłuższe URL-e przy wielu parametrach

#### Rozwiązanie B: Endpoint z body (POST)
```http
POST /api/v1/movies/search
{
  "title": "Matrix",
  "year": 2003,
  "director": "Wachowski",
  "actors": ["Keanu Reeves", "Laurence Fishburne"]
}
```

**Zalety:**
- Bardziej złożone zapytania
- Lepsze dla wielu aktorów

**Wady:**
- POST dla wyszukiwania jest mniej RESTful
- Trudniejsze cache'owanie

#### Rozwiązanie C: Slug z parametrami (hybrydowe)
```http
GET /api/v1/movies/matrix-2003?director=wachowski&actor=keanu
```

**Zalety:**
- Krótkie URL-e dla podstawowych przypadków
- Możliwość rozszerzenia parametrami

**Wady:**
- Mniej elastyczne niż query params
- Slug może być mylący przy wielu parametrach

**REKOMENDACJA: Rozwiązanie A** - najbardziej elastyczne i zgodne z REST

---

### 2. Wyszukiwanie Hybrydowe (Lokalne + TMDB)

#### Problem:
Jak połączyć wyniki lokalne z TMDB, gdy:
- Film jest lokalnie, ale nie ma na TMDB (stary wpis)
- Film jest na TMDB, ale nie ma lokalnie (nowy film)
- Film jest w obu miejscach (synchronizacja)

#### Rozwiązanie: Asynchroniczne wyszukiwanie z merge'owaniem

**Flow:**
```
1. Wyszukaj lokalnie (szybkie, synchroniczne)
2. Wyszukaj na TMDB (może być wolniejsze, synchroniczne lub async)
3. Merge wyników:
   - Priorytet: lokalne (jeśli istnieje)
   - Uzupełnij: TMDB (jeśli brakuje lokalnie)
   - Oznacz: nowe filmy z TMDB (do utworzenia)
```

**Implementacja:**

```php
class MovieSearchService
{
    public function search(array $criteria): SearchResult
    {
        // 1. Wyszukaj lokalnie
        $localResults = $this->searchLocal($criteria);
        
        // 2. Wyszukaj na TMDB
        $tmdbResults = $this->searchTmdb($criteria);
        
        // 3. Merge i deduplikacja
        return $this->mergeResults($localResults, $tmdbResults);
    }
    
    private function mergeResults($local, $tmdb): SearchResult
    {
        $merged = [];
        $tmdbIds = [];
        
        // Dodaj lokalne (priorytet)
        foreach ($local as $movie) {
            $merged[] = [
                'source' => 'local',
                'movie' => $movie,
                'tmdb_id' => $movie->tmdbSnapshot?->tmdb_id,
            ];
            if ($movie->tmdbSnapshot) {
                $tmdbIds[] = $movie->tmdbSnapshot->tmdb_id;
            }
        }
        
        // Dodaj z TMDB tylko te, których nie ma lokalnie
        foreach ($tmdb as $tmdbMovie) {
            if (!in_array($tmdbMovie['id'], $tmdbIds)) {
                $merged[] = [
                    'source' => 'tmdb',
                    'tmdb_data' => $tmdbMovie,
                    'needs_creation' => true,
                ];
            }
        }
        
        return new SearchResult($merged);
    }
}
```

**Odpowiedź API (BEZ tmdb_id):**
```json
{
  "results": [
    {
      "source": "local",
      "movie": { /* pełne dane lokalne */ },
      "slug": "the-matrix-1999",
      "has_description": true
    },
    {
      "source": "external",
      "title": "The Matrix Resurrections",
      "release_year": 2021,
      "overview": "...",
      "needs_creation": true,
      "suggested_slug": "the-matrix-resurrections-2021"
    }
  ],
  "total": 2,
  "local_count": 1,
  "external_count": 1
}
```

---

### 3. Obsługa Scenariuszy (Jednoznaczne / Wiele / Brak)

#### Problem:
Jak obsłużyć różne scenariusze wyników wyszukiwania?

#### Rozwiązanie: Status codes + struktura odpowiedzi

**Scenariusz 1: Jednoznaczne trafienie (100% pewność)**
```http
GET /api/v1/movies/search?q=matrix&year=1999
→ 200 OK
{
  "match_type": "exact",
  "confidence": 1.0,
  "result": { /* pełne dane filmu */ }
}
```

**Scenariusz 2: Wiele wyników (disambiguation)**
```http
GET /api/v1/movies/search?q=matrix
→ 300 Multiple Choices
{
  "match_type": "ambiguous",
  "count": 4,
  "results": [
    { "title": "The Matrix", "year": 1999, "slug": "the-matrix-1999", "source": "local" },
    { "title": "The Matrix Reloaded", "year": 2003, "slug": "the-matrix-reloaded-2003", "source": "local" },
    { "title": "The Matrix Revolutions", "year": 2003, "slug": "the-matrix-revolutions-2003", "source": "external" },
    { "title": "The Matrix Resurrections", "year": 2021, "slug": "the-matrix-resurrections-2021", "source": "external" }
  ],
  "hint": "Use ?slug={slug} to select specific movie or GET /api/v1/movies/{slug}"
}
```

**Scenariusz 3: Brak wyników**
```http
GET /api/v1/movies/search?q=nieistniejacy-film-xyz
→ 404 Not Found
{
  "match_type": "none",
  "message": "No movies found matching your criteria",
  "suggestions": [ /* podobne tytuły */ ]
}
```

**Scenariusz 4: Częściowe trafienie (można uzupełnić)**
```http
GET /api/v1/movies/search?q=matrix&year=2003
→ 200 OK (ale z warning)
{
  "match_type": "partial",
  "confidence": 0.85,
  "warning": "Found multiple Matrix movies from 2003",
  "result": { /* najlepsze dopasowanie */ },
  "alternatives": [ /* inne opcje */ ]
}
```

---

### 4. Tworzenie Wpisów Lokalnych i Uzupełnianie Metadanych

#### Problem:
- Czy tworzyć obiekty Person/Actor od razu?
- Czy zapisywać tylko nazwiska jako stringi?
- Jak obsłużyć powiązane filmy?

#### Rozwiązanie: Lazy Creation + Full Sync

**Strategia:**
1. **Podstawowe metadane** - tworzone od razu (title, year, director, genres)
2. **Aktorzy/Person** - lazy creation (tylko gdy potrzebne)
3. **Powiązane filmy** - asynchroniczne uzupełnianie

**Implementacja:**

```php
class TmdbMovieCreationService
{
    public function createFromTmdb(array $tmdbData, string $requestSlug): Movie
    {
        // 1. Podstawowe metadane (synchroniczne)
        $movie = Movie::create([
            'title' => $tmdbData['title'],
            'slug' => Movie::generateSlug(...),
            'release_year' => $year,
            'director' => $tmdbData['director'],
            'genres' => $this->extractGenres($tmdbData),
        ]);
        
        // 2. Zapisuj snapshot TMDB
        $this->saveTmdbSnapshot($movie, $tmdbData);
        
        // 3. Queue job dla pełnej synchronizacji (async)
        SyncMovieMetadataJob::dispatch($movie->id, $tmdbData['id']);
        
        return $movie;
    }
}

class SyncMovieMetadataJob implements ShouldQueue
{
    public function handle(): void
    {
        // 1. Pobierz pełne dane z TMDB (cast, crew, related movies)
        $fullData = $this->tmdbService->getMovieDetails($tmdbId);
        
        // 2. Utwórz/znajdź Person dla aktorów
        foreach ($fullData['cast'] as $actor) {
            $person = Person::firstOrCreate(
                ['tmdb_id' => $actor['id']], // jeśli mamy tmdb_id w people
                ['name' => $actor['name'], 'slug' => Person::generateSlug($actor['name'])]
            );
            
            $movie->people()->attach($person->id, [
                'role' => 'ACTOR',
                'character_name' => $actor['character'],
                'billing_order' => $actor['order'],
            ]);
        }
        
        // 3. Utwórz/znajdź Person dla crew (director, writer, producer)
        // 4. Powiązane filmy (series, sequels) - jako osobne joby
    }
}
```

**Alternatywa: Proxy Pattern (tylko nazwiska)**
- Jeśli nie chcemy pełnych obiektów Person od razu:
```php
// W tabeli movies
'cast_names' => ['Keanu Reeves', 'Laurence Fishburne'], // JSON array
'crew_names' => ['Lana Wachowski', 'Lilly Wachowski'], // JSON array
```

**REKOMENDACJA: Full Objects (Person)** - bardziej elastyczne, lepsze dla przyszłych funkcji

---

### 5. Generowanie Opisów AI z Context Tag

#### Problem:
- Jak zmodyfikować opis z TMDB zgodnie z context_tag (humor, modern, critical)?
- Jak uniknąć halucynacji AI?
- Jak zabezpieczyć przed AI injection/XSS?

#### Rozwiązanie: Prompt Engineering + Validation

**Flow:**
```
1. Pobierz overview z TMDB
2. Wygeneruj prompt z context_tag
3. Wywołaj AI z zabezpieczeniami
4. Waliduj wynik (długość, format, bezpieczeństwo)
5. Zapisz opis
```

**Implementacja:**

```php
class GenerateMovieDescriptionJob implements ShouldQueue
{
    public function handle(): void
    {
        $tmdbData = $this->getTmdbData($movie);
        $originalOverview = $tmdbData['overview'];
        
        // 1. Przygotuj prompt z zabezpieczeniami
        $prompt = $this->buildPrompt($originalOverview, $contextTag);
        
        // 2. Wywołaj AI z rate limiting i retry
        $generated = $this->aiService->generate($prompt, [
            'max_tokens' => 500,
            'temperature' => 0.7,
            'system_prompt' => $this->getSystemPrompt($contextTag),
        ]);
        
        // 3. Waliduj i sanitize
        $validated = $this->validateAndSanitize($generated);
        
        // 4. Zapisz
        MovieDescription::create([
            'movie_id' => $movie->id,
            'locale' => $locale,
            'text' => $validated,
            'context_tag' => $contextTag,
            'origin' => 'GENERATED',
            'ai_model' => 'gpt-4o-mini',
        ]);
    }
    
    private function buildPrompt(string $overview, string $contextTag): string
    {
        // System prompt z zabezpieczeniami
        $systemPrompt = match($contextTag) {
            'humor' => "You are a witty movie critic. Rewrite the movie description in a humorous style. Do NOT invent facts. Only use information from the provided overview.",
            'modern' => "You are a modern film critic. Rewrite the description in contemporary language. Do NOT invent facts.",
            'critical' => "You are a critical film analyst. Provide a critical analysis. Do NOT invent facts.",
            default => "Rewrite the movie description. Do NOT invent facts.",
        };
        
        // User prompt z oryginalnym opisem (sanitized)
        $userPrompt = "Original description:\n\n" . 
                     $this->sanitizeInput($overview) . 
                     "\n\nRewrite this description in {$contextTag} style. " .
                     "IMPORTANT: Only use facts from the original description. " .
                     "Do not add new information, characters, or plot points.";
        
        return [
            'system' => $systemPrompt,
            'user' => $userPrompt,
        ];
    }
    
    private function validateAndSanitize(string $text): string
    {
        // 1. Sprawdź długość
        if (strlen($text) > 2000) {
            throw new ValidationException('Generated text too long');
        }
        
        // 2. Sprawdź czy nie zawiera podejrzanych wzorców (AI injection)
        if ($this->detectAiInjection($text)) {
            throw new SecurityException('Potential AI injection detected');
        }
        
        // 3. Sanitize HTML/XSS
        $text = strip_tags($text); // Usuń HTML
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); // Escape
        
        // 4. Sprawdź czy nie zawiera zbyt dużo nowych informacji (halucynacje)
        // Porównaj z oryginalnym opisem (similarity check)
        $similarity = $this->calculateSimilarity($text, $this->originalOverview);
        if ($similarity < 0.3) {
            throw new ValidationException('Generated text too different from original (possible hallucination)');
        }
        
        return $text;
    }
    
    private function detectAiInjection(string $text): bool
    {
        // Wzorce podejrzane (przykłady)
        $patterns = [
            '/ignore previous instructions/i',
            '/forget everything/i',
            '/new instructions/i',
            '/system prompt/i',
            '/<script/i',
            '/javascript:/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text)) {
                return true;
            }
        }
        
        return false;
    }
}
```

**Zabezpieczenia:**
1. **System Prompt** - jasne instrukcje dla AI
2. **Input Sanitization** - czyszczenie danych wejściowych
3. **Output Validation** - sprawdzanie wyniku
4. **Similarity Check** - porównanie z oryginałem
5. **Length Limits** - ograniczenie długości
6. **Pattern Detection** - wykrywanie injection

---

### 6. Endpointy API

#### Propozycja Endpointów:

**1. Wyszukiwanie (GET)**
```http
GET /api/v1/movies/search?q={query}&year={year}&director={director}&actor={actor}
```
- `q` - główne zapytanie (tytuł, część tytułu)
- `year` - rok produkcji (opcjonalny)
- `director` - reżyser (opcjonalny)
- `actor` - aktor (opcjonalny, może być wiele: `actor[]=keanu&actor[]=laurence`)
- `limit` - limit wyników (domyślnie 20)
- `include_tmdb` - czy włączyć wyniki z TMDB (domyślnie true)

**Odpowiedzi:**
- `200 OK` - jednoznaczne trafienie lub lista wyników
- `300 Multiple Choices` - wiele wyników (disambiguation)
- `404 Not Found` - brak wyników

**2. Pobranie filmu (GET) - istniejący**
```http
GET /api/v1/movies/{slug}?description_id={id}
```
- Bez zmian - już istnieje

**3. Generowanie opisu (POST) - istniejący**
```http
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999",
  "locale": "pl-PL",
  "context_tag": "humor"
}
```
- Bez zmian - już istnieje

**4. Zgłaszanie błędu (POST) - NOWY**
```http
POST /api/v1/movies/{slug}/report
{
  "type": "incorrect_description" | "incorrect_metadata" | "missing_data" | "other",
  "description_id": 123, // opcjonalne, jeśli dotyczy konkretnego opisu
  "message": "Opis zawiera nieprawidłowe informacje o zakończeniu filmu",
  "suggested_fix": "Powinno być: ..." // opcjonalne
}
```

**Odpowiedzi:**
- `201 Created` - zgłoszenie przyjęte
- `400 Bad Request` - nieprawidłowe dane

**Struktura odpowiedzi:**
```json
{
  "id": 456,
  "status": "pending" | "reviewed" | "resolved" | "rejected",
  "message": "Report submitted successfully",
  "report_url": "/api/v1/reports/456"
}
```

**5. Tworzenie filmu z TMDB (POST) - NOWY (opcjonalny)**
```http
POST /api/v1/movies/create
{
  "tmdb_id": 603,
  "locale": "pl-PL",
  "context_tag": "modern"
}
```

**Flow:**
1. Pobierz dane z TMDB
2. Utwórz film lokalnie
3. Queue job dla generowania opisu
4. Zwróć 202 Accepted

---

### 7. Slug Generation - Ulepszenia

#### Problem:
Jak budować dobre, unikalne slugi?

#### Obecne rozwiązanie:
- Format: `title-year-director` lub `title-year-2`
- Automatyczne rozwiązywanie duplikatów

#### Propozycje ulepszeń:

**1. TMDB ID w slug (opcjonalnie)**
```
the-matrix-1999-tmdb603
```
- Zalety: 100% unikalność
- Wady: Długie, mniej czytelne

**2. Hash suffix (dla duplikatów)**
```
the-matrix-1999-a1b2c3
```
- Zalety: Krótkie, unikalne
- Wady: Mniej czytelne

**3. Numeracja sekwencyjna (obecne)**
```
the-matrix-1999-2
```
- Zalety: Proste, czytelne
- Wady: Może być mylące

**REKOMENDACJA: Zostaw obecne rozwiązanie** - działa dobrze, jest czytelne

**Dodatkowe ulepszenie:**
- Sprawdzanie podobieństwa slugów przed utworzeniem
- Sugerowanie podobnych slugów jeśli istnieją

---

### 8. Asynchroniczne Przetwarzanie

#### Problem:
Wyszukiwanie TMDB może być wolne. Jak to zrobić asynchronicznie?

#### Rozwiązanie: Hybrid Approach

**Opcja A: Synchroniczne (obecne)**
- Wyszukiwanie lokalne: synchroniczne (szybkie)
- Wyszukiwanie TMDB: synchroniczne (może być wolne)
- **Zalety:** Proste, natychmiastowe wyniki
- **Wady:** Może być wolne przy wielu zapytaniach

**Opcja B: Asynchroniczne (cache + background)**
- Wyszukiwanie lokalne: synchroniczne
- Wyszukiwanie TMDB: z cache (jeśli dostępne) lub queue job
- **Zalety:** Szybsze odpowiedzi
- **Wady:** Złożoność, może brakować wyników w pierwszej odpowiedzi

**Opcja C: Streaming/WebSocket (zaawansowane)**
- Najpierw lokalne wyniki
- Potem TMDB wyniki przychodzą asynchronicznie
- **Zalety:** Najlepsze UX
- **Wady:** Bardzo złożone, wymaga WebSocket

**REKOMENDACJA: Opcja A z cache'owaniem**
- Cache wyników wyszukiwania TMDB (TTL: 1h)
- Jeśli cache miss, synchroniczne zapytanie
- Background job do odświeżania cache dla popularnych zapytań

---

## 🔒 Bezpieczeństwo

### 1. AI Injection Prevention
- Sanitizacja inputów
- Wykrywanie podejrzanych wzorców
- System prompts z zabezpieczeniami
- Rate limiting

### 2. XSS Prevention
- `htmlspecialchars()` na outputach
- `strip_tags()` na opisach
- Content Security Policy

### 3. Input Validation
- Walidacja slugów (SlugValidator)
- Walidacja parametrów wyszukiwania
- Ograniczenie długości zapytań

---

## 📊 Przykładowe Flow

### Flow 1: Wyszukiwanie "Matrix"
```
1. GET /api/v1/movies/search?q=matrix
2. Wyszukaj lokalnie → 2 filmy
3. Wyszukaj TMDB → 4 filmy (cache lub synchroniczne)
4. Merge → 4 filmy (2 lokalne + 2 nowe z TMDB)
5. 300 Multiple Choices z listą (BEZ tmdb_id, tylko slugi)
6. Użytkownik wybiera: ?slug=the-matrix-1999 lub wybiera z listy
7. Utwórz film lokalnie (jeśli nie istnieje) - ASYNC
8. Queue job dla opisu (dla wielu context_tag jednocześnie)
9. 202 Accepted
```

### Flow 2: Wyszukiwanie "Matrix 1999"
```
1. GET /api/v1/movies/search?q=matrix&year=1999
2. Wyszukaj lokalnie → 1 film (the-matrix-1999)
3. Wyszukaj TMDB → 1 film (cache lub synchroniczne)
4. Merge → 1 film (lokalny, potwierdzony z TMDB)
5. 200 OK z pełnymi danymi (BEZ tmdb_id)
```

### Flow 3: Zgłoszenie błędu
```
1. POST /api/v1/movies/the-matrix-1999/report
2. Walidacja danych
3. Utwórz Report record (status: pending)
4. Queue job dla administratora (notification)
5. 201 Created
6. Administrator weryfikuje → status: verified
7. Automatyczna regeneracja opisu (queue job)
8. Status: resolved
```

---

## ✅ Ustalone Decyzje

### 0. Ukrycie TMDB w API
**DECYZJA:** Użytkownik nie może wiedzieć, że aplikacja korzysta z TMDB lub innych zewnętrznych serwisów.
- **tmdb_id nie może być widoczne w odpowiedziach API**
- Używać tylko slugów i lokalnych ID
- W disambiguation używać slugów zamiast tmdb_id
- TMDB jest tylko wewnętrznym źródłem danych

### 1. Wielokrotne generowanie opisów
**DECYZJA:** Jednoczesne generowanie wielu opisów dla różnych context_tag jest dozwolone.
- Można generować jednocześnie dla: `default`, `modern`, `romantic`, `sciFi`, `comedy` itp.
- Każdy context_tag = osobny job w kolejce
- Brak limitu liczby opisów na film (ale rozsądne ograniczenie może być dodane później)

### 2. Synchronizacja aktorów z TMDB
**DECYZJA:** ✅ **Opcja A** - Tylko metadane filmu przy `/refresh`

**Implementacja:**
- **Przy pierwszym utworzeniu filmu:** Pobierz pełne dane z TMDB (cast, crew) → utwórz obiekty Person → połącz z filmem (ASYNC via `SyncMovieMetadataJob`)
- **Przy odświeżeniu (`/refresh`):** Tylko aktualizuj metadane filmu (tytuł, rok, reżyser, genres) - **NIE synchronizuj aktorów ponownie**
- **Uzasadnienie:** Aktorzy są rzadko zmieniane, a pełna synchronizacja może być kosztowna. Jeśli potrzebna jest aktualizacja aktorów, można dodać osobny endpoint `/api/v1/movies/{slug}/sync-cast` w przyszłości.

### 3. Powiązane filmy (Sequels/Prequels)
**DECYZJA:** ✅ **Opcja A** - Automatyczne wykrywanie i linkowanie

**Implementacja:**
- **Typy relacji:**
  - `SEQUEL` - kontynuacje (Matrix → Matrix Reloaded → Matrix Revolutions)
  - `PREQUEL` - prequele (Star Wars Ep. 4 → Ep. 1-3)
  - `REMAKE` - remaki (The Matrix 1999 → The Matrix Resurrections 2021)
  - `SERIES` - filmy w serii (Harry Potter 1-8)
  - `SPINOFF` - spin-offy
  - `SAME_UNIVERSE` - ten sam uniwersum

- **Wymagane zmiany:**
  - Nowa tabela `movie_relationships`:
    ```sql
    movie_relationships
    ├── id (PK)
    ├── movie_id (FK)
    ├── related_movie_id (FK)
    ├── relationship_type (ENUM: SEQUEL, PREQUEL, REMAKE, SERIES, SPINOFF, SAME_UNIVERSE)
    ├── order (nullable, dla sequels/prequels - kolejność w serii)
    └── created_at
    ```
  - Async job `SyncMovieRelationshipsJob` - wykrywa z TMDB (collection_id, related movies)
  - Endpoint: `GET /api/v1/movies/{slug}/related?type=SEQUEL` - pobieranie powiązanych filmów
  - Wykrywanie przy pierwszym utworzeniu filmu (ASYNC)

**WYJAŚNIENIE endpointu `/related`:**
- **Opcja A:** Zwraca wszystkie typy relacji (SEQUEL, PREQUEL, REMAKE, SERIES, itp.) w jednej odpowiedzi
- **Opcja B:** Filtrowane po typie relacji przez query parameter `?type=SEQUEL`

**REKOMENDACJA: Opcja B (z filtrowaniem) + domyślnie wszystkie**
- Domyślnie: `GET /api/v1/movies/{slug}/related` → zwraca wszystkie typy relacji
- Z filtrem: `GET /api/v1/movies/{slug}/related?type=SEQUEL` → zwraca tylko sequels
- Możliwość wielu filtrów: `?type[]=SEQUEL&type[]=PREQUEL` → zwraca sequels i prequels
- **Uzasadnienie:** 
  - Elastyczność - użytkownik może wybrać co chce zobaczyć
  - Wydajność - mniej danych do przetworzenia przy filtrowaniu
  - Lepsze UX - można szybko znaleźć konkretny typ relacji

**Implementacja:**
```php
// GET /api/v1/movies/{slug}/related
public function related(string $slug, Request $request): JsonResponse
{
    $movie = $this->movieRepository->findBySlug($slug);
    if (!$movie) {
        return response()->json(['error' => 'Movie not found'], 404);
    }
    
    $types = $request->query('type', []); // Domyślnie wszystkie
    if (!is_array($types)) {
        $types = [$types]; // Pojedynczy typ jako array
    }
    
    $query = $movie->relatedMovies();
    if (!empty($types)) {
        $query->whereIn('relationship_type', $types);
    }
    
    $related = $query->get()->map(function ($relatedMovie) {
        return [
            'slug' => $relatedMovie->slug,
            'title' => $relatedMovie->title,
            'release_year' => $relatedMovie->release_year,
            'relationship_type' => $relatedMovie->pivot->relationship_type,
            'order' => $relatedMovie->pivot->order,
        ];
    });
    
    return response()->json([
        'movie' => ['slug' => $movie->slug, 'title' => $movie->title],
        'related' => $related,
        'filters' => $types,
    ]);
}
```

### 4. Zgłaszanie błędów
**DECYZJA:** ✅ **Opcja B** - Wymaga akceptacji administratora + **PRIORYTETYZACJA**

**Implementacja:**
- **Flow:** Zgłoszenie → Status `pending` → Admin review → Status `verified` → Automatyczna regeneracja → Status `resolved`
- **Priorytetyzacja:**
  - Częstsze zgłoszenia tego samego błędu = wyższy priorytet naprawy
  - System liczy liczbę zgłoszeń dla tego samego filmu + typ błędu + description_id (jeśli dotyczy opisu)
  - Priorytet obliczany jako: `priority_score = count(reports) * weight(type)`
  - Wagi typów błędów:
    - `incorrect_description`: 3.0
    - `incorrect_metadata`: 2.0
    - `missing_data`: 1.5
    - `other`: 1.0

- **Struktura tabeli `movie_reports`:**
  ```sql
  movie_reports
  ├── id (PK)
  ├── movie_id (FK)
  ├── description_id (FK, nullable - jeśli dotyczy konkretnego opisu)
  ├── type (ENUM: incorrect_description, incorrect_metadata, missing_data, other)
  ├── message (TEXT)
  ├── suggested_fix (TEXT, nullable)
  ├── status (ENUM: pending, verified, resolved, rejected)
  ├── priority_score (FLOAT, calculated) -- WIDOCZNY W API DLA ADMINÓW
  ├── verified_by (FK to users, nullable)
  ├── verified_at (TIMESTAMP, nullable)
  ├── resolved_at (TIMESTAMP, nullable)
  └── created_at
  ```

- **Endpoint:** `POST /api/v1/movies/{slug}/report`
- **Admin endpoint:** `GET /api/v1/admin/reports?priority=high&status=pending` - lista zgłoszeń posortowana po priorytecie
  - **Odpowiedź zawiera `priority_score`** - widoczny dla adminów w API
  - Sortowanie domyślne: `priority_score DESC, created_at DESC`
- **Automatyczna regeneracja:** Po weryfikacji (status `verified`) → queue job `RegenerateMovieDescriptionJob`

**Priorytet w odpowiedzi API dla adminów:**
```json
{
  "reports": [
    {
      "id": 123,
      "movie": {"slug": "the-matrix-1999", "title": "The Matrix"},
      "type": "incorrect_description",
      "message": "Opis zawiera nieprawidłowe informacje",
      "status": "pending",
      "priority_score": 9.0,  // ← WIDOCZNY DLA ADMINÓW
      "priority_level": "high", // ← Dodatkowe pole dla łatwiejszej identyfikacji
      "duplicate_count": 3, // ← Liczba podobnych zgłoszeń
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "total": 15,
    "high_priority": 5,
    "pending": 8
  }
}
```

### 5. Cache
**DECYZJA:** 
- Cache wyników wyszukiwania TMDB: **1h TTL** ✅
- Cache wygenerowanych opisów AI: Tak (obecnie już jest w MovieController)

### 6. Rate Limiting
**DECYZJA:**
- **Lokalnie:** Konfiguracja w `config/rate-limiting.php` z auto-dostosowaniem do obciążenia
- **Dla TMDB:** Rate limiting zgodny z limitami API TMDB (40 requests/10s) - już zaimplementowane
- **Auto-dostosowanie:** Implementacja adaptive rate limiting (zmniejsza limity przy wysokim obciążeniu)

**WYJAŚNIENIE:**
Rate limiting ma dwa poziomy:
1. **Lokalny (API endpoints)** - ogranicza liczbę requestów do naszego API
2. **Zewnętrzny (TMDB)** - już zaimplementowane (40 req/10s)

**Proponowane wartości domyślne:**
- **SEARCH:** 100 requests/minutę (wysokie, bo wyszukiwanie jest szybkie i cache'owane)
- **GENERATE:** 10 requests/minutę (niski, bo generowanie jest kosztowne i długotrwałe)

**Implementacja z auto-dostosowaniem:**
```php
// config/rate-limiting.php
return [
    'search' => [
        'max_attempts' => env('RATE_LIMIT_SEARCH', 100),
        'decay_minutes' => 1,
        'adaptive' => env('RATE_LIMIT_ADAPTIVE', true),
        'min_attempts' => 20, // minimum przy wysokim obciążeniu
        'max_attempts_high_load' => 50, // zmniejszone przy wysokim obciążeniu
    ],

    'generate' => [
        'max_attempts' => env('RATE_LIMIT_GENERATE', 10),
        'decay_minutes' => 1,
        'adaptive' => env('RATE_LIMIT_ADAPTIVE', true),
        'min_attempts' => 3, // minimum przy wysokim obciążeniu
        'max_attempts_high_load' => 5, // zmniejszone przy wysokim obciążeniu
    ],
    
    'report' => [
        'max_attempts' => env('RATE_LIMIT_REPORT', 20),
        'decay_minutes' => 1,
    ],
];
```

**Adaptive Rate Limiting - Implementacja:**
```php
class AdaptiveRateLimiter
{
    public function getMaxAttempts(string $endpoint): int
    {
        $config = config("rate-limiting.{$endpoint}");
        $baseLimit = $config['max_attempts'];
        
        if (!$config['adaptive']) {
            return $baseLimit;
        }
        
        // Sprawdź obciążenie systemu
        $systemLoad = $this->getSystemLoad();
        $queueSize = $this->getQueueSize();
        $activeJobs = $this->getActiveJobsCount();
        
        // Oblicz współczynnik obciążenia (0.0 - 1.0)
        $loadFactor = min(1.0, ($systemLoad + $queueSize * 0.1 + $activeJobs * 0.05) / 100);
        
        // Jeśli obciążenie > 70%, zmniejsz limity
        if ($loadFactor > 0.7) {
            $reducedLimit = $config['max_attempts_high_load'] ?? ($baseLimit * 0.5);
            return max($config['min_attempts'] ?? ($baseLimit * 0.2), $reducedLimit);
        }
        
        return $baseLimit;
    }
    
    private function getSystemLoad(): float
    {
        // CPU load (0-100)
        return sys_getloadavg()[0] * 100 / 4; // 4 cores
    }
    
    private function getQueueSize(): int
    {
        // Liczba jobów w kolejce
        return \Illuminate\Support\Facades\Queue::size();
    }
    
    private function getActiveJobsCount(): int
    {
        // Liczba aktywnych jobów (Horizon)
        return \Laravel\Horizon\Horizon::current()->activeJobsCount() ?? 0;
    }
}
```

**Middleware:**
```php
// app/Http/Middleware/AdaptiveRateLimit.php
class AdaptiveRateLimit
{
    public function handle($request, Closure $next, string $endpoint)
    {
        $limiter = app(AdaptiveRateLimiter::class);
        $maxAttempts = $limiter->getMaxAttempts($endpoint);
        
        $key = $request->user()?->id ?? $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return response()->json([
                'error' => 'Too many requests',
                'retry_after' => RateLimiter::availableIn($key),
            ], 429);
        }
        
        RateLimiter::hit($key, 60); // 1 minute decay
        
        return $next($request);
    }
}
```

---

## 🎯 Finalne Decyzje i Rekomendacje

1. **Endpoint wyszukiwania:** `GET /api/v1/movies/search?q=...&year=...` (bez tmdb_id w odpowiedzi)
2. **Slugi:** Zostaw obecne rozwiązanie (title-year-director)
3. **Aktorzy:** Full objects (Person) z lazy creation, synchronizacja tylko przy pierwszym utworzeniu
4. **Generowanie:** Asynchroniczne, jednoczesne dla wielu context_tag, z walidacją i zabezpieczeniami
5. **Zgłaszanie błędów:** Endpoint `/api/v1/movies/{slug}/report`, automatyczna regeneracja po weryfikacji
6. **Cache:** Redis cache dla wyników TMDB (TTL: 1h)
7. **Bezpieczeństwo:** Multi-layer (input sanitization, output validation, pattern detection)
8. **Ukrycie TMDB:** Brak tmdb_id w odpowiedziach API, tylko slugi
9. **Rate Limiting:** Konfigurowalne lokalnie z auto-dostosowaniem
10. **Powiązane filmy:** ✅ **Opcja B** - Endpoint `/related` z filtrowaniem po typie (domyślnie wszystkie)
11. **Synchronizacja aktorów przy refresh:** ✅ **Opcja A** - Tylko metadane filmu
12. **Weryfikacja zgłoszeń błędów:** ✅ **Opcja B** - Wymaga admina + priorytetyzacja (widoczna w API)
13. **Rate Limiting:** ✅ Domyślne wartości: SEARCH=100/min, GENERATE=10/min + auto-dostosowanie

---

## 📝 Następne Kroki

### Faza 1: Podstawowe wyszukiwanie
1. ✅ Utworzyć `MovieSearchService` z merge'owaniem wyników (BEZ tmdb_id w odpowiedzi)
2. ✅ Dodać endpoint `/api/v1/movies/search` (query params: q, year, director, actor)
3. ✅ Ukryć tmdb_id w wszystkich odpowiedziach API (użyć tylko slugów)
4. ✅ Dodać cache'owanie wyników wyszukiwania TMDB (TTL: 1h)

### Faza 2: Tworzenie i synchronizacja
5. ✅ Rozszerzyć `TmdbMovieCreationService` o pełną synchronizację metadanych (ASYNC)
6. ✅ Utworzyć `SyncMovieMetadataJob` dla aktorów/crew (lazy creation)
7. ✅ Utworzyć tabelę `movie_relationships` i model `MovieRelationship`
8. ✅ Utworzyć `SyncMovieRelationshipsJob` dla automatycznego wykrywania powiązanych filmów (ASYNC)
9. ✅ Dodać endpoint `GET /api/v1/movies/{slug}/related` z filtrowaniem po typie
10. ✅ Zaktualizować `/refresh` - tylko metadane filmu (bez synchronizacji aktorów)

### Faza 3: Generowanie opisów
9. ✅ Dodać walidację i zabezpieczenia do generowania opisów (AI injection, XSS)
10. ✅ Wsparcie dla jednoczesnego generowania wielu context_tag
11. ✅ Implementacja system promptów z zabezpieczeniami

### Faza 4: Zgłaszanie błędów
12. ✅ Utworzyć model `MovieReport` i endpoint `/api/v1/movies/{slug}/report`
13. ✅ Implementacja priorytetyzacji (priority_score widoczny w API dla adminów)
14. ✅ Admin endpoint `GET /api/v1/admin/reports` z sortowaniem po priorytecie
15. ✅ Automatyczna regeneracja po weryfikacji (status `verified` → queue job)

### Faza 5: Rate Limiting
15. ✅ Utworzyć `config/rate-limiting.php` z domyślnymi wartościami (SEARCH=100/min, GENERATE=10/min)
16. ✅ Implementacja `AdaptiveRateLimiter` service (auto-dostosowanie do obciążenia)
17. ✅ Utworzyć middleware `AdaptiveRateLimit` dla endpointów
18. ✅ Dodać monitoring obciążenia systemu (CPU, queue size, active jobs)

### Faza 6: Testy
17. ✅ Napisać testy dla wszystkich scenariuszy wyszukiwania
18. ✅ Testy dla generowania wielu context_tag
19. ✅ Testy dla zgłaszania błędów
20. ✅ Testy bezpieczeństwa (AI injection, XSS)

---

## 📋 Podsumowanie Ustaleń

### ✅ Zdecydowane:
1. **Ukrycie TMDB** - brak tmdb_id w odpowiedziach API, tylko slugi
2. **Wielokrotne generowanie** - jednoczesne dla wielu context_tag (default, modern, romantic, sciFi, comedy)
3. **Cache** - 1h TTL dla wyników wyszukiwania TMDB
4. **Rate Limiting** - konfigurowalne lokalnie z auto-dostosowaniem
5. **Zgłaszanie błędów** - automatyczna regeneracja po weryfikacji
6. **Asynchroniczność** - wszystkie długotrwałe operacje przez queue jobs
7. **CQRS** - zastosować jeśli sytuacja wymaga (np. dla wyszukiwania)

### ✅ Finalne Decyzje:
1. **Powiązane filmy** - ✅ **Opcja A**: Automatyczne wykrywanie sequels/prequels
2. **Synchronizacja aktorów przy refresh** - ✅ **Opcja A**: Tylko metadane filmu (nie synchronizuj aktorów ponownie)
3. **Weryfikacja zgłoszeń** - ✅ **Opcja B**: Wymaga akceptacji administratora + **PRIORYTETYZACJA**: Częstsze zgłoszenia = wyższy priorytet naprawy

---

## ✅ Wszystkie Decyzje Podjęte

1. **Powiązane filmy (Sequels/Prequels):** ✅ **Opcja A** - Automatyczne wykrywanie i linkowanie
2. **Synchronizacja aktorów przy `/refresh`:** ✅ **Opcja A** - Tylko metadane filmu
3. **Weryfikacja zgłoszeń błędów:** ✅ **Opcja B** - Wymaga akceptacji administratora + priorytetyzacja

**Wszystkie decyzje zostały podjęte. Można przystąpić do implementacji.**

---

## 📊 Finalne Podsumowanie Wszystkich Decyzji

### ✅ Ukrycie TMDB
- Brak `tmdb_id` w odpowiedziach API
- Używać tylko slugów w disambiguation
- TMDB jest tylko wewnętrznym źródłem danych

### ✅ Wielokrotne generowanie opisów
- Jednoczesne generowanie dla wielu context_tag (default, modern, romantic, sciFi, comedy)
- Każdy context_tag = osobny job w kolejce

### ✅ Synchronizacja aktorów
- Przy pierwszym utworzeniu: pełna synchronizacja (ASYNC)
- Przy `/refresh`: tylko metadane filmu (bez aktorów)

### ✅ Powiązane filmy
- Automatyczne wykrywanie sequels/prequels/remakes/series
- Endpoint `/related` z filtrowaniem po typie (domyślnie wszystkie)

### ✅ Zgłaszanie błędów
- Wymaga akceptacji administratora
- Priorytetyzacja: częstsze zgłoszenia = wyższy priorytet
- `priority_score` widoczny w API dla adminów
- Automatyczna regeneracja po weryfikacji

### ✅ Rate Limiting
- SEARCH: 100 requests/minutę (domyślnie)
- GENERATE: 10 requests/minutę (domyślnie)
- Auto-dostosowanie do obciążenia systemu (adaptive)

### ✅ Cache
- Wyniki wyszukiwania TMDB: 1h TTL
- Opisy AI: już cache'owane (obecne rozwiązanie)

---

**Gotowe do implementacji! 🚀**

---

## 🧹 Instrukcje dotyczące czyszczenia kodu podczas implementacji

### Oznaczanie nieużywanych plików/funkcji

**Podczas implementacji nowych funkcji, jeśli znajdziesz nieużywane/niepotrzebne pliki lub funkcje:**

1. **Dodaj komentarz z tagiem:**
   ```php
   /**
    * @deprecated Nieużywane - do usunięcia po weryfikacji
    * @todo REMOVE: Sprawdź czy nie jest używane w innych miejscach, potem usuń
    * @see NEW_SEARCH_USE_CASE_ANALYSIS.md - implementacja nowego use case'a
    */
   ```

2. **Lub użyj standardowego komentarza:**
   ```php
   // TODO: REMOVE - Nieużywane po implementacji nowego use case'a
   // Data: 2024-01-XX
   // Powód: Zastąpione przez [nazwa nowej klasy/funkcji]
   ```

3. **Dla całych plików - dodaj na początku:**
   ```php
   <?php
   
   /**
    * @deprecated Ten plik jest nieużywany i powinien zostać usunięty
    * @todo REMOVE: Sprawdź zależności, potem usuń plik
    * @see docs/issue/NEW_SEARCH_USE_CASE_ANALYSIS.md
    */
   ```

4. **Dla klas - użyj atrybutu:**
   ```php
   /**
    * @deprecated Klasa nieużywana - do usunięcia
    * Zastąpiona przez: App\Services\MovieSearchService
    */
   #[Deprecated('Use MovieSearchService instead')]
   class OldMovieSearchService
   {
       // ...
   }
   ```

### Standardowe tagi do użycia:

- `@deprecated` - standardowy tag PHP dla przestarzałych elementów
- `@todo REMOVE` - zadanie do wykonania (usunięcie)
- `TODO: CLEANUP` - do posprzątania
- `TODO: REFACTOR` - do refaktoryzacji
- `FIXME: REMOVE` - do naprawy/usunięcia

### Proces weryfikacji przed usunięciem:

1. **Sprawdź użycie:**
   ```bash
   # W IDE lub przez grep
   grep -r "OldClassName" api/
   grep -r "old_function_name" api/
   ```

2. **Sprawdź testy:**
   ```bash
   # Czy są testy używające tego kodu?
   grep -r "OldClassName" tests/
   ```

3. **Sprawdź dokumentację:**
   - Czy jest wspomniane w README/docs?
   - Czy jest w OpenAPI spec?

4. **Po weryfikacji:**
   - Usuń plik/funkcję
   - Zaktualizuj dokumentację jeśli potrzeba
   - Usuń związane testy (jeśli były tylko dla tego kodu)

### Przykłady miejsc, gdzie mogą być nieużywane elementy:

- Stare kontrolery zastąpione nowymi
- Stare serwisy zastąpione nowymi (np. stary sposób wyszukiwania)
- Nieużywane metody w modelach
- Stare migracje (jeśli nie są potrzebne)
- Nieużywane helpery/utilities
- Stare testy dla usuniętych funkcji

### Notatka:

**Nie usuwać od razu!** Oznaczyć komentarzem, zweryfikować, potem usunąć w osobnej fazie czyszczenia kodu (może być osobne zadanie/PR).


# 🔧 Refaktoryzacja Kontrolerów API - Zgodność z SOLID i Dobrymi Praktykami

**Data utworzenia:** 2025-01-27  
**Status:** 📋 Planowanie  
**Priorytet:** 🔴 Wysoki  
**Szacowany czas:** 6-8 godzin

---

## 📋 **OPIS ZADANIA**

Refaktoryzacja kontrolerów API (`MovieController`, `PersonController`, `GenerateController`, `JobsController`) w celu:
- Eliminacji duplikacji kodu
- Zgodności z zasadami SOLID
- Poprawy czytelności i utrzymywalności kodu
- Ujednolicenia podejścia do transformacji danych (Resource classes)
- Dodania typów zwracanych i poprawy type safety

---

## 🎯 **CELE REFAKTORYZACJI**

### **Główne cele:**
1. ✅ Wydzielenie logiki biznesowej z kontrolerów do dedykowanych serwisów/akcji
2. ✅ Eliminacja duplikacji kodu (cache, job queue, resource creation)
3. ✅ Ujednolicenie podejścia do transformacji danych (Resource classes)
4. ✅ Poprawa czytelności metod (Single Responsibility Principle)
5. ✅ Dodanie typów zwracanych (`JsonResponse`)
6. ✅ Lepsze testowanie (dependency injection, mniejsze metody)

---

## 🔴 **PROBLEMY WYKRYTE W OBECNYM KODZIE**

### **1. MovieController::show() - Główne Problemy**

#### **Aktualne problemy:**
- ❌ Metoda zbyt długa (60+ linii)
- ❌ Zagnieżdżone if-y (3 poziomy deep)
- ❌ Mieszanie logiki biznesowej (disambiguation) z kontrolerem
- ❌ Duplikacja tworzenia `MovieResource` (2x w tej samej metodzie)
- ❌ Brak typów zwracanych (`JsonResponse`)
- ❌ Brak wydzielonej logiki dla: cache init, response building, disambiguation

#### **Kod przed refaktoryzacją:**
```php
public function show(Request $request, string $slug)
{
    $movie = $this->movieRepository->findBySlugWithRelations($slug);
    if ($movie) {
        // Check if slug without year matched multiple movies
        $parsed = Movie::parseSlug($slug);
        if ($parsed['year'] === null) {
            $allMovies = $this->movieRepository->findAllByTitleSlug(Str::slug($parsed['title']));
            if ($allMovies->count() > 1) {
                // Multiple movies with same title - include disambiguation info
                $resource = new MovieResource($movie);
                $resource->additional(['_links' => $this->hateoas->movieLinks($movie)]);
                $payload = $resource->resolve($request);
                $payload['_meta'] = [
                    'ambiguous' => true,
                    'message' => 'Multiple movies found with this title...',
                    'alternatives' => $allMovies->map(function (Movie $m) {
                        return [
                            'slug' => $m->slug,
                            'title' => $m->title,
                            'release_year' => $m->release_year,
                            'url' => url("/api/v1/movies/{$m->slug}"),
                        ];
                    })->toArray(),
                ];
                return response()->json($payload);
            }
        }
        $resource = new MovieResource($movie);
        $resource->additional(['_links' => $this->hateoas->movieLinks($movie)]);
        return response()->json($resource->resolve($request));
    }
    // ... rest of the method
}
```

---

### **2. PersonController - Problemy**

#### **Aktualne problemy:**
- ❌ Używa `toArray()` zamiast Resource class (niekonsystentne z MovieController)
- ❌ Brak `PersonResource` class
- ❌ Duplikacja logiki cache/job queue (identyczna jak w MovieController)
- ❌ Brak typów zwracanych
- ❌ `show()` nie przyjmuje `Request` (niekonsystentne z MovieController)

#### **Kod przed refaktoryzacją:**
```php
public function show(string $slug)
{
    $person = $this->personRepository->findBySlugWithRelations($slug);
    if ($person) {
        $payload = $person->toArray(); // ❌ Powinno używać PersonResource
        $payload['_links'] = $this->hateoas->personLinks($person);
        return response()->json($payload);
    }
    // ... duplikacja logiki cache/job queue
}
```

---

### **3. GenerateController - Duplikacja**

#### **Aktualne problemy:**
- ❌ `handleMovieGeneration()` i `handlePersonGeneration()` są niemal identyczne
- ❌ Duplikacja: slug validation, cache initialization, event dispatch

---

### **4. JobsController - Problemy**

#### **Aktualne problemy:**
- ❌ Brak typów zwracanych
- ❌ Magic string `'ai_job:'` (powinien być w konstancie/helper)

---

### **5. Duplikacja Logiki Cache**

#### **Problemy:**
- ❌ Cache initialization jest duplikowana w 3 miejscach:
  - `MovieController::show()`
  - `PersonController::show()`
  - `GenerateController::handleMovieGeneration()` / `handlePersonGeneration()`

#### **Przykład duplikacji:**
```php
// Powtarza się w 3 miejscach:
Cache::put("ai_job:{$jobId}", [
    'job_id' => $jobId,
    'status' => 'PENDING',
    'entity' => 'MOVIE', // lub 'PERSON'
    'slug' => $slug,
], now()->addMinutes(15));
```

---

## ✅ **PROPONOWANE ZMIANY**

### **1. Utworzenie JobStatusService**

**Plik:** `api/app/Services/JobStatusService.php`

**Cel:** Eliminacja duplikacji cache initialization i management.

**Metody:**
```php
class JobStatusService
{
    public function initializeStatus(
        string $jobId,
        string $entityType,
        string $slug,
        ?float $confidence = null
    ): void;

    public function getStatus(string $jobId): ?array;

    public function updateStatus(
        string $jobId,
        string $status,
        array $additional = []
    ): void;

    private function cacheKey(string $jobId): string;
}
```

**Korzyści:**
- ✅ Single Responsibility Principle
- ✅ DRY (Don't Repeat Yourself)
- ✅ Łatwiejsze testowanie
- ✅ Centralne miejsce na zmiany logiki cache

---

### **2. Utworzenie PersonResource**

**Plik:** `api/app/Http/Resources/PersonResource.php`

**Cel:** Ujednolicenie transformacji danych z `MovieResource`.

**Struktura:**
```php
class PersonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'birth_date' => $this->birth_date,
            'birthplace' => $this->birthplace,
            'default_bio' => $this->whenLoaded('defaultBio'),
            'movies' => $this->whenLoaded('movies', function () {
                return $this->movies->map(function ($movie) {
                    return [
                        'id' => $movie->id,
                        'slug' => $movie->slug,
                        'title' => $movie->title,
                        'role' => $movie->pivot->role,
                        'character_name' => $movie->pivot->character_name ?? null,
                    ];
                });
            }),
            '_links' => $this->when($this->additional['_links'] ?? null, function () {
                return $this->additional['_links'];
            }),
        ];
    }
}
```

**Korzyści:**
- ✅ Konsystencja z `MovieResource`
- ✅ Centralne miejsce na transformację danych
- ✅ Łatwiejsze testowanie
- ✅ Możliwość dodania dodatkowej logiki (formatowanie dat, etc.)

---

### **3. Utworzenie MovieDisambiguationService**

**Plik:** `api/app/Services/MovieDisambiguationService.php`

**Cel:** Wydzielenie logiki disambiguation z `MovieController::show()`.

**Metody:**
```php
class MovieDisambiguationService
{
    public function __construct(
        private readonly MovieRepository $movieRepository
    ) {}

    public function checkForAmbiguousSlug(
        Movie $movie,
        string $slug
    ): ?array {
        $parsed = Movie::parseSlug($slug);
        
        if ($parsed['year'] !== null) {
            return null; // Slug contains year, not ambiguous
        }

        $allMovies = $this->movieRepository->findAllByTitleSlug(
            Str::slug($parsed['title'])
        );

        if ($allMovies->count() <= 1) {
            return null; // No ambiguity
        }

        return [
            'ambiguous' => true,
            'message' => 'Multiple movies found with this title. Showing most recent. Use slug with year (e.g., "bad-boys-1995") for specific version.',
            'alternatives' => $allMovies->map(function (Movie $m) {
                return [
                    'slug' => $m->slug,
                    'title' => $m->title,
                    'release_year' => $m->release_year,
                    'url' => url("/api/v1/movies/{$m->slug}"),
                ];
            })->toArray(),
        ];
    }
}
```

**Korzyści:**
- ✅ Single Responsibility Principle
- ✅ Łatwiejsze testowanie (unit tests dla disambiguation logic)
- ✅ Możliwość reużycia w innych miejscach
- ✅ Uproszczenie `MovieController::show()`

---

### **4. Utworzenie QueueMovieGenerationAction i QueuePersonGenerationAction**

**Pliki:**
- `api/app/Actions/QueueMovieGenerationAction.php`
- `api/app/Actions/QueuePersonGenerationAction.php`

**Cel:** Wydzielenie logiki queue generation z kontrolerów.

**Struktura:**
```php
class QueueMovieGenerationAction
{
    public function __construct(
        private readonly JobStatusService $jobStatusService
    ) {}

    public function handle(string $slug): array
    {
        $jobId = (string) Str::uuid();

        $this->jobStatusService->initializeStatus(
            $jobId,
            'MOVIE',
            $slug
        );

        event(new MovieGenerationRequested($slug, $jobId));

        return [
            'job_id' => $jobId,
            'status' => 'PENDING',
            'message' => 'Generation queued for movie by slug',
            'slug' => $slug,
        ];
    }
}
```

**Korzyści:**
- ✅ Single Responsibility Principle
- ✅ DRY (eliminacja duplikacji)
- ✅ Łatwiejsze testowanie
- ✅ Możliwość dodania dodatkowej logiki (logging, metrics, etc.)

---

### **5. Refaktoryzacja MovieController::show()**

**Kod po refaktoryzacji:**
```php
public function show(Request $request, string $slug): JsonResponse
{
    $movie = $this->movieRepository->findBySlugWithRelations($slug);
    
    if ($movie) {
        return $this->handleExistingMovie($movie, $request, $slug);
    }
    
    return $this->handleMissingMovie($slug);
}

private function handleExistingMovie(
    Movie $movie,
    Request $request,
    string $slug
): JsonResponse {
    $disambiguation = $this->disambiguationService->checkForAmbiguousSlug($movie, $slug);
    
    $resource = $this->createMovieResource($movie, $request);
    
    if ($disambiguation) {
        $resource['_meta'] = $disambiguation;
    }
    
    return response()->json($resource);
}

private function handleMissingMovie(string $slug): JsonResponse
{
    if (! Feature::active('ai_description_generation')) {
        return response()->json(['error' => 'Movie not found'], 404);
    }
    
    $result = $this->queueMovieGenerationAction->handle($slug);
    
    return response()->json($result, 202);
}

private function createMovieResource(Movie $movie, Request $request): array
{
    $resource = new MovieResource($movie);
    $resource->additional(['_links' => $this->hateoas->movieLinks($movie)]);
    
    return $resource->resolve($request);
}
```

**Korzyści:**
- ✅ Metoda `show()` jest teraz czytelna i prosta
- ✅ Każda metoda ma jedną odpowiedzialność
- ✅ Łatwiejsze testowanie (każda metoda osobno)
- ✅ Brak zagnieżdżonych if-ów

---

### **6. Refaktoryzacja PersonController**

**Zmiany:**
1. Dodanie `Request $request` do `show()`
2. Użycie `PersonResource` zamiast `toArray()`
3. Użycie `QueuePersonGenerationAction`
4. Użycie `JobStatusService`
5. Dodanie typów zwracanych

**Kod po refaktoryzacji:**
```php
public function show(Request $request, string $slug): JsonResponse
{
    $person = $this->personRepository->findBySlugWithRelations($slug);
    
    if ($person) {
        return $this->handleExistingPerson($person, $request);
    }
    
    return $this->handleMissingPerson($slug);
}

private function handleExistingPerson(
    Person $person,
    Request $request
): JsonResponse {
    $resource = $this->createPersonResource($person, $request);
    
    return response()->json($resource);
}

private function handleMissingPerson(string $slug): JsonResponse
{
    if (! Feature::active('ai_bio_generation')) {
        return response()->json(['error' => 'Person not found'], 404);
    }
    
    $result = $this->queuePersonGenerationAction->handle($slug);
    
    return response()->json($result, 202);
}

private function createPersonResource(Person $person, Request $request): array
{
    $resource = new PersonResource($person);
    $resource->additional(['_links' => $this->hateoas->personLinks($person)]);
    
    return $resource->resolve($request);
}
```

---

### **7. Refaktoryzacja GenerateController**

**Zmiany:**
1. Użycie `JobStatusService` zamiast bezpośredniego `Cache::put()`
2. Wydzielenie wspólnej logiki do helper methods

**Korzyści:**
- ✅ Mniej duplikacji
- ✅ Spójność z innymi kontrolerami

---

### **8. Refaktoryzacja JobsController**

**Zmiany:**
1. Dodanie typów zwracanych
2. Użycie `JobStatusService` zamiast bezpośredniego `Cache::get()`

**Kod po refaktoryzacji:**
```php
public function show(string $id): JsonResponse
{
    $data = $this->jobStatusService->getStatus($id);
    
    if (! $data) {
        return response()->json([
            'job_id' => $id,
            'status' => 'UNKNOWN',
        ], 404);
    }
    
    return response()->json($data);
}
```

---

## 📊 **PODSUMOWANIE ZMIAN**

### **Nowe pliki do utworzenia:**

1. ✅ `api/app/Services/JobStatusService.php`
2. ✅ `api/app/Http/Resources/PersonResource.php`
3. ✅ `api/app/Services/MovieDisambiguationService.php`
4. ✅ `api/app/Actions/QueueMovieGenerationAction.php`
5. ✅ `api/app/Actions/QueuePersonGenerationAction.php`

### **Pliki do modyfikacji:**

1. ✅ `api/app/Http/Controllers/Api/MovieController.php`
2. ✅ `api/app/Http/Controllers/Api/PersonController.php`
3. ✅ `api/app/Http/Controllers/Api/GenerateController.php`
4. ✅ `api/app/Http/Controllers/Api/JobsController.php`

### **Testy do utworzenia/aktualizacji:**

1. ✅ `api/tests/Unit/Services/JobStatusServiceTest.php`
2. ✅ `api/tests/Unit/Services/MovieDisambiguationServiceTest.php`
3. ✅ `api/tests/Unit/Actions/QueueMovieGenerationActionTest.php`
4. ✅ `api/tests/Unit/Actions/QueuePersonGenerationActionTest.php`
5. ✅ `api/tests/Unit/Http/Resources/PersonResourceTest.php`
6. ✅ Aktualizacja istniejących testów kontrolerów

---

## 🎯 **KORZYŚCI Z REFAKTORYZACJI**

### **1. SOLID Principles:**
- ✅ **Single Responsibility:** Każda klasa/metoda ma jedną odpowiedzialność
- ✅ **Open/Closed:** Łatwiejsze dodawanie nowych funkcjonalności bez modyfikacji istniejącego kodu
- ✅ **Dependency Inversion:** Kontrolery zależą od abstrakcji (Services, Actions)

### **2. DRY (Don't Repeat Yourself):**
- ✅ Eliminacja duplikacji cache initialization
- ✅ Eliminacja duplikacji resource creation
- ✅ Eliminacja duplikacji queue generation logic

### **3. Testowalność:**
- ✅ Mniejsze metody = łatwiejsze unit testy
- ✅ Wydzielone serwisy = możliwość mockowania
- ✅ Mniej zależności w kontrolerach

### **4. Czytelność:**
- ✅ Krótsze metody
- ✅ Mniej zagnieżdżonych if-ów
- ✅ Jasne nazwy metod (`handleExistingMovie`, `handleMissingMovie`)

### **5. Konsystencja:**
- ✅ Ujednolicone podejście do Resource classes
- ✅ Ujednolicone typy zwracane (`JsonResponse`)
- ✅ Ujednolicone podejście do error handling

---

## 📝 **KROKI IMPLEMENTACJI**

### **Faza 1: Utworzenie Services i Resources**
1. ✅ Utworzenie `JobStatusService`
2. ✅ Utworzenie `PersonResource`
3. ✅ Utworzenie `MovieDisambiguationService`

### **Faza 2: Utworzenie Actions**
4. ✅ Utworzenie `QueueMovieGenerationAction`
5. ✅ Utworzenie `QueuePersonGenerationAction`

### **Faza 3: Refaktoryzacja Kontrolerów**
6. ✅ Refaktoryzacja `MovieController::show()`
7. ✅ Refaktoryzacja `PersonController`
8. ✅ Refaktoryzacja `GenerateController`
9. ✅ Refaktoryzacja `JobsController`

### **Faza 4: Testy**
10. ✅ Utworzenie testów dla nowych klas
11. ✅ Aktualizacja istniejących testów
12. ✅ Uruchomienie wszystkich testów

### **Faza 5: Dokumentacja**
13. ✅ Aktualizacja dokumentacji API (jeśli potrzeba)
14. ✅ Aktualizacja README (jeśli potrzeba)

---

## ⚠️ **UWAGI I RYZYKA**

### **Potencjalne problemy:**
1. ⚠️ Breaking changes w testach (możliwe zmiany w mockach)
2. ⚠️ Konieczność aktualizacji wszystkich miejsc używających bezpośrednio `Cache::put/get`
3. ⚠️ Konieczność weryfikacji, że wszystkie edge cases są obsługiwane

### **Mitygacja:**
- ✅ Pisanie testów przed refaktoryzacją (TDD)
- ✅ Stopniowa refaktoryzacja (małe kroki)
- ✅ Uruchamianie testów po każdej zmianie

---

## 📚 **MATERIAŁY REFERENCYJNE**

- [Laravel Resources](https://laravel.com/docs/11.x/eloquent-resources)
- [SOLID Principles](https://en.wikipedia.org/wiki/SOLID)
- [Refactoring Guru](https://refactoring.guru/refactoring)
- [Clean Code by Robert C. Martin](https://www.amazon.com/Clean-Code-Handbook-Software-Craftsmanship/dp/0132350882)

---

## ✅ **CHECKLIST PRZED COMMIT**

- [ ] Wszystkie nowe klasy utworzone
- [ ] Wszystkie kontrolery zrefaktoryzowane
- [ ] Wszystkie testy przechodzą
- [ ] PHPStan nie zgłasza błędów
- [ ] Laravel Pint nie zgłasza błędów stylu
- [ ] Dokumentacja zaktualizowana (jeśli potrzeba)
- [ ] Code review wykonany

---

**Status:** 📋 Planowanie  
**Ostatnia aktualizacja:** 2025-01-27


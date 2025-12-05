# Analiza problemu weryfikacji AI dla wszystkich typów encji

> **Data utworzenia:** 2025-12-01  
> **Kontekst:** Analiza problemu weryfikacji istnienia przez AI dla filmów, osób i przyszłych typów (seriale, TV shows)  
> **Kategoria:** technical  
> **Priorytet:** 🔴 Krytyczny

## 🎯 Problem

### Obecna sytuacja

System MovieMind API ma **identyczny problem** dla wszystkich typów encji:

1. **Filmy (Movies)** - ✅ Zidentyfikowany problem
2. **Osoby (People)** - ⚠️ **Ten sam problem istnieje**
3. **Seriale (Series)** - ⚠️ **Będzie miał ten sam problem** (gdy zostanie dodany)
4. **TV Shows** - ⚠️ **Będzie miał ten sam problem** (gdy zostanie dodany)

### Wspólny flow problemu

```
Request → Check DB → Not found → Queue Job → AI verifies → FAILED (NOT_FOUND)
```

**Dla wszystkich typów:**
- Endpoint zwraca 202 z `job_id`
- AI weryfikuje w swojej wiedzy z treningu
- AI zwraca `{"error": "Entity not found"}` nawet dla istniejących encji
- Job kończy się `FAILED` z `NOT_FOUND`

## 📊 Analiza dla każdego typu

### 1. Filmy (Movies) - ✅ Zidentyfikowany

**Problem:**
- AI zwraca "Movie not found" dla istniejących filmów (np. "Bad Boys")
- Slug może być niejednoznaczny (np. "bad-boys" może oznaczać różne filmy)

**Przykład:**
```php
// api/app/Services/OpenAiClient.php:49
$systemPrompt = 'You are a movie database assistant. IMPORTANT: First verify if the movie exists...';
```

**Rozwiązanie:** Integracja z TMDb API

### 2. Osoby (People) - ⚠️ Ten sam problem

**Problem:**
- AI zwraca "Person not found" dla istniejących osób
- Slug może być niejednoznaczny (np. "john-smith" może oznaczać wiele osób)

**Kod:**
```php
// api/app/Services/OpenAiClient.php:74
$systemPrompt = 'You are a biography assistant. IMPORTANT: First verify if the person exists...';
```

**Przykład problemu:**
- Slug: `will-smith` → może nie być rozpoznany przez AI
- Slug: `christopher-nolan` → może nie być rozpoznany przez AI
- Slug: `john-doe` → może być niejednoznaczny (wiele osób o tym imieniu)

**Rozwiązanie:** Integracja z TMDb API (People endpoint)

### 3. Seriale (Series) - ⚠️ Będzie miał ten sam problem

**Problem (przewidywany):**
- Gdy zostanie dodany endpoint dla seriali, będzie miał identyczny problem
- AI zwróci "Series not found" dla istniejących seriali
- Slug może być niejednoznaczny

**Przykład:**
- Slug: `breaking-bad` → może nie być rozpoznany przez AI
- Slug: `game-of-thrones` → może nie być rozpoznany przez AI

**Rozwiązanie:** Integracja z TMDb API (TV Shows endpoint)

### 4. TV Shows - ⚠️ Będzie miał ten sam problem

**Problem (przewidywany):**
- Identyczny problem jak dla seriali
- AI zwróci "TV Show not found" dla istniejących programów

**Rozwiązanie:** Integracja z TMDb API (TV Shows endpoint)

## 💡 Uniwersalne rozwiązanie

### Strategia: Wspólny serwis weryfikacji

**Zalety:**
- ✅ Jedna implementacja dla wszystkich typów
- ✅ Spójne zachowanie w całym systemie
- ✅ Łatwiejsze utrzymanie
- ✅ Możliwość rozszerzenia o nowe typy

### Architektura rozwiązania

```
┌─────────────────────────────────────────────────────────┐
│              Entity Verification Service                │
│  (Uniwersalny serwis dla wszystkich typów encji)        │
└─────────────────────────────────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        │               │               │
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│  TMDb Client │ │  IMDb Client │ │  Other APIs  │
│  (Movies)    │ │  (People)    │ │  (Future)    │
└──────────────┘ └──────────────┘ └──────────────┘
```

### Implementacja

#### 1. Interface dla weryfikacji

```php
interface EntityVerificationServiceInterface
{
    public function verifyMovie(string $slug): ?MovieVerificationResult;
    public function verifyPerson(string $slug): ?PersonVerificationResult;
    public function verifySeries(string $slug): ?SeriesVerificationResult;
    public function verifyTVShow(string $slug): ?TVShowVerificationResult;
}
```

#### 2. TMDb Client (wspólny)

```php
class TmdbVerificationService implements EntityVerificationServiceInterface
{
    public function verifyMovie(string $slug): ?MovieVerificationResult
    {
        // Wyszukaj film w TMDb
        $results = $this->tmdbClient->search()->movies($slug);
        
        if (empty($results)) {
            return null; // Nie znaleziono
        }
        
        // Rozwiąż niejednoznaczność (wybierz najlepszy match)
        $bestMatch = $this->resolveAmbiguity($results, $slug);
        
        return new MovieVerificationResult(
            title: $bestMatch['title'],
            year: $bestMatch['release_date'],
            director: $bestMatch['director'],
            tmdbId: $bestMatch['id']
        );
    }
    
    public function verifyPerson(string $slug): ?PersonVerificationResult
    {
        // Wyszukaj osobę w TMDb
        $results = $this->tmdbClient->search()->people($slug);
        
        if (empty($results)) {
            return null;
        }
        
        $bestMatch = $this->resolveAmbiguity($results, $slug);
        
        return new PersonVerificationResult(
            name: $bestMatch['name'],
            birthDate: $bestMatch['birthday'],
            birthplace: $bestMatch['place_of_birth'],
            tmdbId: $bestMatch['id']
        );
    }
    
    // Podobnie dla Series i TV Shows
}
```

#### 3. Integracja w Controllerach

```php
// MovieController::show()
$movie = $this->movieRepository->findBySlugWithRelations($slug);
if ($movie) {
    return $this->respondWithExistingMovie(...);
}

if (!Feature::active('ai_description_generation')) {
    return response()->json(['error' => 'Movie not found'], 404);
}

// NOWE: Weryfikacja przed queue job
$verification = $this->verificationService->verifyMovie($slug);
if (!$verification) {
    return response()->json(['error' => 'Movie not found'], 404);
}

// Queue job z danymi z weryfikacji
$result = $this->queueMovieGenerationAction->handle(
    $slug,
    locale: Locale::EN_US->value,
    tmdbData: $verification // Przekaż dane z TMDb
);

return response()->json($result, 202);
```

## 🔄 Plan implementacji

### Faza 1: Filmy (Krytyczna) - 8-12h

1. Integracja TMDb Client dla filmów
2. Weryfikacja przed queue job
3. Przekazanie danych z TMDb do AI
4. Testy

### Faza 2: Osoby (Wysoki priorytet) - 6-8h

1. Rozszerzenie TMDb Client o People endpoint
2. Weryfikacja w PersonController
3. Przekazanie danych z TMDb do AI
4. Testy

### Faza 3: Seriale i TV Shows (Średni priorytet) - 8-10h

1. Rozszerzenie TMDb Client o TV Shows endpoint
2. Weryfikacja w SeriesController i TVShowController
3. Przekazanie danych z TMDb do AI
4. Testy

### Faza 4: Refaktoryzacja (Niski priorytet) - 4-6h

1. Utworzenie wspólnego interfejsu
2. Wspólny serwis weryfikacji
3. Usunięcie duplikacji kodu

## 📋 Checklist dla każdego typu

### Dla każdego nowego typu encji:

- [ ] Sprawdź czy TMDb API obsługuje ten typ
- [ ] Dodaj metodę weryfikacji w TMDb Client
- [ ] Zintegruj weryfikację w Controller
- [ ] Przekaż dane z TMDb do AI w prompt
- [ ] Dodaj testy
- [ ] Zaktualizuj dokumentację

## 🔗 Powiązane dokumenty

- [`AI_MOVIE_VERIFICATION_PROBLEM.md`](./AI_MOVIE_VERIFICATION_PROBLEM.md)
- [TMDb API Documentation](https://www.themoviedb.org/documentation/api)
- [Task: TASK-044 - Integracja TMDb dla filmów](../issue/pl/TASKS.md#task-044)

## 📌 Notatki

- **Problem jest uniwersalny** - dotyczy wszystkich typów encji
- **Rozwiązanie jest uniwersalne** - TMDb API obsługuje wszystkie typy
- **Priorytet:** 🔴 Wysoki - wymaga naprawy dla wszystkich typów

---

**Ostatnia aktualizacja:** 2025-12-01


# Analiza i Rekomendacje: TASK-037 i TASK-038

> **Data utworzenia:** 2025-12-01  
> **Kontekst:** Szczegółowa analiza i rekomendacje implementacji dla zadań przeciwdziałania halucynacjom AI  
> **Kategoria:** technical

## 🎯 Cel Analizy

Przygotowanie szczegółowej analizy i rekomendacji implementacji dla:
- **TASK-037:** Weryfikacja istnienia filmów/osób przed generowaniem AI
- **TASK-038:** Weryfikacja zgodności danych AI z slugiem

---

## 📊 Obecny Stan Systemu

### 1. Flow Generowania AI

**Obecny przepływ:**
```
Controller (GET /api/v1/movies/{slug})
  ↓
MovieRepository::findBySlugWithRelations()
  ↓ (jeśli nie znaleziono)
QueueMovieGenerationAction
  ↓
Event: MovieGenerationRequested
  ↓
Listener: QueueMovieGenerationJob
  ↓
RealGenerateMovieJob (queue)
  ↓
OpenAiClient::generateMovie()
  ↓
OpenAI API
  ↓
Zapis do bazy (BEZ WALIDACJI)
```

### 2. Obecne Mechanizmy Walidacji

#### ✅ Co działa:
- **SlugValidator** - walidacja formatu slug (długość, wzorce, rok)
- **JSON Schema** - walidacja struktury odpowiedzi AI
- **Feature flags** - kontrola włączania/wyłączania generowania

#### ❌ Co brakuje:
- Weryfikacja istnienia filmu/osoby przed wywołaniem AI
- Walidacja zgodności danych AI ze slugiem
- Wykrywanie halucynacji AI
- Obsługa błędów "not found" z AI

### 3. Obecne Prompty AI

**Movie:**
```php
$systemPrompt = 'You are a movie database assistant. Generate movie information from a slug. Return JSON with: title, release_year, director, description (movie plot), genres (array).';
```

**Person:**
```php
$systemPrompt = 'You are a biography assistant. Generate person biography from a slug. Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).';
```

**Problemy:**
- Brak instrukcji weryfikacji istnienia
- Brak instrukcji zwracania błędu dla nieistniejących encji
- AI może generować dane dla nieistniejących filmów/osób

---

## 🔍 Analiza Problemów

### Problem 1: Brak Weryfikacji Istnienia (TASK-037)

**Ryzyko:** 🔴 **WYSOKIE**

**Wpływ:**
- Baza danych może zawierać nieprawdziwe dane
- Użytkownicy otrzymują informacje o nieistniejących filmach/osobach
- Koszty API dla niepotrzebnych wywołań
- Utrata zaufania użytkowników

**Przykład scenariusza:**
```bash
# Slug dla nieistniejącego filmu
GET /api/v1/movies/non-existent-movie-test-9999
# Zwraca: 202 Accepted, job_id, status: PENDING
# AI próbuje wygenerować dane dla nieistniejącego filmu
# AI może "wymyślić" film z losowymi danymi
```

**Szacowany wpływ:**
- **Częstotliwość:** Średnia (zależy od jakości slugów)
- **Koszty:** ~$0.001-0.01 per niepotrzebne wywołanie API
- **Jakość danych:** Krytyczna - może zepsuć bazę danych

### Problem 2: Brak Walidacji Zgodności (TASK-038)

**Ryzyko:** 🔴 **WYSOKIE**

**Wpływ:**
- Niezgodne dane w bazie (np. slug "the-matrix-1999" → tytuł "Inception")
- Błędne informacje dla użytkowników
- Trudności w debugowaniu
- Problemy z wyszukiwaniem i filtrowaniem

**Przykład scenariusza:**
```php
// Slug: "the-matrix-1999"
// AI zwraca: {"title": "Inception", "release_year": 2010}
// Aplikacja zapisze te dane bez weryfikacji!
// Rezultat: Film z slugiem "the-matrix-1999" ma tytuł "Inception"
```

**Szacowany wpływ:**
- **Częstotliwość:** Niska (ale krytyczna gdy wystąpi)
- **Koszty:** Wysokie (trudne do naprawienia, wymaga ręcznej korekty)
- **Jakość danych:** Krytyczna - całkowicie błędne dane

---

## 💡 Rekomendacje Implementacji

### TASK-037: Weryfikacja Istnienia Przed Generowaniem

#### 🚀 Faza 1: Krótkoterminowa (1-2 tygodnie) - **REKOMENDOWANA DO STARTU**

**Priorytet:** 🔴 **WYSOKI**  
**Szacowany czas:** 4-6 godzin  
**Złożoność:** 🟢 **NISKA**

**Implementacja:**

1. **Ulepszone prompty z instrukcją weryfikacji**

```php
// api/app/Services/OpenAiClient.php

// Movie prompt
$systemPrompt = 'You are a movie database assistant. IMPORTANT: First verify if the movie exists. If the movie does not exist, return {"error": "Movie not found"}. Only if the movie exists, generate movie information from the slug. Return JSON with: title, release_year, director, description (movie plot), genres (array).';

// Person prompt
$systemPrompt = 'You are a biography assistant. IMPORTANT: First verify if the person exists. If the person does not exist, return {"error": "Person not found"}. Only if the person exists, generate biography from the slug. Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).';
```

2. **Obsługa odpowiedzi z błędem w OpenAiClient**

```php
// api/app/Services/OpenAiClient.php

private function makeApiCall(...): array
{
    // ... existing code ...
    
    $content = $this->extractContent($response);
    
    // Check for error response from AI
    if (isset($content['error'])) {
        return $this->errorResponse($content['error']);
    }
    
    // ... rest of code ...
}
```

3. **Obsługa błędów w Jobach**

```php
// api/app/Jobs/RealGenerateMovieJob.php

private function createMovieRecord(OpenAiClientInterface $openAiClient): array
{
    $aiResponse = $openAiClient->generateMovie($this->slug);

    if ($aiResponse['success'] === false) {
        $error = $aiResponse['error'] ?? 'Unknown error';
        
        // Check if it's a "not found" error
        if (str_contains(strtolower($error), 'not found')) {
            throw new \RuntimeException("Movie not found: {$this->slug}");
        }
        
        throw new \RuntimeException('AI API returned error: '.$error);
    }
    
    // ... rest of code ...
}
```

4. **Zwracanie 404 w Controllerze**

```php
// api/app/Http/Controllers/Api/MovieController.php

// W metodzie show(), gdy movie nie istnieje i feature flag jest włączony:
if (! $movie && Feature::active('ai_description_generation')) {
    // Queue generation, but check job status later
    // If job fails with "not found", return 404
}
```

**Zalety:**
- ✅ Proste w implementacji (zmiany tylko w promptach i obsłudze błędów)
- ✅ Nie wymaga zewnętrznych API
- ✅ Wykorzystuje wiedzę AI
- ✅ Szybkie wdrożenie (1-2 dni)

**Wady:**
- ❌ AI może nadal generować dane (hallucination) - ~10-20% przypadków
- ❌ Mniej dokładne niż zewnętrzne API
- ❌ Koszty tokenów dla weryfikacji (ale minimalne)

**Szacowany efekt:**
- Redukcja halucynacji: **60-80%**
- Czas implementacji: **4-6 godzin**
- Koszt: **Minimalny** (tylko zmiany w kodzie)

#### 🔄 Faza 2: Średnioterminowa (1-2 miesiące)

**Priorytet:** 🟡 **ŚREDNI**  
**Szacowany czas:** 8-12 godzin  
**Złożoność:** 🟡 **ŚREDNIA**

**Implementacja:**

1. **Heurystyki walidacji przed generowaniem**

```php
// api/app/Services/PreGenerationValidator.php

class PreGenerationValidator
{
    public function shouldGenerateMovie(string $slug): array
    {
        $slugValidation = SlugValidator::validateMovieSlug($slug);
        
        // Low confidence = probably doesn't exist
        if ($slugValidation['confidence'] < 0.5) {
            return [
                'should_generate' => false,
                'reason' => 'Low confidence slug format',
                'confidence' => $slugValidation['confidence'],
            ];
        }
        
        // Check for suspicious patterns
        if ($this->isSuspiciousPattern($slug)) {
            return [
                'should_generate' => false,
                'reason' => 'Suspicious slug pattern detected',
            ];
        }
        
        return [
            'should_generate' => true,
            'confidence' => $slugValidation['confidence'],
        ];
    }
    
    private function isSuspiciousPattern(string $slug): bool
    {
        // Patterns like: test-123, random-xyz-999, etc.
        return preg_match('/\b(test|random|xyz|abc|123|999)\b/i', $slug);
    }
}
```

2. **Feature flag `hallucination_guard`**

```php
// api/app/Jobs/RealGenerateMovieJob.php

private function createMovieRecord(OpenAiClientInterface $openAiClient): array
{
    // Pre-generation validation
    if (Feature::active('hallucination_guard')) {
        $preValidation = app(PreGenerationValidator::class)
            ->shouldGenerateMovie($this->slug);
            
        if (! $preValidation['should_generate']) {
            throw new \RuntimeException(
                "Pre-generation validation failed: {$preValidation['reason']}"
            );
        }
    }
    
    // ... rest of code ...
}
```

**Zalety:**
- ✅ Dodatkowa warstwa ochrony
- ✅ Możliwość stopniowego wdrażania (feature flag)
- ✅ Wykrywa podejrzane slugi przed wywołaniem AI

**Wady:**
- ❌ Może odrzucić poprawne slugi (false positives)
- ❌ Wymaga dostrojenia threshold

**Szacowany efekt:**
- Redukcja halucynacji: **+10-15%** (łącznie z Fazą 1: **70-95%**)
- Czas implementacji: **8-12 godzin**

#### 🌟 Faza 3: Długoterminowa (3-6 miesięcy)

**Priorytet:** 🟢 **NISKI** (opcjonalne)  
**Szacowany czas:** 20-30 godzin  
**Złożoność:** 🔴 **WYSOKA**

**Implementacja:**

1. **Integracja z TMDb/OMDb API** (opcjonalna, feature flag)

```php
// api/app/Services/ExternalMovieValidationService.php

class ExternalMovieValidationService
{
    public function movieExists(string $slug): ?array
    {
        // Try TMDb first
        $tmdbResult = $this->checkTmdb($slug);
        if ($tmdbResult !== null) {
            return $tmdbResult;
        }
        
        // Fallback to OMDb
        return $this->checkOmdb($slug);
    }
    
    private function checkTmdb(string $slug): ?array
    {
        // Extract title and year from slug
        $parsed = $this->parseSlug($slug);
        
        // Search TMDb API
        $response = Http::get('https://api.themoviedb.org/3/search/movie', [
            'api_key' => config('services.tmdb.api_key'),
            'query' => $parsed['title'],
            'year' => $parsed['year'],
        ]);
        
        if ($response->successful() && count($response->json()['results']) > 0) {
            return [
                'exists' => true,
                'source' => 'tmdb',
                'data' => $response->json()['results'][0],
            ];
        }
        
        return null;
    }
}
```

**Zalety:**
- ✅ Wysoka dokładność (99%+)
- ✅ Dostęp do metadanych (rok, reżyser, obsada)
- ✅ Weryfikacja przed generowaniem

**Wady:**
- ❌ Zależność od zewnętrznego API
- ❌ Koszty API calls (~$0.001 per request)
- ❌ Rate limits (TMDb: 40 req/10s)
- ❌ Wymaga kluczy API
- ❌ Wolniejsze przetwarzanie

**Szacowany efekt:**
- Redukcja halucynacji: **+5%** (łącznie: **95-99%**)
- Czas implementacji: **20-30 godzin**
- Koszt: **$0.001-0.01 per request** (opcjonalne)

---

### TASK-038: Weryfikacja Zgodności Danych AI ze Slugiem

#### 🚀 Faza 1: Krótkoterminowa (1-2 tygodnie) - **REKOMENDOWANA DO STARTU**

**Priorytet:** 🔴 **WYSOKI**  
**Szacowany czas:** 3-4 godziny  
**Złożoność:** 🟢 **NISKA**

**Implementacja:**

1. **Serwis AiDataValidator**

```php
// api/app/Services/AiDataValidator.php

class AiDataValidator
{
    private const MIN_SIMILARITY_THRESHOLD = 0.6;
    private const MIN_YEAR = 1888;
    private const MAX_YEAR_OFFSET = 2; // current year + 2
    
    public function validateMovieData(array $aiResponse, string $slug): array
    {
        $errors = [];
        
        // 1. Validate release year
        if (isset($aiResponse['release_year'])) {
            $year = (int) $aiResponse['release_year'];
            $currentYear = (int) date('Y');
            $maxYear = $currentYear + self::MAX_YEAR_OFFSET;
            
            if ($year < self::MIN_YEAR || $year > $maxYear) {
                $errors[] = "Invalid release year: {$year} (expected {$this->MIN_YEAR}-{$maxYear})";
            }
        }
        
        // 2. Validate title similarity to slug
        if (isset($aiResponse['title'])) {
            $similarity = $this->calculateSimilarity($slug, $aiResponse['title']);
            if ($similarity < self::MIN_SIMILARITY_THRESHOLD) {
                $errors[] = "Title '{$aiResponse['title']}' does not match slug '{$slug}' (similarity: {$similarity})";
            }
        }
        
        // 3. Extract year from slug and compare
        if (preg_match('/\b(18[89]\d|19\d{2}|20[0-3]\d)\b/', $slug, $matches)) {
            $slugYear = (int) $matches[1];
            if (isset($aiResponse['release_year']) && $aiResponse['release_year'] != $slugYear) {
                $errors[] = "Release year mismatch: slug has {$slugYear}, AI returned {$aiResponse['release_year']}";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'similarity' => $similarity ?? null,
        ];
    }
    
    public function validatePersonData(array $aiResponse, string $slug): array
    {
        $errors = [];
        
        // 1. Validate birth date
        if (isset($aiResponse['birth_date'])) {
            $birthDate = \DateTime::createFromFormat('Y-m-d', $aiResponse['birth_date']);
            if (! $birthDate) {
                $errors[] = "Invalid birth date format: {$aiResponse['birth_date']}";
            } else {
                $year = (int) $birthDate->format('Y');
                $currentYear = (int) date('Y');
                
                if ($year < 1850 || $year > $currentYear) {
                    $errors[] = "Invalid birth year: {$year} (expected 1850-{$currentYear})";
                }
            }
        }
        
        // 2. Validate name similarity to slug
        if (isset($aiResponse['name'])) {
            $similarity = $this->calculateSimilarity($slug, $aiResponse['name']);
            if ($similarity < self::MIN_SIMILARITY_THRESHOLD) {
                $errors[] = "Name '{$aiResponse['name']}' does not match slug '{$slug}' (similarity: {$similarity})";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'similarity' => $similarity ?? null,
        ];
    }
    
    private function calculateSimilarity(string $slug, string $text): float
    {
        // Normalize both strings
        $slugNormalized = $this->normalizeForComparison($slug);
        $textNormalized = $this->normalizeForComparison($text);
        
        // Use Levenshtein distance
        $maxLength = max(strlen($slugNormalized), strlen($textNormalized));
        if ($maxLength === 0) {
            return 1.0;
        }
        
        $distance = levenshtein($slugNormalized, $textNormalized);
        $similarity = 1 - ($distance / $maxLength);
        
        // Also check if slug words appear in text
        $slugWords = explode('-', $slugNormalized);
        $matchedWords = 0;
        foreach ($slugWords as $word) {
            if (strlen($word) >= 3 && str_contains($textNormalized, $word)) {
                $matchedWords++;
            }
        }
        
        $wordSimilarity = count($slugWords) > 0 ? $matchedWords / count($slugWords) : 0;
        
        // Combine both metrics (weighted average)
        return ($similarity * 0.6) + ($wordSimilarity * 0.4);
    }
    
    private function normalizeForComparison(string $text): string
    {
        // Remove year patterns
        $text = preg_replace('/\b(18[89]\d|19\d{2}|20[0-3]\d)\b/', '', $text);
        
        // Convert to lowercase
        $text = strtolower($text);
        
        // Remove special characters, keep only alphanumeric and hyphens
        $text = preg_replace('/[^a-z0-9-]/', '', $text);
        
        return trim($text, '-');
    }
}
```

2. **Integracja z Jobami**

```php
// api/app/Jobs/RealGenerateMovieJob.php

private function createMovieRecord(OpenAiClientInterface $openAiClient): array
{
    $aiResponse = $openAiClient->generateMovie($this->slug);

    if ($aiResponse['success'] === false) {
        $error = $aiResponse['error'] ?? 'Unknown error';
        throw new \RuntimeException('AI API returned error: '.$error);
    }
    
    // Validate AI response data
    if (Feature::active('hallucination_guard')) {
        $validator = app(\App\Services\AiDataValidator::class);
        $validation = $validator->validateMovieData($aiResponse, $this->slug);
        
        if (! $validation['valid']) {
            Log::warning('AI data validation failed', [
                'slug' => $this->slug,
                'errors' => $validation['errors'],
                'ai_response' => $aiResponse,
            ]);
            
            throw new \RuntimeException(
                'AI data validation failed: '.implode(', ', $validation['errors'])
            );
        }
    }
    
    // ... rest of code ...
}
```

**Zalety:**
- ✅ Szybkie wykrywanie niezgodności
- ✅ Niskie koszty (tylko obliczenia lokalne)
- ✅ Wykrywa podstawowe błędy (rok, podobieństwo)

**Wady:**
- ❌ Może odrzucić poprawne dane (false positives) - ~5-10%
- ❌ Wymaga dostrojenia threshold
- ❌ Mniej dokładne dla alternatywnych nazw

**Szacowany efekt:**
- Redukcja błędnych danych: **80-90%**
- Czas implementacji: **3-4 godziny**
- Koszt: **Minimalny**

#### 🔄 Faza 2: Średnioterminowa (1-2 miesiące)

**Priorytet:** 🟡 **ŚREDNI**  
**Szacowany czas:** 6-8 godzin  
**Złożoność:** 🟡 **ŚREDNIA**

**Implementacja:**

1. **Rozszerzone heurystyki**

```php
// Dodatkowe walidacje:
- Sprawdzanie czy reżyser pasuje do gatunku filmu
- Sprawdzanie czy data urodzenia pasuje do miejsca urodzenia (geografia)
- Sprawdzanie czy gatunki są spójne z rokiem wydania
- Fuzzy matching dla alternatywnych nazw (np. "The Matrix" vs "Matrix")
```

2. **Logowanie i monitoring**

```php
// api/app/Services/AiDataValidator.php

public function validateMovieData(array $aiResponse, string $slug): array
{
    // ... existing validation ...
    
    // Log suspicious cases (even if passed)
    if ($similarity < 0.7 && $similarity >= 0.6) {
        Log::info('Low similarity detected (passed threshold)', [
            'slug' => $slug,
            'title' => $aiResponse['title'],
            'similarity' => $similarity,
        ]);
    }
    
    return $result;
}
```

**Zalety:**
- ✅ Lepsze wykrywanie edge cases
- ✅ Monitoring jakości danych
- ✅ Możliwość analizy i poprawy

**Szacowany efekt:**
- Redukcja błędnych danych: **+5-10%** (łącznie: **85-95%**)
- Czas implementacji: **6-8 godzin**

---

## 📋 Plan Implementacji - Rekomendowany

### Kolejność Realizacji

#### **Tydzień 1-2: Podstawowa Implementacja (KRYTYCZNE)**

**Dzień 1-2: TASK-037 Faza 1**
1. ✅ Ulepszone prompty z instrukcją weryfikacji
2. ✅ Obsługa odpowiedzi z błędem w OpenAiClient
3. ✅ Obsługa błędów "not found" w Jobach
4. ✅ Testy jednostkowe i feature

**Dzień 3-4: TASK-038 Faza 1**
1. ✅ Implementacja AiDataValidator
2. ✅ Integracja z RealGenerateMovieJob
3. ✅ Integracja z RealGeneratePersonJob
4. ✅ Testy jednostkowe i feature

**Dzień 5: Testy i Dokumentacja**
1. ✅ Testy manualne scenariuszy
2. ✅ Aktualizacja dokumentacji
3. ✅ Code review

**Szacowany czas:** **5 dni roboczych (40 godzin)**

#### **Tydzień 3-4: Rozszerzona Implementacja**

**Tydzień 3: TASK-037 Faza 2**
1. ✅ Implementacja PreGenerationValidator
2. ✅ Aktywacja feature flag `hallucination_guard`
3. ✅ Testy i monitoring

**Tydzień 4: TASK-038 Faza 2**
1. ✅ Rozszerzone heurystyki walidacji
2. ✅ Logowanie i monitoring
3. ✅ Dashboard/metrics

**Szacowany czas:** **10 dni roboczych (80 godzin)**

---

## 🎯 Rekomendacje Priorytetyzacji

### **REKOMENDACJA: Rozpocznij od Faz 1 obu zadań**

**Dlaczego:**
1. ✅ **Wysoki wpływ, niski koszt** - szybkie wdrożenie z dużym efektem
2. ✅ **Minimalne ryzyko** - proste zmiany, łatwe do rollback
3. ✅ **Natychmiastowa poprawa** - redukcja halucynacji o 60-80%
4. ✅ **Fundament dla przyszłych rozszerzeń** - Fazy 2 i 3 budują na Fazie 1

### Kolejność Implementacji:

1. **TASK-037 Faza 1** (4-6h) - **START TUTAJ**
   - Największy wpływ na jakość danych
   - Najprostsza implementacja
   - Natychmiastowa redukcja halucynacji

2. **TASK-038 Faza 1** (3-4h) - **NATYCHMIAST PO**
   - Uzupełnia TASK-037
   - Wykrywa niezgodności danych
   - Można realizować równolegle z TASK-037

3. **TASK-037 Faza 2** (8-12h) - **PO FAZIE 1**
   - Dodatkowa warstwa ochrony
   - Feature flag pozwala na stopniowe wdrożenie

4. **TASK-038 Faza 2** (6-8h) - **OPCJONALNE**
   - Rozszerzone heurystyki
   - Monitoring i analityka

5. **TASK-037 Faza 3** (20-30h) - **DŁUGOTERMINOWO**
   - Tylko jeśli potrzebna wyższa dokładność
   - Wymaga kluczy API i dodatkowych kosztów

---

## 📊 Metryki Sukcesu

### Metryki do śledzenia:

1. **Redukcja halucynacji:**
   - Przed: ~20-30% nieistniejących encji generuje dane
   - Po Fazie 1: ~5-10% (redukcja o 60-80%)
   - Po Fazie 2: ~2-5% (redukcja o 85-90%)
   - Po Fazie 3: ~0.5-1% (redukcja o 95-99%)

2. **Redukcja błędnych danych:**
   - Przed: ~10-15% niezgodnych danych
   - Po Fazie 1: ~1-3% (redukcja o 80-90%)
   - Po Fazie 2: ~0.5-1% (redukcja o 90-95%)

3. **Koszty API:**
   - Przed: Wszystkie slugi generują wywołania API
   - Po Fazie 1: ~20-30% mniej wywołań (nieistniejące encje)
   - Po Fazie 3: ~5-10% mniej wywołań (dodatkowa weryfikacja)

4. **Czas odpowiedzi:**
   - Przed: ~2-5s per generation
   - Po Fazie 1: ~2-5s (bez zmian)
   - Po Fazie 3: ~3-7s (dodatkowa weryfikacja TMDb)

---

## ⚠️ Ryzyka i Mitigacje

### Ryzyko 1: False Positives (Odrzucanie poprawnych danych)

**Prawdopodobieństwo:** 🟡 Średnie  
**Wpływ:** 🟡 Średni

**Mitigacja:**
- Dostosowanie threshold (start od 0.6, dostosuj na podstawie danych)
- Logowanie wszystkich odrzuconych przypadków
- Feature flag pozwala na szybkie wyłączenie
- Monitoring false positive rate

### Ryzyko 2: Zwiększone koszty API (Faza 3)

**Prawdopodobieństwo:** 🟢 Niskie  
**Wpływ:** 🟡 Średni

**Mitigacja:**
- Opcjonalna integracja (feature flag)
- Cache wyników weryfikacji TMDb
- Rate limiting i retry logic
- Monitoring kosztów

### Ryzyko 3: Wolniejsze przetwarzanie (Faza 3)

**Prawdopodobieństwo:** 🟡 Średnie  
**Wpływ:** 🟢 Niski

**Mitigacja:**
- Asynchroniczna weryfikacja (opcjonalna)
- Cache wyników
- Timeout dla zewnętrznych API
- Fallback do prompt-based verification

---

## 🧪 Scenariusze Testowe

### Test 1: Nieistniejący film

**Input:**
```bash
GET /api/v1/movies/non-existent-movie-test-9999
```

**Oczekiwany wynik (Po Fazie 1):**
- 202 Accepted (job queued)
- Job kończy się błędem: "Movie not found"
- Status job: FAILED
- Brak danych w bazie

### Test 2: Niezgodne dane AI

**Input:**
```bash
# Slug: "the-matrix-1999"
# AI zwraca: {"title": "Inception", "release_year": 2010}
```

**Oczekiwany wynik (Po Fazie 1 TASK-038):**
- Job kończy się błędem: "AI data validation failed: Title 'Inception' does not match slug 'the-matrix-1999'"
- Status job: FAILED
- Brak danych w bazie
- Log zawiera szczegóły niezgodności

### Test 3: Poprawne dane

**Input:**
```bash
# Slug: "the-matrix-1999"
# AI zwraca: {"title": "The Matrix", "release_year": 1999}
```

**Oczekiwany wynik:**
- Job kończy się sukcesem
- Status job: DONE
- Dane zapisane w bazie
- Similarity score: > 0.8

---

## 📝 Checklist Implementacji

### TASK-037 Faza 1:
- [ ] Zaktualizować prompty w `OpenAiClient::generateMovie()`
- [ ] Zaktualizować prompty w `OpenAiClient::generatePerson()`
- [ ] Dodać obsługę `{"error": "..."}` w `OpenAiClient::makeApiCall()`
- [ ] Dodać obsługę błędów "not found" w `RealGenerateMovieJob`
- [ ] Dodać obsługę błędów "not found" w `RealGeneratePersonJob`
- [ ] Dodać testy jednostkowe dla nowych promptów
- [ ] Dodać testy feature dla scenariusza "not found"
- [ ] Zaktualizować dokumentację API

### TASK-038 Faza 1:
- [ ] Utworzyć `AiDataValidator` service
- [ ] Zaimplementować `validateMovieData()`
- [ ] Zaimplementować `validatePersonData()`
- [ ] Zaimplementować `calculateSimilarity()`
- [ ] Zintegrować z `RealGenerateMovieJob`
- [ ] Zintegrować z `RealGeneratePersonJob`
- [ ] Dodać testy jednostkowe dla `AiDataValidator`
- [ ] Dodać testy feature dla scenariusza niezgodności
- [ ] Zaktualizować dokumentację

---

## 🔗 Powiązane Dokumenty

- [AI Validation and Hallucination Prevention](./AI_VALIDATION_AND_HALLUCINATION_PREVENTION.md)
- [TASK-037](../../issue/pl/TASKS.md#task-037)
- [TASK-038](../../issue/pl/TASKS.md#task-038)
- [Manual Testing Guide](../reference/MANUAL_TESTING_GUIDE.md)

---

**Ostatnia aktualizacja:** 2025-12-01


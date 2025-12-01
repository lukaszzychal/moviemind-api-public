# Analiza walidacji AI i zapobiegania halucynacjom

> **Data utworzenia:** 2025-11-30  
> **Kontekst:** Analiza mechanizmów walidacji danych generowanych przez AI oraz identyfikacja luk w weryfikacji istnienia filmów/osób  
> **Kategoria:** technical

## Cel

Przeanalizowanie obecnych mechanizmów walidacji danych generowanych przez AI, identyfikacja problemów związanych z generowaniem danych dla nieistniejących encji oraz zaproponowanie rozwiązań.

## Obecny stan

### 1. Walidacja formatu slug

**Lokalizacja:** `api/app/Helpers/SlugValidator.php`

**Funkcjonalność:**
- `validateMovieSlug()` - sprawdza format slug (długość, rok, wzorce)
- `validatePersonSlug()` - sprawdza format slug (długość, liczba słów)
- Zwraca `confidence` score (0.0-1.0) i `reason`

**Ograniczenia:**
- ✅ Sprawdza tylko format slug
- ❌ Nie sprawdza czy film/osoba faktycznie istnieje
- ❌ Nie weryfikuje istnienia w zewnętrznych bazach (TMDb, OMDb, etc.)

**Przykład:**
```php
// Slug "non-existent-movie-test-9999" przechodzi walidację
// confidence: 0.6, reason: "Slug looks like a title but no year detected"
```

### 2. Prompty AI

**Lokalizacja:** `api/app/Services/OpenAiClient.php`

**Obecne prompty:**

**Movie:**
```php
$systemPrompt = 'You are a movie database assistant. Generate movie information from a slug. Return JSON with: title, release_year, director, description (movie plot), genres (array).';
$userPrompt = "Generate movie information for slug: {$slug}. Return JSON with: title, release_year, director, description (movie plot), genres (array).";
```

**Person:**
```php
$systemPrompt = 'You are a biography assistant. Generate person biography from a slug. Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).';
$userPrompt = "Generate biography for person with slug: {$slug}. Return JSON with: name, birth_date (YYYY-MM-DD), birthplace, biography (full text).";
```

**Problemy:**
- ❌ Brak instrukcji weryfikacji istnienia filmu/osoby
- ❌ Brak instrukcji zwracania błędu gdy film/osoba nie istnieje
- ❌ AI może wygenerować dane dla nieistniejącego filmu/osoby (hallucination)

### 3. Weryfikacja danych wygenerowanych przez AI

**Lokalizacja:** `api/app/Jobs/RealGenerateMovieJob.php`, `api/app/Jobs/RealGeneratePersonJob.php`

**Obecny stan:**
- ❌ Brak walidacji czy tytuł/imię z odpowiedzi AI pasuje do slug
- ❌ Brak walidacji czy rok wydania/data urodzenia są rozsądne
- ❌ Brak mechanizmu wykrywania "hallucinations"
- ❌ Dane są zapisywane bezpośrednio do bazy bez weryfikacji

**Przykład kodu:**
```php
// RealGenerateMovieJob::createMovieRecord()
$title = $aiResponse['title'] ?? Str::of($this->slug)->replace('-', ' ')->title();
$releaseYear = $aiResponse['release_year'] ?? 1999;
// Brak walidacji czy title pasuje do slug!
```

### 4. Feature flag `hallucination_guard`

**Lokalizacja:** `api/app/Features/hallucination_guard.php`

**Status:**
- Feature flag istnieje ale nie jest używany
- Brak implementacji mechanizmów anty-halucynacyjnych

## Zidentyfikowane problemy

### Problem 1: Brak weryfikacji istnienia przed generowaniem

**Opis:**
Aplikacja nie sprawdza czy film/osoba faktycznie istnieje przed wywołaniem AI. Dla nieistniejących encji:
- Aplikacja zwraca 202 Accepted i kolejkuje generowanie
- AI próbuje wygenerować dane dla nieistniejącego filmu/osoby
- AI może "wymyślić" film/osobę (hallucination)

**Przykład testu:**
```bash
# Slug dla nieistniejącego filmu
curl http://localhost:8000/api/v1/movies/non-existent-movie-test-9999
# Zwraca: 202 Accepted, job_id, status: PENDING
# AI próbuje wygenerować dane dla nieistniejącego filmu
```

**Konsekwencje:**
- Baza danych może zawierać nieprawdziwe dane
- Użytkownicy mogą otrzymać informacje o nieistniejących filmach/osobach
- Koszty API dla niepotrzebnych wywołań

### Problem 2: Brak walidacji zgodności danych ze slugiem

**Opis:**
Po otrzymaniu odpowiedzi AI, aplikacja nie weryfikuje czy:
- Tytuł/imię pasuje do slug
- Rok wydania/data urodzenia są rozsądne
- Dane faktycznie należą do filmu/osoby określonej przez slug

**Przykład:**
```php
// Slug: "the-matrix-1999"
// AI może zwrócić: {"title": "Inception", "release_year": 2010}
// Aplikacja zapisze te dane bez weryfikacji!
```

**Konsekwencje:**
- Niezgodne dane w bazie
- Błędne informacje dla użytkowników
- Trudności w debugowaniu

### Problem 3: Prompty AI nie zawierają instrukcji weryfikacji

**Opis:**
Obecne prompty nie zawierają instrukcji:
- Weryfikacji istnienia filmu/osoby
- Zwracania błędu gdy film/osoba nie istnieje
- Sprawdzania zgodności danych ze slugiem

**Konsekwencje:**
- AI może generować dane dla nieistniejących encji
- Brak kontroli nad jakością danych
- Wysokie ryzyko "hallucinations"

## Propozycje rozwiązań

### Rozwiązanie 1: Weryfikacja istnienia przed generowaniem

#### Opcja A: Integracja z TMDb/OMDb API (Rekomendowane dla produkcji)

**Implementacja:**
1. Przed wywołaniem AI, sprawdzić czy film/osoba istnieje w TMDb/OMDb
2. Jeśli nie istnieje, zwrócić 404 z komunikatem "Movie/Person not found"
3. Jeśli istnieje, kontynuować z generowaniem

**Zalety:**
- ✅ Wysoka dokładność
- ✅ Dostęp do metadanych (rok, reżyser, obsada)
- ✅ Możliwość weryfikacji przed generowaniem

**Wady:**
- ❌ Zależność od zewnętrznego API
- ❌ Koszty API calls
- ❌ Rate limits
- ❌ Wymaga kluczy API

**Implementacja:**
```php
// Nowy serwis: ExternalMovieValidationService
class ExternalMovieValidationService
{
    public function movieExists(string $slug): bool
    {
        // Sprawdź w TMDb/OMDb
        // Zwróć true/false
    }
}
```

#### Opcja B: Ulepszone prompty z instrukcją weryfikacji (Rekomendowane jako pierwszy krok)

**Implementacja:**
1. Dodać do promptów instrukcję weryfikacji istnienia
2. AI powinien zwrócić `{"error": "Movie not found"}` gdy film nie istnieje
3. Obsłużyć odpowiedź z błędem w aplikacji

**Zalety:**
- ✅ Proste w implementacji
- ✅ Nie wymaga zewnętrznych API
- ✅ Wykorzystuje wiedzę AI

**Wady:**
- ❌ AI może nadal generować dane (hallucination)
- ❌ Mniej dokładne niż zewnętrzne API
- ❌ Koszty tokenów dla weryfikacji

**Implementacja:**
```php
// Ulepszony prompt
$systemPrompt = 'You are a movie database assistant. First, verify if the movie exists. If it does not exist, return {"error": "Movie not found"}. If it exists, generate movie information from a slug. Return JSON with: title, release_year, director, description (movie plot), genres (array).';
```

#### Opcja C: Kombinacja podejść (Rekomendowane długoterminowo)

**Implementacja:**
1. **Poziom 1:** Ulepszone prompty z instrukcją weryfikacji (szybkie wdrożenie)
2. **Poziom 2:** Heurystyki walidacji danych (rok, format, podobieństwo slug)
3. **Poziom 3:** Opcjonalna integracja z TMDb dla wysokiej pewności (feature flag)
4. **Poziom 4:** Logowanie i monitoring podejrzanych przypadków

**Zalety:**
- ✅ Wielowarstwowa ochrona
- ✅ Możliwość stopniowego wdrażania
- ✅ Elastyczność (można wyłączyć zewnętrzne API)

### Rozwiązanie 2: Weryfikacja zgodności danych ze slugiem

#### Opcja A: Heurystyki walidacji (Rekomendowane)

**Implementacja:**
1. Sprawdzić czy rok wydania jest rozsądny (1888-aktualny rok+2)
2. Sprawdzić czy data urodzenia jest rozsądna
3. Sprawdzić podobieństwo slug vs tytuł/imię (Levenshtein, fuzzy matching)
4. Odrzucić dane jeśli niezgodność > threshold

**Zalety:**
- ✅ Szybkie, niskie koszty
- ✅ Wykrywa podstawowe niezgodności
- ✅ Można dostosować threshold

**Wady:**
- ❌ Może odrzucić poprawne dane (różne formaty nazw)
- ❌ Mniej dokładne niż weryfikacja przez AI

**Implementacja:**
```php
class AiDataValidator
{
    public function validateMovieData(array $aiResponse, string $slug): array
    {
        $errors = [];
        
        // Walidacja roku
        if (isset($aiResponse['release_year'])) {
            $year = (int) $aiResponse['release_year'];
            if ($year < 1888 || $year > date('Y') + 2) {
                $errors[] = 'Invalid release year';
            }
        }
        
        // Walidacja podobieństwa tytułu do slug
        if (isset($aiResponse['title'])) {
            $similarity = $this->calculateSimilarity($slug, $aiResponse['title']);
            if ($similarity < 0.5) {
                $errors[] = 'Title does not match slug';
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }
}
```

#### Opcja B: Weryfikacja przez drugie wywołanie AI

**Implementacja:**
1. Po wygenerowaniu danych, użyć AI do weryfikacji
2. Prompt: "Verify if this movie/person matches slug: {slug}"
3. Odrzucić dane jeśli AI zwróci niezgodność

**Zalety:**
- ✅ Wysoka dokładność
- ✅ Wykorzystuje kontekst AI

**Wady:**
- ❌ Podwójne koszty API
- ❌ Wolniejsze przetwarzanie

### Rozwiązanie 3: Aktywacja feature flag `hallucination_guard`

**Implementacja:**
1. Zaimplementować mechanizmy anty-halucynacyjne
2. Aktywować feature flag `hallucination_guard`
3. Użyć flagi do kontroli włączania/wyłączania walidacji

**Zalety:**
- ✅ Możliwość stopniowego wdrażania
- ✅ Możliwość wyłączenia w razie problemów
- ✅ Elastyczność

## Rekomendacje

### Krótkoterminowe (1-2 tygodnie)

1. **Ulepszone prompty** - dodać instrukcję weryfikacji istnienia
2. **Heurystyki walidacji** - podstawowa walidacja roku i podobieństwa slug
3. **Obsługa błędów** - zwracanie 404 gdy AI zwróci "not found"

### Średnioterminowe (1-2 miesiące)

1. **Feature flag `hallucination_guard`** - implementacja i aktywacja
2. **Rozszerzone heurystyki** - bardziej zaawansowana walidacja
3. **Logowanie i monitoring** - śledzenie podejrzanych przypadków

### Długoterminowe (3-6 miesięcy)

1. **Integracja z TMDb/OMDb** - opcjonalna weryfikacja przed generowaniem
2. **Machine learning** - model wykrywania halucynacji
3. **Dashboard** - monitoring jakości danych AI

## Testy

### Scenariusz 1: Nieistniejący film

**Test:**
```bash
curl http://localhost:8000/api/v1/movies/non-existent-movie-xyz-9999
```

**Oczekiwany wynik (po implementacji):**
- 404 Not Found z komunikatem "Movie not found"
- LUB 202 Accepted ale job kończy się błędem "Movie not found"

**Obecny wynik:**
- 202 Accepted, job kolejkuje generowanie
- AI próbuje wygenerować dane

### Scenariusz 2: Niezgodne dane AI

**Test:**
```bash
# Slug: "the-matrix-1999"
# AI zwraca: {"title": "Inception", "release_year": 2010}
```

**Oczekiwany wynik (po implementacji):**
- Dane są odrzucane
- Job kończy się błędem "Data validation failed"
- Log zawiera szczegóły niezgodności

**Obecny wynik:**
- Dane są zapisywane bez weryfikacji

## Powiązane dokumenty

- [Manual Testing Guide](../reference/MANUAL_TESTING_GUIDE.md)
- [OpenAI Integration](../tutorials/OPENAI_SETUP_AND_TESTING.md)
- [Task TASK-037](../../issue/pl/TASKS.md#task-037)
- [Task TASK-038](../../issue/pl/TASKS.md#task-038)

## Notatki

- Feature flag `hallucination_guard` istnieje ale nie jest używany
- Testy manualne wykazały że AI próbuje generować dane dla nieistniejących encji
- OpenAI API zwraca błąd 400 dla niektórych nieistniejących slugów (prawdopodobnie problem z formatem requestu)

---

**Ostatnia aktualizacja:** 2025-11-30


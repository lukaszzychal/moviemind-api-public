# Analiza obsługi błędów w Jobach - Error Handling Analysis

> **Data utworzenia:** 2025-01-09  
> **Kontekst:** Analiza problemu z brakiem komunikatów błędów w payload FAILED dla Jobów  
> **Kategoria:** technical

## 🎯 Cel

Przeanalizować obecną obsługę błędów w Jobach (RealGenerateMovieJob, RealGeneratePersonJob) i zaproponować rozwiązanie, które pozwoli frontendowi otrzymywać czytelne komunikaty błędów.

---

## 📋 Analiza obecnego stanu

### Problem

**Obecny flow:**
1. Frontend wywołuje `GET /api/v1/movies/test-movie-123`
2. API zwraca `202 Accepted` z `job_id`
3. Job wykonuje się i kończy się błędem
4. Frontend wywołuje `GET /api/v1/jobs/{job_id}`
5. API zwraca `status: FAILED` **bez komunikatu błędu**

**Przyczyna:**
- W `RealGenerateMovieJob::handle()` catch blok wywołuje `$this->updateCache('FAILED')` bez przekazania error message
- Metoda `updateCache()` nie przyjmuje parametru `error`
- Metoda `failed()` zapisuje error, ale tylko gdy job się całkowicie nie powiedzie (po wszystkich retry)

### Obecne typy błędów w kodzie

#### 1. **NOT_FOUND** (Hallucination)
- **Exception:** `RuntimeException("Movie not found: {slug}")`
- **Kiedy:** AI nie rozpoznało filmu/osoby
- **Przykłady:**
  - Użytkownik próbuje wygenerować opis dla nieistniejącego filmu (hallucination)
  - AI zwraca "not found" w odpowiedzi

#### 2. **AI_API_ERROR** (Technical)
- **Exception:** `RuntimeException("AI API returned error: {error}")`
- **Kiedy:** Błędy z OpenAI API
- **Przykłady:**
  - Rate limits
  - Network errors
  - API errors (invalid key, quota exceeded)
  - Timeouts

#### 3. **VALIDATION_ERROR** (Hallucination Guard)
- **Exception:** `RuntimeException("AI data validation failed: {errors}")`
- **Kiedy:** Dane wygenerowane przez AI nie przechodzą walidacji
- **Przykłady:**
  - Title nie pasuje do slug (low similarity)
  - Invalid release year
  - Data inconsistency

#### 4. **UNKNOWN_ERROR** (Technical)
- **Exception:** Inne `\Throwable`
- **Kiedy:** Nieoczekiwane błędy
- **Przykłady:**
  - Database errors
  - Memory errors
  - Unexpected exceptions

---

## 💡 Rekomendacje

### Rekomendacja 1: Strukturalny format z typem błędu (ZALECANE)

**Format:**
```json
{
  "job_id": "559d53db-bb14-46ca-928e-d600b3cf6b3a",
  "status": "FAILED",
  "entity": "MOVIE",
  "slug": "test-movie-123",
  "requested_slug": "test-movie-123",
  "error": {
    "type": "NOT_FOUND",
    "message": "The requested movie was not found",
    "technical_message": "Movie not found: test-movie-123",
    "user_message": "This movie does not exist in our database"
  },
  "locale": "en-US",
  "context_tag": null
}
```

**Zalety:**
- ✅ Frontend może rozróżnić typy błędów i pokazać odpowiedni komunikat
- ✅ Użytkownik otrzymuje czytelny komunikat (`user_message`)
- ✅ Deweloperzy mają dostęp do szczegółów technicznych (`technical_message`)
- ✅ Możliwość implementacji różnych akcji w zależności od typu błędu
- ✅ Łatwiejsze logowanie i monitoring (grupowanie po `error.type`)

**Wady:**
- ❌ Wymaga zmiany formatu w cache i API response
- ❌ Wymaga aktualizacji OpenAPI schema

### Rekomendacja 2: Krótki komunikat z automatycznym mapowaniem

**Format:**
```json
{
  "job_id": "559d53db-bb14-46ca-928e-d600b3cf6b3a",
  "status": "FAILED",
  "entity": "MOVIE",
  "error": "NOT_FOUND: The requested movie was not found",
  "locale": "en-US"
}
```

**Zalety:**
- ✅ Prosty format (tylko string)
- ✅ Mniejsza zmiana w kodzie
- ✅ Kompatybilny z obecnym schematem (tylko dodanie error)

**Wady:**
- ❌ Frontend musi parsować string (`error.split(': ')`)
- ❌ Mniej elastyczny
- ❌ Trudniejsze rozróżnienie typów błędów

### Rekomendacja 3: Pełny exception message (prosty)

**Format:**
```json
{
  "job_id": "559d53db-bb14-46ca-928e-d600b3cf6b3a",
  "status": "FAILED",
  "entity": "MOVIE",
  "error": "Movie not found: test-movie-123",
  "locale": "en-US"
}
```

**Zalety:**
- ✅ Najmniejsza zmiana w kodzie
- ✅ Wszystkie szczegóły dostępne

**Wady:**
- ❌ Użytkownicy widzą techniczne komunikaty
- ❌ Frontend nie może łatwo rozróżnić typów błędów
- ❌ Trudniejsze do zlokalizowania (i18n)

---

## 🎯 Finalna rekomendacja

**Rekomendacja: Rekomendacja 1 - Strukturalny format z typem błędu**

### Uzasadnienie

1. **User Experience:**
   - Użytkownicy otrzymują czytelne komunikaty błędów
   - Frontend może pokazać odpowiednie akcje (np. "Try again", "This movie doesn't exist")

2. **Developer Experience:**
   - Łatwiejsze debugowanie z `technical_message`
   - Monitoring i logowanie po typie błędu
   - Łatwiejsze testowanie (sprawdzanie `error.type`)

3. **Skalowalność:**
   - Łatwe dodawanie nowych typów błędów
   - Możliwość dodania dodatkowych pól w przyszłości (np. `retry_after`, `error_code`)

### Implementacja

#### 1. Utworzenie Enum dla typów błędów

```php
// api/app/Enums/JobErrorType.php
enum JobErrorType: string
{
    case NOT_FOUND = 'NOT_FOUND';
    case AI_API_ERROR = 'AI_API_ERROR';
    case VALIDATION_ERROR = 'VALIDATION_ERROR';
    case UNKNOWN_ERROR = 'UNKNOWN_ERROR';
}
```

#### 2. Utworzenie Error Formatter Service

```php
// api/app/Services/JobErrorFormatter.php
class JobErrorFormatter
{
    public function formatError(\Throwable $exception, string $slug): array
    {
        $type = $this->detectErrorType($exception);
        
        return [
            'type' => $type->value,
            'message' => $this->getUserMessage($type, $slug),
            'technical_message' => $exception->getMessage(),
            'user_message' => $this->getUserFriendlyMessage($type, $slug),
        ];
    }
    
    private function detectErrorType(\Throwable $exception): JobErrorType
    {
        $message = $exception->getMessage();
        
        if (stripos($message, 'not found') !== false) {
            return JobErrorType::NOT_FOUND;
        }
        
        if (stripos($message, 'AI API returned error') !== false) {
            return JobErrorType::AI_API_ERROR;
        }
        
        if (stripos($message, 'validation failed') !== false) {
            return JobErrorType::VALIDATION_ERROR;
        }
        
        return JobErrorType::UNKNOWN_ERROR;
    }
    
    // ... metody pomocnicze
}
```

#### 3. Aktualizacja RealGenerateMovieJob

```php
// W catch bloku:
} catch (\Throwable $e) {
    $errorFormatter = app(JobErrorFormatter::class);
    $errorData = $errorFormatter->formatError($e, $this->slug);
    
    $this->updateCache('FAILED', error: $errorData);
    throw $e;
}
```

#### 4. Aktualizacja updateCache() w Job

```php
private function updateCache(
    string $status,
    ?int $id = null,
    ?string $slug = null,
    ?int $descriptionId = null,
    ?string $locale = null,
    ?string $contextTag = null,
    ?array $error = null  // Dodane
): void {
    $payload = [
        'job_id' => $this->jobId,
        'status' => $status,
        'entity' => 'MOVIE',
        'slug' => $slug ?? $this->slug,
        'requested_slug' => $this->slug,
        'id' => $id,
        'description_id' => $descriptionId,
        'locale' => $locale ?? $this->locale,
        'context_tag' => $contextTag ?? $this->contextTag,
    ];
    
    if ($error !== null) {
        $payload['error'] = $error;
    }
    
    Cache::put($this->cacheKey(), $payload, now()->addMinutes(15));
}
```

#### 5. Aktualizacja OpenAPI Schema

```yaml
components:
  schemas:
    Job:
      properties:
        error:
          type: object
          nullable: true
          properties:
            type:
              type: string
              enum: [NOT_FOUND, AI_API_ERROR, VALIDATION_ERROR, UNKNOWN_ERROR]
            message:
              type: string
              description: Technical error message
            technical_message:
              type: string
              description: Full exception message for debugging
            user_message:
              type: string
              description: User-friendly error message
```

---

## 📊 Porównanie opcji

| Kryterium | Opcja 1 (Strukturalny) | Opcja 2 (String z prefiksem) | Opcja 3 (Prosty string) |
|-----------|------------------------|------------------------------|-------------------------|
| User Experience | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐ |
| Developer Experience | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐ |
| Złożoność implementacji | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| Skalowalność | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐ |
| Kompatybilność wsteczna | ⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |

---

## 🔄 Migracja

Jeśli wybierzemy Opcję 1 (Strukturalny format):

1. **Faza 1:** Dodaj `error` jako strukturalny obiekt (breaking change)
2. **Faza 2:** Frontend aktualizuje się do nowego formatu
3. **Faza 3:** Dodaj obsługę i18n dla `user_message` (opcjonalnie)

**Alternatywnie:** Można dodać `error` jako string dla kompatybilności wstecznej, a następnie dodać `error_detail` jako strukturalny obiekt (gradual migration).

---

## 🔗 Powiązane dokumenty

- [OpenAPI Schema](../openapi.yaml)
- [JobStatusService](../../api/app/Services/JobStatusService.php)
- [RealGenerateMovieJob](../../api/app/Jobs/RealGenerateMovieJob.php)
- [Manual Testing Guide](../reference/MANUAL_TESTING_GUIDE.md)

---

**Ostatnia aktualizacja:** 2025-01-09


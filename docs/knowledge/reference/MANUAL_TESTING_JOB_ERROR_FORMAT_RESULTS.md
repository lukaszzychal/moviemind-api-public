# Manual Testing Results - Job Error Format

> **Data utworzenia:** 2025-01-09  
> **Kontekst:** Wyniki manualnego testowania strukturalnego formatu błędów w Jobach  
> **Kategoria:** reference

## 🎯 Cel

Przetestować manualnie całe flow aplikacji w trybie `AI_SERVICE=mock` i `AI_SERVICE=real`, sprawdzając czy strukturalny format błędów działa poprawnie dla wszystkich typów jobów.

---

## ✅ Wyniki Testów

### Test 1: MOCK - Movie (MockGenerateMovieJob)

**Job ID:** `721fe071-80b4-43b0-8968-806469b6b784`  
**Slug:** `test-movie-mock-1764752980`  
**Status:** ✅ **PASSED**

**Wynik:**
```json
{
  "status": "FAILED",
  "entity": "MOVIE",
  "error": {
    "type": "NOT_FOUND",
    "message": "The requested movie was not found",
    "technical_message": "Movie not found: test-movie-mock-1764752980",
    "user_message": "This movie does not exist in our database"
  }
}
```

**Weryfikacja:**
- ✅ Status: `FAILED`
- ✅ Error jest obiektem
- ✅ `error.type`: `NOT_FOUND`
- ✅ `error.message`: istnieje
- ✅ `error.technical_message`: istnieje
- ✅ `error.user_message`: zawiera słowo "movie"

---

### Test 2: MOCK - Person (MockGeneratePersonJob)

**Job ID:** `900f691e-02e0-42c5-b401-0901adb7f505`  
**Slug:** `john-doe-987`  
**Status:** ✅ **PASSED**

**Wynik:**
```json
{
  "status": "FAILED",
  "entity": "PERSON",
  "error": {
    "type": "NOT_FOUND",
    "message": "The requested person was not found",
    "technical_message": "Person not found: john-doe-987",
    "user_message": "This person does not exist in our database"
  }
}
```

**Weryfikacja:**
- ✅ Status: `FAILED`
- ✅ Error jest obiektem
- ✅ `error.type`: `NOT_FOUND`
- ✅ `error.message`: istnieje
- ✅ `error.technical_message`: istnieje
- ✅ `error.user_message`: zawiera słowo "person" (nie "movie")

---

### Test 3: REAL - Movie (RealGenerateMovieJob)

**Job ID:** `b5db4a54-5266-45e1-a63d-94263094ef0e`  
**Slug:** `test-movie-real-1764752995`  
**Status:** ✅ **PASSED**

**Wynik:**
```json
{
  "status": "FAILED",
  "entity": "MOVIE",
  "error": {
    "type": "NOT_FOUND",
    "message": "The requested movie was not found",
    "technical_message": "Movie not found: test-movie-real-1764752995",
    "user_message": "This movie does not exist in our database"
  }
}
```

**Weryfikacja:**
- ✅ Status: `FAILED`
- ✅ Error jest obiektem
- ✅ `error.type`: `NOT_FOUND`
- ✅ `error.message`: istnieje
- ✅ `error.technical_message`: istnieje
- ✅ `error.user_message`: zawiera słowo "movie"

---

### Test 4: REAL - Person (RealGeneratePersonJob)

**Job ID:** `9edb41ff-2035-4db6-aac7-f55c13849074`  
**Slug:** `jane-smith-002`  
**Status:** ✅ **PASSED**

**Wynik:**
```json
{
  "status": "FAILED",
  "entity": "PERSON",
  "error": {
    "type": "NOT_FOUND",
    "message": "The requested person was not found",
    "technical_message": "Person not found: jane-smith-002",
    "user_message": "This person does not exist in our database"
  }
}
```

**Weryfikacja:**
- ✅ Status: `FAILED`
- ✅ Error jest obiektem
- ✅ `error.type`: `NOT_FOUND`
- ✅ `error.message`: istnieje
- ✅ `error.technical_message`: istnieje
- ✅ `error.user_message`: zawiera słowo "person" (nie "movie")

---

## 📊 Podsumowanie

### Wszystkie testy: ✅ **4/4 PASSED**

| Test | Job Type | Entity | Status | Error Format | User Message |
|------|----------|--------|--------|--------------|--------------|
| 1 | MockGenerateMovieJob | MOVIE | ✅ PASSED | ✅ Structured | ✅ Contains "movie" |
| 2 | MockGeneratePersonJob | PERSON | ✅ PASSED | ✅ Structured | ✅ Contains "person" |
| 3 | RealGenerateMovieJob | MOVIE | ✅ PASSED | ✅ Structured | ✅ Contains "movie" |
| 4 | RealGeneratePersonJob | PERSON | ✅ PASSED | ✅ Structured | ✅ Contains "person" |

### Weryfikacja Checklist

- [x] Endpoint zwraca `202 Accepted` z `job_id` - ✅ Wszystkie testy
- [x] `GET /api/v1/jobs/{id}` zwraca status joba - ✅ Wszystkie testy
- [x] Gdy `status: "FAILED"`, pole `error` istnieje i jest obiektem - ✅ Wszystkie testy
- [x] `error.type` jest jednym z: `NOT_FOUND`, `AI_API_ERROR`, `VALIDATION_ERROR`, `UNKNOWN_ERROR` - ✅ Wszystkie testy (NOT_FOUND)
- [x] `error.message` istnieje i jest stringiem - ✅ Wszystkie testy
- [x] `error.technical_message` istnieje i zawiera pełny exception message - ✅ Wszystkie testy
- [x] `error.user_message` istnieje i jest czytelny dla użytkownika - ✅ Wszystkie testy
- [x] Dla MOVIE, `user_message` zawiera słowo "movie" - ✅ Test 1 i 3
- [x] Dla PERSON, `user_message` zawiera słowo "person" - ✅ Test 2 i 4
- [x] Format działa zarówno w trybie MOCK jak i REAL - ✅ Wszystkie testy

---

## 🔍 Weryfikacja Kodu

### Sprawdzone Joby

1. **MockGenerateMovieJob** (`api/app/Jobs/MockGenerateMovieJob.php`)
   - ✅ Używa `JobErrorFormatter` w catch bloku
   - ✅ Używa `JobErrorFormatter` w metodzie `failed()`
   - ✅ Przekazuje `'MOVIE'` jako entityType

2. **RealGenerateMovieJob** (`api/app/Jobs/RealGenerateMovieJob.php`)
   - ✅ Używa `JobErrorFormatter` w catch bloku
   - ✅ Używa `JobErrorFormatter` w metodzie `failed()`
   - ✅ Przekazuje `'MOVIE'` jako entityType

3. **MockGeneratePersonJob** (`api/app/Jobs/MockGeneratePersonJob.php`)
   - ✅ Używa `JobErrorFormatter` w catch bloku
   - ✅ Używa `JobErrorFormatter` w metodzie `failed()`
   - ✅ Przekazuje `'PERSON'` jako entityType

4. **RealGeneratePersonJob** (`api/app/Jobs/RealGeneratePersonJob.php`)
   - ✅ Używa `JobErrorFormatter` w catch bloku
   - ✅ Używa `JobErrorFormatter` w metodzie `failed()`
   - ✅ Przekazuje `'PERSON'` jako entityType

---

## 📝 Notatki

- Wszystkie joby poprawnie używają `JobErrorFormatter` z odpowiednim `entityType`
- Strukturalny format błędów działa poprawnie dla wszystkich typów jobów
- User messages są poprawnie formatowane (zawierają "movie" dla MOVIE, "person" dla PERSON)
- Format działa zarówno w trybie MOCK jak i REAL

---

## 🔗 Powiązane dokumenty

- [Manual Testing Guide - Job Error Format](./MANUAL_TESTING_JOB_ERROR_FORMAT.md)
- [Manual Testing Guide](./MANUAL_TESTING_GUIDE.md)
- [Job Error Handling Analysis](../technical/JOB_ERROR_HANDLING_ANALYSIS.md)
- [OpenAPI Schema](../../openapi.yaml)

---

**Ostatnia aktualizacja:** 2025-01-09


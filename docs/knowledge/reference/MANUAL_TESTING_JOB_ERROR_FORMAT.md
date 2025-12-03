# Manual Testing Guide - Job Error Format

> **Data utworzenia:** 2025-01-09  
> **Kontekst:** Instrukcje manualnego testowania strukturalnego formatu błędów w Jobach  
> **Kategoria:** reference

## 🎯 Cel

Przetestować manualnie całe flow aplikacji w trybie `AI_SERVICE=mock` i `AI_SERVICE=real`, sprawdzając czy strukturalny format błędów działa poprawnie.

---

## 📋 Przygotowanie

### 1. Uruchom aplikację lokalnie

```bash
cd api
php artisan serve --host=127.0.0.1 --port=8000
```

### 2. Uruchom Horizon (dla przetwarzania jobów)

```bash
cd api
php artisan horizon
```

### 3. Aktywuj feature flagi

```bash
cd api
php artisan tinker
```

W tinker:
```php
Laravel\Pennant\Feature::activate('ai_description_generation');
Laravel\Pennant\Feature::activate('ai_bio_generation');
```

---

## 🧪 Test 1: Tryb MOCK - Film nie istnieje (hallucination)

### Cel

Sprawdzić czy w trybie MOCK, gdy próbujemy wygenerować opis dla nieistniejącego filmu, job zwraca strukturalny format błędu.

### Kroki

#### 1. Ustaw tryb MOCK

```bash
# W .env lub przez tinker
php artisan tinker
config(['services.ai.service' => 'mock']);
```

#### 2. Wywołaj endpoint dla nieistniejącego filmu

```bash
curl -X GET "http://127.0.0.1:8000/api/v1/movies/test-movie-123" \
  -H "Accept: application/json" | jq .
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Response zawiera: `job_id`, `status: "PENDING"`, `slug: "test-movie-123"`

#### 3. Sprawdź status joba (poczekaj kilka sekund)

```bash
# Zastąp {job_id} rzeczywistym job_id z poprzedniego kroku
JOB_ID="<job_id_z_kroku_2>"
curl -X GET "http://127.0.0.1:8000/api/v1/jobs/$JOB_ID" \
  -H "Accept: application/json" | jq .
```

**Oczekiwany wynik:**
- Status: `200 OK`
- `status: "FAILED"` (lub `"DONE"` jeśli mock job się powiódł)
- Jeśli `FAILED`, sprawdź czy `error` jest obiektem z polami:
  - `type` (NOT_FOUND, AI_API_ERROR, VALIDATION_ERROR, UNKNOWN_ERROR)
  - `message` (krótki komunikat techniczny)
  - `technical_message` (pełny exception message)
  - `user_message` (komunikat dla użytkownika)

**Przykład odpowiedzi FAILED:**
```json
{
  "job_id": "559d53db-bb14-46ca-928e-d600b3cf6b3a",
  "status": "FAILED",
  "entity": "MOVIE",
  "slug": "test-movie-123",
  "requested_slug": "test-movie-123",
  "locale": "en-US",
  "error": {
    "type": "NOT_FOUND",
    "message": "The requested movie was not found",
    "technical_message": "Movie not found: test-movie-123",
    "user_message": "This movie does not exist in our database"
  }
}
```

---

## 🧪 Test 2: Tryb REAL - Film nie istnieje (hallucination)

### Cel

Sprawdzić czy w trybie REAL, gdy próbujemy wygenerować opis dla nieistniejącego filmu, job zwraca strukturalny format błędu.

### Kroki

#### 1. Ustaw tryb REAL

```bash
php artisan tinker
config(['services.ai.service' => 'real']);
```

**UWAGA:** Wymaga poprawnego `OPENAI_API_KEY` w `.env`.

#### 2. Wywołaj endpoint dla nieistniejącego filmu

```bash
curl -X GET "http://127.0.0.1:8000/api/v1/movies/non-existent-movie-$(date +%s)" \
  -H "Accept: application/json" | jq .
```

#### 3. Sprawdź status joba (poczekaj na przetworzenie przez Horizon)

```bash
JOB_ID="<job_id_z_kroku_2>"
curl -X GET "http://127.0.0.1:8000/api/v1/jobs/$JOB_ID" \
  -H "Accept: application/json" | jq .
```

**Oczekiwany wynik:**
- Jeśli AI zwróci "not found", `status: "FAILED"` z `error.type: "NOT_FOUND"`
- Jeśli AI zwróci błąd API, `status: "FAILED"` z `error.type: "AI_API_ERROR"`
- Strukturalny format błędu z wszystkimi wymaganymi polami

---

## 🧪 Test 3: Tryb MOCK - Osoba nie istnieje

### Cel

Sprawdzić czy dla osoby (PERSON) strukturalny format błędów działa poprawnie.

### Kroki

#### 1. Ustaw tryb MOCK

```bash
php artisan tinker
config(['services.ai.service' => 'mock']);
```

#### 2. Wywołaj endpoint dla nieistniejącej osoby

```bash
curl -X GET "http://127.0.0.1:8000/api/v1/people/test-person-123" \
  -H "Accept: application/json" | jq .
```

#### 3. Sprawdź status joba

```bash
JOB_ID="<job_id_z_kroku_2>"
curl -X GET "http://127.0.1:8000/api/v1/jobs/$JOB_ID" \
  -H "Accept: application/json" | jq .
```

**Oczekiwany wynik:**
- `status: "FAILED"` (lub `"DONE"` jeśli mock job się powiódł)
- Jeśli `FAILED`, `error.user_message` zawiera słowo "person" (nie "movie")

---

## 🧪 Test 4: Tryb REAL - Błąd AI API (rate limit)

### Cel

Sprawdzić czy błędy AI API (np. rate limit) są poprawnie formatowane.

### Kroki

#### 1. Ustaw tryb REAL

```bash
php artisan tinker
config(['services.ai.service' => 'real']);
```

#### 2. Symuluj błąd rate limit (jeśli możliwe)

**Uwaga:** Wymaga rzeczywistego błędu z OpenAI API lub mockowania odpowiedzi.

#### 3. Sprawdź status joba

**Oczekiwany wynik:**
- `status: "FAILED"`
- `error.type: "AI_API_ERROR"`
- `error.user_message` zawiera "temporarily unavailable"

---

## ✅ Checklist weryfikacji

Dla każdego testu sprawdź:

- [ ] Endpoint zwraca `202 Accepted` z `job_id`
- [ ] `GET /api/v1/jobs/{id}` zwraca status joba
- [ ] Gdy `status: "FAILED"`, pole `error` istnieje i jest obiektem
- [ ] `error.type` jest jednym z: `NOT_FOUND`, `AI_API_ERROR`, `VALIDATION_ERROR`, `UNKNOWN_ERROR`
- [ ] `error.message` istnieje i jest stringiem
- [ ] `error.technical_message` istnieje i zawiera pełny exception message
- [ ] `error.user_message` istnieje i jest czytelny dla użytkownika
- [ ] Dla MOVIE, `user_message` zawiera słowo "movie"
- [ ] Dla PERSON, `user_message` zawiera słowo "person"
- [ ] Format działa zarówno w trybie MOCK jak i REAL

---

## 🔍 Sprawdzanie logów

### Horizon Dashboard

```bash
# Otwórz w przeglądarce
http://127.0.0.1:8000/horizon
```

Sprawdź:
- Failed jobs - czy pokazują błędy
- Job details - czy zawierają informacje o błędach

### Laravel Logs

```bash
tail -f storage/logs/laravel.log | grep -E "failed|error|JobErrorFormatter"
```

---

## 📝 Notatki z testowania

Zapisz wyniki każdego testu:

- **Test 1 (MOCK - Movie):** ✅/❌ - [notatki]
- **Test 2 (REAL - Movie):** ✅/❌ - [notatki]
- **Test 3 (MOCK - Person):** ✅/❌ - [notatki]
- **Test 4 (REAL - AI API Error):** ✅/❌ - [notatki]

---

## 🔗 Powiązane dokumenty

- [Manual Testing Guide](./MANUAL_TESTING_GUIDE.md)
- [Job Error Handling Analysis](../technical/JOB_ERROR_HANDLING_ANALYSIS.md)
- [OpenAPI Schema](../../openapi.yaml)

---

**Ostatnia aktualizacja:** 2025-01-09


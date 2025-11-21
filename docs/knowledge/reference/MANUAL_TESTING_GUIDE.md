# Instrukcje do Manualnego Testowania w Środowisku Lokalnym

> **Data utworzenia:** 2025-11-21  
> **Kontekst:** Szczegółowy przewodnik do manualnego testowania funkcjonalności MovieMind API w środowisku lokalnym  
> **Kategoria:** reference

## 🎯 Cel

Ten dokument zawiera szczegółowe instrukcje do manualnego testowania funkcjonalności MovieMind API w środowisku lokalnym, ze szczególnym uwzględnieniem testowania mechanizmu zapobiegania duplikatom.

---

## 📋 Wymagania Wstępne

### Narzędzia

1. **Docker i Docker Compose** - uruchomione
2. **API dostępne** pod `http://localhost:8000`
3. **Redis** - działa (dla cache)
4. **Horizon** - działa (dla queue jobs)
5. **PostgreSQL** - działa (dla bazy danych)
6. **Narzędzia CLI:**
   - `curl` - do wykonywania requestów HTTP
   - `jq` - opcjonalne, do parsowania JSON (zalecane)

### Sprawdzenie Statusu

```bash
# Sprawdź status Docker containers
docker ps

# Sprawdź status Horizon
docker logs moviemind-horizon | tail -20

# Sprawdź logi aplikacji
tail -50 api/storage/logs/laravel.log
```

**Oczekiwany wynik:** Wszystkie kontenery działają:
- `moviemind-php`
- `moviemind-nginx` (port 8000)
- `moviemind-redis` (port 6379)
- `moviemind-db` (PostgreSQL, port 5433)
- `moviemind-horizon`

---

## 🔧 Przygotowanie Środowiska

### Krok 1: Aktywacja Feature Flagów

#### 1.1. Sprawdź status flagów

```bash
curl -s -X GET "http://localhost:8000/api/v1/admin/flags" \
  -H "Accept: application/json" | jq '.data[] | select(.name | contains("ai_"))'
```

#### 1.2. Aktywuj `ai_description_generation` (jeśli nieaktywny)

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/ai_description_generation" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"state":"on"}' | jq .
```

**Oczekiwany wynik:** `{"name": "ai_description_generation", "active": true}`

#### 1.3. Aktywuj `ai_bio_generation` (jeśli nieaktywny)

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/ai_bio_generation" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"state":"on"}' | jq .
```

**Oczekiwany wynik:** `{"name": "ai_bio_generation", "active": true}`

---

## 🧪 Test 1: Concurrent Requests dla Movie (GET /api/v1/movies/{slug})

### Cel

Sprawdzenie, czy concurrent requests dla tego samego slug zwracają ten sam `job_id` (mechanizm slot management).

### Kroki

#### 1. Przygotuj unikalny slug

```bash
SLUG="test-movie-$(date +%s)"
echo "Testing slug: $SLUG"
```

#### 2. Wykonaj pierwszy request

```bash
JOB1=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id // "ERROR"')
echo "Request 1 job_id: $JOB1"
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Response zawiera: `job_id`, `status: "PENDING"`, `slug`
- Przykład: `"job_id": "7f8a7c8b-f6ac-442b-abf7-8418f0660dfc"`

#### 3. Wykonaj drugi request (natychmiast po pierwszym)

```bash
sleep 0.1  # Krótkie opóźnienie (100ms)
JOB2=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id // "ERROR"')
echo "Request 2 job_id: $JOB2"
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- `job_id` jest **identyczny** jak w pierwszym requeście
- `JOB1 == JOB2`

#### 4. Weryfikacja

```bash
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ]; then
  echo "✅ SUCCESS: Both requests returned the same job_id"
else
  echo "❌ FAIL: Different job_ids or error"
fi
```

**Oczekiwany wynik:** `✅ SUCCESS: Both requests returned the same job_id`

#### 5. Sprawdź logi

```bash
docker logs moviemind-php 2>&1 | grep -E "QueueMovieGenerationAction|generation slot" | tail -5
```

**Oczekiwany wynik w logach:**
- Request 1: `"acquired generation slot"` → `"dispatched new job"`
- Request 2: `"reusing existing job"` (ten sam job_id)

---

## 🧪 Test 2: Concurrent Requests dla Movie (POST /api/v1/generate)

### Cel

Sprawdzenie, czy concurrent requests przez endpoint `/generate` zwracają ten sam `job_id`.

### Kroki

#### 1. Przygotuj unikalny slug

```bash
SLUG="test-generate-movie-$(date +%s)"
echo "Testing slug: $SLUG"
```

#### 2. Wykonaj pierwszy request

```bash
JOB1=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"entity_type\":\"MOVIE\",\"entity_id\":\"$SLUG\"}" \
  | jq -r '.job_id // "ERROR"')
echo "Request 1 job_id: $JOB1"
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Response zawiera: `job_id`, `status: "PENDING"`

#### 3. Wykonaj drugi request

```bash
sleep 0.1
JOB2=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"entity_type\":\"MOVIE\",\"entity_id\":\"$SLUG\"}" \
  | jq -r '.job_id // "ERROR"')
echo "Request 2 job_id: $JOB2"
```

#### 4. Weryfikacja

```bash
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ]; then
  echo "✅ SUCCESS: Both requests returned the same job_id"
else
  echo "❌ FAIL: Different job_ids or error"
fi
```

---

## 🧪 Test 3: Concurrent Requests dla Person (GET /api/v1/people/{slug})

### Cel

Sprawdzenie, czy concurrent requests dla Person zwracają ten sam `job_id`.

### Uwaga

Slug dla Person musi mieć format **2-4 słów** (np. `john-doe`, `mary-jane-watson`). Slug z pojedynczym słowem lub więcej niż 4 słowa może być odrzucony przez walidator.

### Kroki

#### 1. Aktywuj feature flag (jeśli nieaktywny)

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/ai_bio_generation" \
  -H "Content-Type: application/json" \
  -d '{"state":"on"}' | jq .
```

#### 2. Przygotuj unikalny slug (format: 2-4 słowa)

```bash
SLUG="john-doe-$(date +%s | tail -c 4)"
echo "Testing slug: $SLUG"
```

#### 3. Wykonaj pierwszy request

```bash
JOB1=$(curl -s -X GET "http://localhost:8000/api/v1/people/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id // .error // "ERROR"')
echo "Request 1: $JOB1"
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Response zawiera: `job_id`

#### 4. Wykonaj drugi request

```bash
sleep 0.1
JOB2=$(curl -s -X GET "http://localhost:8000/api/v1/people/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id // .error // "ERROR"')
echo "Request 2: $JOB2"
```

#### 5. Weryfikacja

```bash
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ] && [ "$JOB1" != "Person not found" ]; then
  echo "✅ SUCCESS: Both requests returned the same job_id"
else
  echo "❌ FAIL: Different job_ids or error"
fi
```

---

## 🧪 Test 4: Concurrent Requests dla Person (POST /api/v1/generate)

### Cel

Sprawdzenie, czy concurrent requests dla Person przez endpoint `/generate` zwracają ten sam `job_id`.

### Kroki

#### 1. Przygotuj unikalny slug (format: 2-4 słowa)

```bash
SLUG="jane-smith-$(date +%s | tail -c 4)"
echo "Testing slug: $SLUG"
```

#### 2. Wykonaj pierwszy request

```bash
JOB1=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"entity_type\":\"PERSON\",\"entity_id\":\"$SLUG\"}" \
  | jq -r '.job_id // .error // "ERROR"')
echo "Request 1: $JOB1"
```

#### 3. Wykonaj drugi request

```bash
sleep 0.1
JOB2=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"entity_type\":\"PERSON\",\"entity_id\":\"$SLUG\"}" \
  | jq -r '.job_id // .error // "ERROR"')
echo "Request 2: $JOB2"
```

#### 4. Weryfikacja

```bash
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ] && [ "$JOB1" != "Feature not available" ] && [ "$JOB1" != "Invalid slug format" ]; then
  echo "✅ SUCCESS: Both requests returned the same job_id"
else
  echo "❌ FAIL: Different job_ids or error"
fi
```

---

## 🧪 Test 5: Weryfikacja Logów - Sprawdzenie, że tylko jeden job jest dispatchowany

### Cel

Potwierdzenie w logach, że tylko jeden job jest dispatchowany dla concurrent requests.

### Kroki

#### 1. Sprawdź logi dla Movie

```bash
docker logs moviemind-php 2>&1 | grep -E "QueueMovieGenerationAction.*dispatched|acquired generation slot|reusing existing job" | tail -10
```

**Oczekiwany wynik:**
- Dla każdego testu: **jeden** `"dispatched new job"`
- Drugi request: `"reusing existing job"` (ten sam job_id)

#### 2. Sprawdź logi dla Person

```bash
docker logs moviemind-php 2>&1 | grep -E "QueuePersonGenerationAction.*dispatched|acquired generation slot|reusing existing job" | tail -10
```

**Oczekiwany wynik:** Analogicznie jak dla Movie.

#### 3. Sprawdź logi bezpośrednio w pliku

```bash
tail -50 api/storage/logs/laravel.log | grep -E "dispatched new job|reusing existing job|generation slot"
```

---

## 🧪 Test 6: Edge Case - Bardzo Szybkie Concurrent Requests

### Cel

Sprawdzenie, czy mechanizm działa również dla 3+ concurrent requests.

### Kroki

#### 1. Wykonaj 3 requesty prawie jednocześnie

```bash
SLUG="rapid-test-$(date +%s)"
echo "Testing rapid concurrent requests: $SLUG"

# Request 1
JOB1=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id')

# Request 2 (natychmiast)
JOB2=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id')

# Request 3 (natychmiast)
JOB3=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id')

echo "Job 1: $JOB1"
echo "Job 2: $JOB2"
echo "Job 3: $JOB3"

# Weryfikacja
if [ "$JOB1" = "$JOB2" ] && [ "$JOB2" = "$JOB3" ] && [ "$JOB1" != "ERROR" ]; then
  echo "✅ SUCCESS: All 3 requests returned the same job_id"
else
  echo "❌ FAIL: Different job_ids"
fi
```

**Oczekiwany wynik:** Wszystkie 3 requesty zwracają ten sam `job_id`.

---

## 🧪 Test 7: Weryfikacja w Bazie Danych - Brak Duplikatów

### Cel

Sprawdzenie, czy w bazie danych nie ma duplikatów (unique constraint działa).

### Kroki

#### 1. Sprawdź, czy nie ma duplikatów w tabeli movies

```bash
docker exec moviemind-db psql -U moviemind -d moviemind -c \
  "SELECT slug, COUNT(*) as count FROM movies GROUP BY slug HAVING COUNT(*) > 1;"
```

**Oczekiwany wynik:** Brak wyników (brak duplikatów).

#### 2. Sprawdź, czy nie ma duplikatów w tabeli people

```bash
docker exec moviemind-db psql -U moviemind -d moviemind -c \
  "SELECT slug, COUNT(*) as count FROM people GROUP BY slug HAVING COUNT(*) > 1;"
```

**Oczekiwany wynik:** Brak wyników (brak duplikatów).

---

## 🧪 Test 8: Test Statusu Joba - Weryfikacja, że job istnieje

### Cel

Sprawdzenie, czy job_id zwrócony przez API rzeczywiście istnieje i można sprawdzić jego status.

### Kroki

#### 1. Pobierz job_id z poprzedniego testu

```bash
SLUG="status-test-$(date +%s)"
JOB_ID=$(curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq -r '.job_id')
echo "Job ID: $JOB_ID"
```

#### 2. Sprawdź status joba

```bash
curl -s -X GET "http://localhost:8000/api/v1/jobs/$JOB_ID" \
  -H "Accept: application/json" | jq .
```

**Oczekiwany wynik:**
- Status: `200 OK`
- Response zawiera: `job_id`, `status` (PENDING/IN_PROGRESS/DONE/FAILED), `entity`, `slug`

---

## ✅ Checklist Końcowy

- [ ] Test 1: Movie GET endpoint - concurrent requests zwracają ten sam job_id
- [ ] Test 2: Movie POST /generate - concurrent requests zwracają ten sam job_id
- [ ] Test 3: Person GET endpoint - concurrent requests zwracają ten sam job_id
- [ ] Test 4: Person POST /generate - concurrent requests zwracają ten sam job_id
- [ ] Test 5: Logi potwierdzają tylko jeden "dispatched new job" per test
- [ ] Test 6: Logi pokazują "reusing existing job" dla drugiego requestu
- [ ] Test 7: Edge case - 3 szybkie requesty zwracają ten sam job_id
- [ ] Test 8: Baza danych - brak duplikatów w tabelach movies i people
- [ ] Test 9: Status joba - job istnieje i można sprawdzić jego status

---

## 🔧 Troubleshooting

### Problem: Feature flag nieaktywny

**Objawy:**
- Response: `{"error": "Feature not available"}` lub `{"error": "Person not found"}`

**Rozwiązanie:**
```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/ai_description_generation" \
  -H "Content-Type: application/json" \
  -d '{"state":"on"}' | jq .
```

### Problem: "Person not found" zamiast 202

**Objawy:**
- GET `/api/v1/people/{slug}` zwraca 404 zamiast 202

**Rozwiązanie:**
- Sprawdź, czy `ai_bio_generation` jest aktywny:
```bash
curl -s -X GET "http://localhost:8000/api/v1/admin/flags" | jq '.data[] | select(.name == "ai_bio_generation")'
```

### Problem: "Invalid slug format" dla Person

**Objawy:**
- Response: `{"error": "Invalid slug format", "message": "Slug does not match expected person slug format"}`

**Rozwiązanie:**
- Użyj slug w formacie **2-4 słów** (np. `john-doe`, `mary-jane-watson`)
- **Nie używaj:** `test-person-123` (zawiera liczby, może być odrzucony)
- **Używaj:** `john-doe`, `jane-smith`, `mary-jane-watson`

### Problem: Różne job_id dla concurrent requests

**Objawy:**
- Request 1: `job_id: abc-123`
- Request 2: `job_id: def-456` (różny!)

**Rozwiązanie:**
1. Sprawdź logi:
```bash
docker logs moviemind-php 2>&1 | grep -E "generation slot|reusing existing job" | tail -10
```

2. Sprawdź Redis (czy cache działa):
```bash
docker exec moviemind-redis redis-cli KEYS "ai_job_inflight:*"
```

3. Sprawdź, czy Horizon działa:
```bash
docker logs moviemind-horizon | tail -20
```

### Problem: Brak logów

**Objawy:**
- Brak logów w `docker logs moviemind-php`

**Rozwiązanie:**
1. Sprawdź logi bezpośrednio w pliku:
```bash
tail -100 api/storage/logs/laravel.log
```

2. Sprawdź uprawnienia do pliku:
```bash
ls -la api/storage/logs/
```

3. Sprawdź konfigurację logowania:
```bash
docker exec moviemind-php php artisan tinker --execute="echo config('logging.default');"
```

---

## 📝 Przykładowy Skrypt Testowy

Możesz zapisać to jako `test-duplicate-prevention.sh`:

```bash
#!/bin/bash

BASE_URL="http://localhost:8000"

echo "=== Test 1: Movie GET endpoint ==="
SLUG="test-movie-$(date +%s)"
JOB1=$(curl -s -X GET "$BASE_URL/api/v1/movies/$SLUG" -H "Accept: application/json" | jq -r '.job_id')
sleep 0.1
JOB2=$(curl -s -X GET "$BASE_URL/api/v1/movies/$SLUG" -H "Accept: application/json" | jq -r '.job_id')
if [ "$JOB1" = "$JOB2" ]; then echo "✅ PASS"; else echo "❌ FAIL"; fi

echo "=== Test 2: Movie POST /generate ==="
SLUG="test-gen-$(date +%s)"
JOB1=$(curl -s -X POST "$BASE_URL/api/v1/generate" -H "Content-Type: application/json" -d "{\"entity_type\":\"MOVIE\",\"entity_id\":\"$SLUG\"}" | jq -r '.job_id')
sleep 0.1
JOB2=$(curl -s -X POST "$BASE_URL/api/v1/generate" -H "Content-Type: application/json" -d "{\"entity_type\":\"MOVIE\",\"entity_id\":\"$SLUG\"}" | jq -r '.job_id')
if [ "$JOB1" = "$JOB2" ]; then echo "✅ PASS"; else echo "❌ FAIL"; fi

echo "=== Test 3: Person GET endpoint ==="
# Aktywuj feature flag
curl -s -X POST "$BASE_URL/api/v1/admin/flags/ai_bio_generation" -H "Content-Type: application/json" -d '{"state":"on"}' > /dev/null
SLUG="john-doe-$(date +%s | tail -c 4)"
JOB1=$(curl -s -X GET "$BASE_URL/api/v1/people/$SLUG" -H "Accept: application/json" | jq -r '.job_id // .error')
sleep 0.1
JOB2=$(curl -s -X GET "$BASE_URL/api/v1/people/$SLUG" -H "Accept: application/json" | jq -r '.job_id // .error')
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ] && [ "$JOB1" != "Person not found" ]; then echo "✅ PASS"; else echo "❌ FAIL"; fi

echo "=== Test 4: Person POST /generate ==="
SLUG="jane-smith-$(date +%s | tail -c 4)"
JOB1=$(curl -s -X POST "$BASE_URL/api/v1/generate" -H "Content-Type: application/json" -d "{\"entity_type\":\"PERSON\",\"entity_id\":\"$SLUG\"}" | jq -r '.job_id // .error')
sleep 0.1
JOB2=$(curl -s -X POST "$BASE_URL/api/v1/generate" -H "Content-Type: application/json" -d "{\"entity_type\":\"PERSON\",\"entity_id\":\"$SLUG\"}" | jq -r '.job_id // .error')
if [ "$JOB1" = "$JOB2" ] && [ "$JOB1" != "ERROR" ] && [ "$JOB1" != "Feature not available" ] && [ "$JOB1" != "Invalid slug format" ]; then echo "✅ PASS"; else echo "❌ FAIL"; fi

echo "=== Tests completed ==="
```

**Użycie:**
```bash
chmod +x test-duplicate-prevention.sh
./test-duplicate-prevention.sh
```

---

## 🔗 Powiązane Dokumenty

- [Locking Strategies for AI Generation](../technical/LOCKING_STRATEGIES_FOR_AI_GENERATION.md)
- [ADR-007: Blokady generowania opisów AI](../../adr/README.md#adr-007-blokady-generowania-opisów-ai)
- [Horizon Setup](./HORIZON_SETUP.md)
- [OpenAI Setup and Testing](./OPENAI_SETUP_AND_TESTING.md)

---

## 📌 Notatki

- **Aktualizacja dokumentu:** Ten dokument powinien być aktualizowany za każdym razem, gdy zmienia się:
  - Endpointy API
  - Mechanizmy zapobiegania duplikatom
  - Feature flagi
  - Format odpowiedzi API
  - Wymagania dotyczące slug formatów
  - Struktura logów

- **Wersja:** Ten dokument jest wersją polską. Wersja angielska znajduje się w `docs/knowledge/reference/MANUAL_TESTING_GUIDE.en.md`

---

**Ostatnia aktualizacja:** 2025-11-21


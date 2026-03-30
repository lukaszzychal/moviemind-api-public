# Instrukcje do Manualnego Testowania w Środowisku Lokalnym

> **Data utworzenia:** 2025-11-21  
> **Kontekst:** Szczegółowy przewodnik do manualnego testowania funkcjonalności MovieMind API w środowisku lokalnym  
> **Kategoria:** reference

## 🎯 Cel

Ten dokument zawiera szczegółowe instrukcje do manualnego testowania funkcjonalności MovieMind API w środowisku lokalnym, ze szczególnym uwzględnieniem testowania mechanizmu zapobiegania duplikatom.

---

## 📋 Przegląd Przypadków Użycia

Ten dokument zawiera instrukcje do testowania następujących przypadków użycia:

| #  | Przypadek Użycia                                    | Opis                                                                                       | Endpoint                      |
|----|-----------------------------------------------------|-------------------------------------------------------------------------------------------|-------------------------------|
| 1  | **Concurrent Requests - Movie (GET)**                | Weryfikacja, że równoległe requesty dla tego samego slug filmu zwracają ten sam `job_id` | `GET /api/v1/movies/{slug}`   |
| 2  | **Concurrent Requests - Movie (POST)**               | Weryfikacja mechanizmu slot management dla endpointu `/generate` dla filmów              | `POST /api/v1/generate`       |
| 3  | **Concurrent Requests - Person (GET)**               | Weryfikacja, że równoległe requesty dla tego samego slug osoby zwracają ten sam `job_id` | `GET /api/v1/people/{slug}`   |
| 4  | **Concurrent Requests - Person (POST)**              | Weryfikacja mechanizmu slot management dla endpointu `/generate` dla osób                | `POST /api/v1/generate`       |
| 5  | **Weryfikacja Logów - Jedno Dispatchowanie**        | Potwierdzenie w logach, że tylko jeden job jest dispatchowany dla concurrent requests    | Logi aplikacji                |
| 6  | **Edge Case - Bardzo Szybkie Concurrent Requests**  | Testowanie mechanizmu dla 3+ równoległych requestów                                       | `GET /api/v1/movies/{slug}`   |
| 7  | **Weryfikacja w Bazie Danych - Brak Duplikatów**    | Sprawdzenie, czy w bazie danych nie ma duplikatów (unique constraint)                     | Baza danych PostgreSQL        |
| 8  | **Test Statusu Joba**                                | Weryfikacja, że `job_id` zwrócony przez API istnieje i można sprawdzić jego status        | `GET /api/v1/jobs/{id}`       |
| 9  | **Generowanie z domyślnym ContextTag**               | Weryfikacja, że gdy nie podano `context_tag`, system używa domyślnego ContextTag          | `POST /api/v1/generate`       |
| 10 | **Generowanie z konkretnym ContextTag**              | Weryfikacja obsługi konkretnego ContextTag (np. "humorous") podczas generowania opisu     | `POST /api/v1/generate`       |
| 11 | **Edge Case - Nieprawidłowy ContextTag**             | Weryfikacja obsługi nieprawidłowego ContextTag (fallback lub błąd walidacji)              | `POST /api/v1/generate`       |
| 12 | **Duplikacja - Różne ContextTag (KLUCZOWY)**         | Weryfikacja, że concurrent requests z różnymi ContextTag zwracają różne job_id i tworzą różne opisy | `POST /api/v1/generate`       |
| 13 | **Co się dzieje gdy nie ma ContextTag w bazie**      | Weryfikacja zachowania, gdy pobieramy film bez opisu z danym ContextTag                   | `GET /api/v1/movies/{slug}`   |
| 14 | **Debug Configuration**                               | Weryfikacja konfiguracji serwisu (AI_SERVICE, OpenAI, etc.) - wymaga feature flag         | `GET /api/v1/admin/debug/config`   |
| 15 | **Weryfikacja TMDb z Feature Flagiem (Movie)**       | Weryfikacja, czy feature flag `tmdb_verification` kontroluje weryfikację TMDb dla filmów  | `GET /api/v1/movies/{slug}`   |
| 16 | **Weryfikacja TMDb z Feature Flagiem (Person)**      | Weryfikacja, czy feature flag `tmdb_verification` kontroluje weryfikację TMDb dla osób    | `GET /api/v1/people/{slug}`   |

### Kluczowe Mechanizmy Testowane

- **Slot Management** - mechanizm zapobiegania duplikatom poprzez sloty generowania
- **Cache Operations** - operacje cache dla statusu jobów i slotów
- **Event Handling** - weryfikacja, że tylko jeden event jest dispatchowany
- **Database Integrity** - sprawdzenie unikalności rekordów w bazie danych
- **Job Status Tracking** - śledzenie statusu asynchronicznych jobów
- **ContextTag Management** - obsługa różnych ContextTag (DEFAULT, MODERN, CRITICAL, HUMOROUS) i zapobieganie duplikatom dla różnych tagów

---

## 🚀 Uruchomienie Środowiska Lokalnego (Docker)

### Krok 1: Przygotowanie Środowiska

#### 1.1. Skopiuj plik konfiguracyjny `.env`

```bash
# Z głównego katalogu projektu
cp env/local.env.example api/.env
```

#### 1.2. Edytuj plik `api/.env` (opcjonalnie)

```bash
# Otwórz plik w edytorze, aby ustawić zmienne środowiskowe (np. OPENAI_API_KEY)
# Dla testowania z mock AI nie jest wymagane
```

**Domyślne wartości:**
- `AI_SERVICE=mock` - używa mock AI (nie wymaga klucza OpenAI)
- `OPENAI_API_KEY=` - opcjonalne, wymagane tylko dla `AI_SERVICE=real`

### Krok 2: Uruchomienie Kontenerów Docker

#### 2.1. Uruchom wszystkie serwisy

```bash
# Z głównego katalogu projektu
docker compose up -d --build
```

**Co to robi:**
- Buduje obrazy Docker (jeśli potrzeba)
- Uruchamia wszystkie kontenery w tle (`-d`):
  - `moviemind-php` - aplikacja PHP/Laravel
  - `moviemind-nginx` - serwer web (port 8000)
  - `moviemind-db` - PostgreSQL (port 5433)
  - `moviemind-redis` - Redis (port 6379)
  - `moviemind-horizon` - Laravel Horizon (queue worker)

**Oczekiwany wynik:**
```bash
[+] Running 5/5
 ✔ Container moviemind-redis    Started
 ✔ Container moviemind-db        Started
 ✔ Container moviemind-php       Started
 ✔ Container moviemind-nginx     Started
 ✔ Container moviemind-horizon   Started
```

#### 2.2. Sprawdź status kontenerów

```bash
docker ps
```

**Oczekiwany wynik:** Wszystkie kontenery powinny mieć status `Up`:
```
CONTAINER ID   IMAGE                    STATUS
xxx            moviemind-php            Up X seconds
xxx            moviemind-nginx          Up X seconds
xxx            moviemind-db             Up X seconds
xxx            moviemind-redis          Up X seconds
xxx            moviemind-horizon        Up X seconds
```

### Krok 3: Instalacja Zależności PHP

#### 3.1. Zainstaluj zależności Composerls -l

```bash
docker compose exec php composer install
```

**Oczekiwany wynik:** Instalacja pakietów PHP bez błędów.

### Krok 4: Konfiguracja Aplikacji

#### 4.1. Wygeneruj klucz aplikacji Laravel

```bash
docker compose exec php php artisan key:generate
```

**Oczekiwany wynik:** `Application key set successfully.`

#### 4.2. Uruchom migracje bazy danych i seedery

```bash
docker compose exec php php artisan migrate --seed
```

**Oczekiwany wynik:**
```
Migration table created successfully.
Migrating: 2024_01_01_000001_create_movies_table
Migrated:  2024_01_01_000001_create_movies_table
...
Seeding: MovieSeeder
Seeding: ActorSeeder
...
Database seeded successfully.
```

### Krok 5: Weryfikacja Uruchomienia

#### 5.1. Sprawdź, czy API odpowiada

```bash
curl -s http://localhost:8000/api/v1/health || echo "API not responding"
```

**Oczekiwany wynik:** Status `200 OK` lub odpowiedź JSON (jeśli endpoint istnieje).

Alternatywnie:
```bash
curl -s -I http://localhost:8000 | head -1
```

**Oczekiwany wynik:** `HTTP/1.1 200 OK` lub `HTTP/1.1 404 Not Found` (w zależności od konfiguracji routingu).

#### 5.2. Sprawdź logi Horizon (queue worker)

```bash
docker compose logs horizon | tail -20
```

**Oczekiwany wynik:** Horizon powinien być uruchomiony:
```
Horizon started successfully.
Processing jobs from queue: default
```

#### 5.3. Sprawdź logi aplikacji

```bash
docker compose logs php | tail -20
```

**Oczekiwany wynik:** Brak błędów krytycznych.

### Krok 6: Przydatne Komendy

#### Zatrzymanie kontenerów

```bash
docker compose down
```

#### Zatrzymanie i usunięcie wolumenów (reset bazy danych)

```bash
docker compose down -v
```

**Uwaga:** To usunie wszystkie dane z bazy danych!

#### Restart kontenerów

```bash
docker compose restart
```

#### Restart konkretnego kontenera

```bash
docker compose restart horizon
```

#### Podgląd logów na żywo

```bash
# Wszystkie kontenery
docker compose logs -f

# Konkretny kontener
docker compose logs -f horizon
docker compose logs -f php
```

#### Wykonanie komendy w kontenerze

```bash
# Wykonaj Artisan command
docker compose exec php php artisan route:list

# Otwórz shell w kontenerze
docker compose exec php bash

# Sprawdź wersję PHP
docker compose exec php php -v
```

### Troubleshooting: Problem z uruchomieniem Dockera

#### Problem: Port 8000 już zajęty

**Objawy:**
```
Error: Bind for 0.0.0.0:8000 failed: port is already allocated
```

**Rozwiązanie:**
1. Znajdź proces używający portu 8000:
   ```bash
   lsof -i :8000
   ```
2. Zatrzymaj proces lub zmień port w `compose.yml` (linia 41: `"8000:80"` → `"8001:80"`)

#### Problem: Port 5433 już zajęty (PostgreSQL)

**Objawy:**
```
Error: Bind for 0.0.0.0:5433 failed: port is already allocated
```

**Rozwiązanie:**
1. Zmień port w `compose.yml` (linia 91: `"5433:5432"` → `"5434:5432"`)
2. Zaktualizuj `DB_PORT` w `api/.env` jeśli używasz zewnętrznego klienta

#### Problem: Kontenery nie startują

**Objawy:**
- Kontenery się restartują w pętli
- Błędy w logach

**Rozwiązanie:**
1. Sprawdź logi:
   ```bash
   docker compose logs
   ```
2. Sprawdź, czy plik `api/.env` istnieje:
   ```bash
   ls -la api/.env
   ```
3. Sprawdź uprawnienia do katalogów:
   ```bash
   ls -la api/storage
   ls -la api/bootstrap/cache
   ```
4. Wyczyść i uruchom ponownie:
   ```bash
   docker compose down -v
   docker compose up -d --build
   ```

#### Problem: Horizon nie działa

**Objawy:**
- Brak logów Horizon
- Jobs nie są przetwarzane

**Rozwiązanie:**
1. Sprawdź, czy kontener Horizon jest uruchomiony:
   ```bash
   docker ps | grep horizon
   ```
2. Sprawdź logi:
   ```bash
   docker compose logs horizon
   ```
3. Restartuj Horizon:
   ```bash
   docker compose restart horizon
   ```

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

#### 1.4. Aktywuj `tmdb_verification` (jeśli nieaktywny)

Feature flag kontrolujący weryfikację TMDb przed generowaniem AI. Domyślnie włączony.

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/tmdb_verification" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"state":"on"}' | jq .
```

**Oczekiwany wynik:** `{"name": "tmdb_verification", "active": true}`

**Uwaga:** Gdy `tmdb_verification` jest wyłączony, system pozwala na generowanie bez weryfikacji TMDb (fallback do AI). Gdy włączony, wymaga weryfikacji TMDb przed generowaniem.

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
- Jeśli `status: "FAILED"`, response zawiera również obiekt `error` z polami:
  - `type` (NOT_FOUND, AI_API_ERROR, VALIDATION_ERROR, UNKNOWN_ERROR)
  - `message` (krótki komunikat techniczny)
  - `technical_message` (pełny exception message)
  - `user_message` (komunikat dla użytkownika)

**Przykład odpowiedzi z błędem FAILED:**
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

## 🧪 Test 9: Generowanie z domyślnym ContextTag

### Cel

Weryfikacja, że gdy nie podano `context_tag` w requestcie, system używa domyślnego ContextTag (DEFAULT, MODERN, CRITICAL lub HUMOROUS w zależności od istniejących opisów).

### Kroki

#### 1. Wygeneruj opis bez podania context_tag

```bash
SLUG="default-context-$(date +%s)"
curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\"
  }" | jq .
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Response zawiera: `job_id`, `status: "PENDING"`, `slug`, `locale: "en-US"`
- `context_tag` może być null lub domyślny (DEFAULT) - zależy od implementacji

#### 2. Sprawdź status joba i poczekaj na ukończenie

```bash
JOB_ID=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{\"entity_type\": \"MOVIE\", \"entity_id\": \"$SLUG\"}" | jq -r '.job_id')

# Poczekaj na ukończenie joba (lub sprawdź status)
sleep 5

curl -s -X GET "http://localhost:8000/api/v1/jobs/$JOB_ID" | jq .
```

#### 3. Sprawdź w bazie danych, jaki ContextTag został użyty

```bash
docker exec moviemind-db psql -U moviemind -d moviemind -c "
  SELECT id, context_tag, locale 
  FROM movie_descriptions 
  WHERE movie_id = (SELECT id FROM movies WHERE slug = '$SLUG')
  ORDER BY created_at DESC 
  LIMIT 1;
"
```

**Oczekiwany wynik:**
- Powinien istnieć opis z context_tag = 'DEFAULT' (lub pierwszy dostępny z kolejności: DEFAULT, MODERN, CRITICAL, HUMOROUS)

---

## 🧪 Test 10: Generowanie z konkretnym ContextTag (humorous)

### Cel

Weryfikacja, że system poprawnie obsługuje konkretny ContextTag podczas generowania opisu.

### Kroki

#### 1. Wygeneruj opis z context_tag="humorous"

```bash
SLUG="humorous-context-$(date +%s)"
curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\",
    \"context_tag\": \"humorous\"
  }" | jq .
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Response zawiera: `job_id`, `status: "PENDING"`, `context_tag: "humorous"`

#### 2. Sprawdź status joba i poczekaj na ukończenie

```bash
JOB_ID=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{\"entity_type\": \"MOVIE\", \"entity_id\": \"$SLUG\", \"context_tag\": \"humorous\"}" | jq -r '.job_id')

sleep 5

curl -s -X GET "http://localhost:8000/api/v1/jobs/$JOB_ID" | jq .
```

#### 3. Sprawdź w bazie danych, czy ContextTag został zapisany

```bash
docker exec moviemind-db psql -U moviemind -d moviemind -c "
  SELECT id, context_tag, locale, LEFT(text, 50) as text_preview
  FROM movie_descriptions 
  WHERE movie_id = (SELECT id FROM movies WHERE slug = '$SLUG')
  ORDER BY created_at DESC 
  LIMIT 1;
"
```

**Oczekiwany wynik:**
- Powinien istnieć opis z context_tag = 'humorous'

---

## 🧪 Test 11: Edge Case - Nieprawidłowy ContextTag

### Cel

Weryfikacja, jak system obsługuje nieprawidłowy ContextTag (fallback do domyślnego lub błąd walidacji).

### Kroki

#### 1. Spróbuj wygenerować opis z nieprawidłowym context_tag

```bash
SLUG="invalid-context-$(date +%s)"
curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\",
    \"context_tag\": \"invalid-tag\"
  }" | jq .
```

**Oczekiwany wynik:**
- Status: `202 Accepted` (system może zaakceptować request, ale zignorować nieprawidłowy tag)
- LUB Status: `422 Unprocessable Entity` (błąd walidacji, jeśli jest walidacja)

#### 2. Sprawdź, jaki ContextTag został faktycznie użyty

```bash
JOB_ID=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{\"entity_type\": \"MOVIE\", \"entity_id\": \"$SLUG\", \"context_tag\": \"invalid-tag\"}" | jq -r '.job_id')

sleep 5

# Sprawdź w bazie danych
docker exec moviemind-db psql -U moviemind -d moviemind -c "
  SELECT id, context_tag 
  FROM movie_descriptions 
  WHERE movie_id = (SELECT id FROM movies WHERE slug = '$SLUG')
  ORDER BY created_at DESC 
  LIMIT 1;
"
```

**Oczekiwany wynik:**
- ContextTag powinien być domyślny (DEFAULT) lub poprawny (jeśli system normalizuje/naprawia)

---

## 🧪 Test 12: Duplikacja - Różne ContextTag (KLUCZOWY)

### Cel

Weryfikacja, że concurrent requests z różnymi ContextTag zwracają różne job_id i tworzą różne opisy w bazie danych. To kluczowy test mechanizmu slot management dla różnych ContextTag.

### Kroki

#### 1. Wygeneruj równoległe requesty z różnymi context_tag

```bash
SLUG="different-context-$(date +%s)"

# Request 1: modern
JOB_ID_1=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\",
    \"context_tag\": \"modern\"
  }" | jq -r '.job_id')

# Request 2: humorous (natychmiast po pierwszym)
JOB_ID_2=$(curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\",
    \"context_tag\": \"humorous\"
  }" | jq -r '.job_id')

echo "Job ID 1 (modern): $JOB_ID_1"
echo "Job ID 2 (humorous): $JOB_ID_2"
```

**Oczekiwany wynik:**
- Oba requesty zwracają status `202 Accepted`
- **Job ID 1 i Job ID 2 powinny być RÓŻNE** (różne ContextTag = różne sloty)
- Oba joby powinny mieć status `PENDING`

#### 2. Sprawdź status obu jobów

```bash
echo "=== Job 1 (modern) ==="
curl -s -X GET "http://localhost:8000/api/v1/jobs/$JOB_ID_1" | jq .

echo "=== Job 2 (humorous) ==="
curl -s -X GET "http://localhost:8000/api/v1/jobs/$JOB_ID_2" | jq .
```

#### 3. Poczekaj na ukończenie jobów i sprawdź w bazie danych

```bash
sleep 10

docker exec moviemind-db psql -U moviemind -d moviemind -c "
  SELECT id, context_tag, locale, LEFT(text, 50) as text_preview
  FROM movie_descriptions 
  WHERE movie_id = (SELECT id FROM movies WHERE slug = '$SLUG')
  ORDER BY context_tag;
"
```

**Oczekiwany wynik:**
- Powinny istnieć **dwa opisy** dla tego samego filmu:
  - Jeden z `context_tag = 'modern'`
  - Drugi z `context_tag = 'humorous'`
- Oba opisy powinny mieć ten sam `locale` (en-US)

#### 4. Weryfikacja logów - oba joby powinny być dispatchowane

```bash
docker logs moviemind-php 2>&1 | grep -E "generation slot|context_tag.*modern|context_tag.*humorous" | tail -10
```

**Oczekiwany wynik:**
- Logi powinny pokazywać dwa różne sloty (różne ContextTag)
- Oba joby powinny być dispatchowane

---

## 🧪 Test 13: Co się dzieje gdy nie ma ContextTag w bazie

### Cel

Weryfikacja zachowania, gdy pobieramy film, który nie ma opisu z danym ContextTag.

### Kroki

#### 1. Utwórz film z opisem z jednym ContextTag (np. modern)

```bash
SLUG="single-context-$(date +%s)"

# Wygeneruj opis z context_tag="modern"
curl -s -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -d "{
    \"entity_type\": \"MOVIE\",
    \"entity_id\": \"$SLUG\",
    \"context_tag\": \"modern\"
  }" | jq .

sleep 5
```

#### 2. Sprawdź, co zwraca GET /api/v1/movies/{slug} (bez parametrów)

```bash
curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq .
```

**Oczekiwany wynik:**
- Status: `200 OK`
- Response zawiera film z domyślnym opisem (default_description_id)
- Opis powinien mieć context_tag="modern" (bo to jedyny opis)

#### 3. Sprawdź, co zwraca GET z description_id dla nieistniejącego ContextTag

Najpierw znajdź movie_id:
```bash
MOVIE_ID=$(docker exec moviemind-db psql -U moviemind -d moviemind -t -c "
  SELECT id FROM movies WHERE slug = '$SLUG';
" | tr -d ' ')

echo "Movie ID: $MOVIE_ID"
```

Teraz sprawdź, co się dzieje, gdy próbujemy użyć description_id, który nie istnieje:
```bash
# Pobierz istniejący description_id
DESC_ID=$(docker exec moviemind-db psql -U moviemind -d moviemind -t -c "
  SELECT id FROM movie_descriptions WHERE movie_id = $MOVIE_ID LIMIT 1;
" | tr -d ' ')

echo "Description ID: $DESC_ID"

# Spróbuj użyć description_id dla nieistniejącego opisu
INVALID_DESC_ID=99999
curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG?description_id=$INVALID_DESC_ID" \
  -H "Accept: application/json" | jq .
```

**Oczekiwany wynik:**
- Status: `404 Not Found` LUB `200 OK` z domyślnym opisem (zależy od implementacji)

---

## 🧪 Test 14: Debug Configuration Endpoint

### Cel

Weryfikacja, że endpoint debug zwraca poprawną konfigurację serwisu, w tym AI_SERVICE, OpenAI settings i inne parametry. Endpoint jest chroniony przez feature flag `debug_endpoints`.

### Kroki

#### 1. Włącz feature flag `debug_endpoints`

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/debug_endpoints" \
  -H "Content-Type: application/json" \
  -d '{"state":"on"}' | jq .
```

**Oczekiwany wynik:**
- Status: `200 OK`
- Response zawiera: `{"name": "debug_endpoints", "active": true}`

#### 2. Wywołaj endpoint debug

```bash
curl -s -X GET "http://localhost:8000/api/v1/admin/debug/config" | jq .
```

**Oczekiwany wynik:**
- Status: `200 OK`
- Response zawiera sekcje: `environment`, `openai`, `queue`, `cache`, `database`, `services`
- `environment.ai_service_env` i `environment.ai_service_config` są zgodne
- `environment.is_real` lub `environment.is_mock` jest ustawione poprawnie
- `openai.api_key_set` jest `true` jeśli klucz jest ustawiony
- `openai.api_key_preview` pokazuje pierwsze 10 znaków (bez pełnego klucza)

#### 3. Sprawdź konfigurację AI_SERVICE

```bash
curl -s -X GET "http://localhost:8000/api/v1/admin/debug/config" | jq '.environment'
```

**Oczekiwany wynik:**
- `ai_service_env` - wartość z .env
- `ai_service_config` - wartość z config('services.ai.service')
- `ai_service_selector` - wartość z AiServiceSelector::getService()
- Wszystkie trzy wartości powinny być zgodne

#### 4. Sprawdź konfigurację OpenAI

```bash
curl -s -X GET "http://localhost:8000/api/v1/admin/debug/config" | jq '.openai'
```

**Oczekiwany wynik:**
- `api_key_set: true` jeśli OPENAI_API_KEY jest ustawiony
- `model` - nazwa modelu (np. "gpt-4o-mini")
- `api_url` - URL endpointu OpenAI
- `backoff_enabled` - czy backoff jest włączony
- `backoff_intervals` - tablica interwałów

#### 5. Test z wyłączonym feature flagiem

```bash
# Wyłącz feature flag
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/debug_endpoints" \
  -H "Content-Type: application/json" \
  -d '{"state":"off"}' | jq .

# Spróbuj wywołać endpoint
curl -s -X GET "http://localhost:8000/api/v1/admin/debug/config" | jq .
```

**Oczekiwany wynik:**
- Status: `403 Forbidden`
- Response: `{"error": "Forbidden", "message": "Debug endpoints are disabled. Enable feature flag \"debug_endpoints\" to access this endpoint."}`

#### 6. Weryfikacja w różnych środowiskach

```bash
# W local/dev - włącz feature flag i sprawdź
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/debug_endpoints" \
  -H "Content-Type: application/json" \
  -d '{"state":"on"}' | jq .

curl -s -X GET "http://localhost:8000/api/v1/admin/debug/config" | jq '.environment.app_env'
```

**Oczekiwany wynik:**
- W local/dev: dostęp po włączeniu feature flagu
- W produkcji: domyślnie wyłączony (wymaga ręcznego włączenia feature flagu)

---

## 🧪 Test 15: Weryfikacja TMDb z Feature Flagiem (Movie)

### Cel

Sprawdzenie, czy weryfikacja TMDb dla filmów jest kontrolowana przez feature flag `tmdb_verification`.

### Kroki

#### 1. Przygotuj slug filmu, który NIE istnieje w TMDb (lub jest bardzo mało prawdopodobny)

```bash
SLUG="non-existent-movie-$(date +%s)"
echo "Testing slug: $SLUG"
```

#### 2. Wyłącz feature flag `tmdb_verification`

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/tmdb_verification" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"state":"off"}' | jq .
```

**Oczekiwany wynik:** `{"name": "tmdb_verification", "active": false}`

#### 3. Spróbuj pobrać film (powinno zainicjować generację AI, status 202)

```bash
curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq .
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Response zawiera: `job_id`, `status: "PENDING"`, `slug`
- Logi powinny zawierać: `MovieController: TMDb verification skipped by feature flag, proceeding with AI generation fallback`

#### 4. Włącz feature flag `tmdb_verification`

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/tmdb_verification" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"state":"on"}' | jq .
```

**Oczekiwany wynik:** `{"name": "tmdb_verification", "active": true}`

#### 5. Spróbuj pobrać ten sam film (powinno zwrócić 404, ponieważ TMDb nie znajdzie)

```bash
curl -s -X GET "http://localhost:8000/api/v1/movies/$SLUG" \
  -H "Accept: application/json" | jq .
```

**Oczekiwany wynik:**
- Status: `404 Not Found`
- Response zawiera: `{"error": "Movie not found"}`
- Logi powinny zawierać: `TmdbVerificationService: searching TMDb for movie` i `TmdbVerificationService: movie not found in TMDb`

---

## 🧪 Test 16: Weryfikacja TMDb z Feature Flagiem (Person)

### Cel

Sprawdzenie, czy weryfikacja TMDb dla osób jest kontrolowana przez feature flag `tmdb_verification`.

### Kroki

#### 1. Przygotuj slug osoby, która NIE istnieje w TMDb (lub jest bardzo mało prawdopodobna)

```bash
SLUG="non-existent-person-$(date +%s)"
echo "Testing slug: $SLUG"
```

#### 2. Wyłącz feature flag `tmdb_verification`

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/tmdb_verification" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"state":"off"}' | jq .
```

**Oczekiwany wynik:** `{"name": "tmdb_verification", "active": false}`

#### 3. Spróbuj pobrać osobę (powinno zainicjować generację AI, status 202)

```bash
curl -s -X GET "http://localhost:8000/api/v1/people/$SLUG" \
  -H "Accept: application/json" | jq .
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Response zawiera: `job_id`, `status: "PENDING"`, `slug`
- Logi powinny zawierać: `PersonController: TMDb verification skipped by feature flag, proceeding with AI generation fallback`

#### 4. Włącz feature flag `tmdb_verification`

```bash
curl -s -X POST "http://localhost:8000/api/v1/admin/flags/tmdb_verification" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"state":"on"}' | jq .
```

**Oczekiwany wynik:** `{"name": "tmdb_verification", "active": true}`

#### 5. Spróbuj pobrać tę samą osobę (powinno zwrócić 404, ponieważ TMDb nie znajdzie)

```bash
curl -s -X GET "http://localhost:8000/api/v1/people/$SLUG" \
  -H "Accept: application/json" | jq .
```

**Oczekiwany wynik:**
- Status: `404 Not Found`
- Response zawiera: `{"error": "Person not found"}`
- Logi powinny zawierać: `TmdbVerificationService: searching TMDb for person` i `TmdbVerificationService: person not found in TMDb`

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
- [ ] Test 10: Generowanie z domyślnym ContextTag - system używa domyślnego
- [ ] Test 11: Generowanie z konkretnym ContextTag (humorous) - poprawnie zapisany w bazie
- [ ] Test 12: Nieprawidłowy ContextTag - fallback lub błąd walidacji
- [ ] Test 13: Różne ContextTag w concurrent requests - różne job_id i opisy (KLUCZOWY)
- [ ] Test 14: Brak ContextTag w bazie - zachowanie przy pobieraniu filmu
- [ ] Test 14: Debug Configuration - weryfikacja konfiguracji serwisu (AI_SERVICE, OpenAI, etc.) - wymaga feature flag `debug_endpoints`
- [ ] Test 15: Weryfikacja TMDb z Feature Flagiem (Movie) - wyłączenie flagi pozwala na generowanie bez TMDb, włączenie wymaga weryfikacji TMDb
- [ ] Test 16: Weryfikacja TMDb z Feature Flagiem (Person) - wyłączenie flagi pozwala na generowanie bez TMDb, włączenie wymaga weryfikacji TMDb

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

---

## 🧪 Test 9: Sprawdzenie Obsługi Niejednoznacznych Slugów podczas Generowania

### Cel

Sprawdzenie jak system obsługuje niejednoznaczne slugi (slug bez roku pasujący do kilku filmów) podczas generowania opisów przez AI.

### Wymagania

- Aplikacja uruchomiona lokalnie
- Redis i Horizon działające
- Feature flag `ai_description_generation` aktywny
- Baza danych z filmami o tym samym tytule (różne lata)

### Krok 1: Przygotowanie danych testowych

Utwórz 2 filmy z tym samym tytułem (różne lata):

```bash
# Przez Tinker
cd api && php artisan tinker
```

```php
$movie1 = \App\Models\Movie::create([
    'title' => 'Bad Boys',
    'slug' => 'bad-boys-1995',
    'release_year' => 1995,
    'director' => 'Michael Bay',
    'genres' => ['Action', 'Comedy']
]);

$movie2 = \App\Models\Movie::create([
    'title' => 'Bad Boys',
    'slug' => 'bad-boys-2020',
    'release_year' => 2020,
    'director' => 'Adil El Arbi',
    'genres' => ['Action', 'Comedy']
]);
```

### Krok 2: Test GET endpoint z niejednoznacznym slugiem

```bash
curl -X GET "http://127.0.0.1:8000/api/v1/movies/bad-boys" \
  -H "Accept: application/json"
```

**Oczekiwany wynik:**
- Status: `200 OK`
- Zwraca najnowszy film (2020)
- Zawiera `_meta.ambiguous = true`
- Zawiera `_meta.alternatives` z listą obu filmów

**Przykładowa odpowiedź:**
```json
{
  "id": 2,
  "title": "Bad Boys",
  "slug": "bad-boys-2020",
  "release_year": 2020,
  "_meta": {
    "ambiguous": true,
    "message": "Multiple movies found with this title. Showing most recent. Use slug with year (e.g., \"bad-boys-1995\") for specific version.",
    "alternatives": [
      {
        "slug": "bad-boys-2020",
        "title": "Bad Boys",
        "release_year": 2020,
        "url": "http://127.0.0.1:8000/api/v1/movies/bad-boys-2020"
      },
      {
        "slug": "bad-boys-1995",
        "title": "Bad Boys",
        "release_year": 1995,
        "url": "http://127.0.0.1:8000/api/v1/movies/bad-boys-1995"
      }
    ]
  }
}
```

### Krok 3: Test generowania z niejednoznacznym slugiem

```bash
curl -X POST "http://127.0.0.1:8000/api/v1/generate" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "bad-boys",
    "locale": "en-US"
  }'
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Zwraca `job_id` i `status: PENDING`
- Job powinien znaleźć istniejący film (najnowszy - 2020) i użyć go zamiast tworzyć nowy

**Sprawdzenie statusu joba:**
```bash
# Zastąp {job_id} rzeczywistym ID z odpowiedzi
curl -X GET "http://127.0.0.1:8000/api/v1/jobs/{job_id}" \
  -H "Accept: application/json"
```

**Oczekiwany wynik po zakończeniu:**
- Status: `DONE`
- `id` wskazuje na istniejący film (bad-boys-2020)
- `slug` to slug istniejącego filmu

### Krok 4: Test generowania z jednoznacznym slugiem (z rokiem)

```bash
curl -X POST "http://127.0.0.1:8000/api/v1/generate" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "bad-boys-1995",
    "locale": "en-US"
  }'
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Job powinien znaleźć istniejący film (bad-boys-1995) i użyć go

### Krok 5: Test generowania nowego filmu

```bash
curl -X POST "http://127.0.0.1:8000/api/v1/generate" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "new-movie-2024",
    "locale": "en-US"
  }'
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Job powinien utworzyć nowy film
- Slug powinien być wygenerowany z danych AI używając `Movie::generateSlug()`
- Jeśli AI zwróci tytuł "New Movie", rok 2024, reżyser "John Doe", slug powinien być "new-movie-2024" lub "new-movie-2024-john-doe" (jeśli potrzebne)

### Weryfikacja w bazie danych

```bash
cd api && php artisan tinker
```

```php
// Sprawdź czy nie powstały duplikaty
$movies = \App\Models\Movie::where('title', 'Bad Boys')->get();
foreach($movies as $m) {
    echo $m->slug . ' (' . $m->release_year . ')' . PHP_EOL;
}

// Sprawdź czy nowy film ma poprawny slug
$newMovie = \App\Models\Movie::where('slug', 'LIKE', 'new-movie%')->first();
if ($newMovie) {
    echo "New movie slug: " . $newMovie->slug . PHP_EOL;
    echo "Title: " . $newMovie->title . PHP_EOL;
    echo "Year: " . $newMovie->release_year . PHP_EOL;
}
```

### Checklist końcowy

- [ ] GET endpoint z niejednoznacznym slugiem zwraca najnowszy film z `_meta`
- [ ] Generowanie z niejednoznacznym slugiem używa istniejącego filmu (najnowszego)
- [ ] Generowanie z jednoznacznym slugiem używa istniejącego filmu
- [ ] Generowanie nowego filmu tworzy film z poprawnym slugiem (wygenerowanym z danych AI)
- [ ] Nie powstają duplikaty filmów
- [ ] Slug jest unikalny (unique constraint działa)

### Troubleshooting

**Problem:** Job tworzy nowy film zamiast użyć istniejącego
- **Rozwiązanie:** Sprawdź czy `findExistingMovie()` w jobach używa logiki podobnej do `MovieRepository::findBySlugWithRelations()`

**Problem:** Slug koliduje (unique constraint violation)
- **Rozwiązanie:** Sprawdź czy `createMovieRecord()` używa `Movie::generateSlug()` zamiast slug z requestu

**Problem:** Nie zwraca `_meta` dla niejednoznacznych slugów
- **Rozwiązanie:** Sprawdź czy `MovieDisambiguationService::determineMeta()` jest wywoływany w `MovieController::show()`

---

## 🧪 Test 10: Sprawdzenie Obsługi Niejednoznacznych Slugów dla Osób podczas Generowania

### Cel

Sprawdzenie jak system obsługuje niejednoznaczne slugi (slug bez roku urodzenia pasujący do kilku osób) podczas generowania biografii przez AI.

### Wymagania

- Aplikacja uruchomiona lokalnie
- Redis i Horizon działające
- Feature flag `ai_bio_generation` aktywny
- Baza danych z osobami o tym samym imieniu i nazwisku (różne daty urodzenia)

### Krok 1: Przygotowanie danych testowych

Utwórz 2 osoby z tym samym imieniem i nazwiskiem (różne daty urodzenia):

```bash
# Przez Tinker
cd api && php artisan tinker
```

```php
$person1 = \App\Models\Person::create([
    'name' => 'John Smith',
    'slug' => 'john-smith-1960',
    'birth_date' => '1960-01-01',
    'birthplace' => 'New York'
]);

$person2 = \App\Models\Person::create([
    'name' => 'John Smith',
    'slug' => 'john-smith-1980',
    'birth_date' => '1980-01-01',
    'birthplace' => 'Los Angeles'
]);
```

### Krok 2: Test GET endpoint z niejednoznacznym slugiem

```bash
curl -X GET "http://127.0.0.1:8000/api/v1/people/john-smith" \
  -H "Accept: application/json"
```

**Oczekiwany wynik:**
- Status: `200 OK`
- Zwraca najnowszą osobę (najnowsza data urodzenia - 1980)
- Zawiera `_meta.ambiguous = true`
- Zawiera `_meta.alternatives` z listą obu osób

**Przykładowa odpowiedź:**
```json
{
  "id": 2,
  "name": "John Smith",
  "slug": "john-smith-1980",
  "birth_date": "1980-01-01",
  "birthplace": "Los Angeles",
  "_meta": {
    "ambiguous": true,
    "message": "Multiple people found with this name. Showing most recent by birth date. Use slug with birth year (e.g., \"john-smith-1960\") for specific version.",
    "alternatives": [
      {
        "slug": "john-smith-1980",
        "name": "John Smith",
        "birth_date": "1980-01-01",
        "url": "http://127.0.0.1:8000/api/v1/people/john-smith-1980"
      },
      {
        "slug": "john-smith-1960",
        "name": "John Smith",
        "birth_date": "1960-01-01",
        "url": "http://127.0.0.1:8000/api/v1/people/john-smith-1960"
      }
    ]
  }
}
```

### Krok 3: Test generowania z niejednoznacznym slugiem

```bash
curl -X POST "http://127.0.0.1:8000/api/v1/generate" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "PERSON",
    "entity_id": "john-smith",
    "locale": "en-US"
  }'
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Zwraca `job_id` i `status: PENDING`
- Job powinien znaleźć istniejącą osobę (najnowszą - 1980) i użyć jej zamiast tworzyć nową

**Sprawdzenie statusu joba:**
```bash
# Zastąp {job_id} rzeczywistym ID z odpowiedzi
curl -X GET "http://127.0.0.1:8000/api/v1/jobs/{job_id}" \
  -H "Accept: application/json"
```

**Oczekiwany wynik po zakończeniu:**
- Status: `DONE`
- `id` wskazuje na istniejącą osobę (john-smith-1980)
- `slug` to slug istniejącej osoby

### Krok 4: Test generowania z jednoznacznym slugiem (z rokiem urodzenia)

```bash
curl -X POST "http://127.0.0.1:8000/api/v1/generate" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "PERSON",
    "entity_id": "john-smith-1960",
    "locale": "en-US"
  }'
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Job powinien znaleźć istniejącą osobę (john-smith-1960) i użyć jej

### Krok 5: Test generowania nowej osoby

```bash
curl -X POST "http://127.0.0.1:8000/api/v1/generate" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "PERSON",
    "entity_id": "new-person-2024",
    "locale": "en-US"
  }'
```

**Oczekiwany wynik:**
- Status: `202 Accepted`
- Job powinien utworzyć nową osobę
- Slug powinien być wygenerowany z danych AI używając `Person::generateSlug()`
- Jeśli AI zwróci imię "New Person", datę urodzenia "1990-01-01", miejsce urodzenia "Boston", slug powinien być "new-person-1990" lub "new-person-1990-boston" (jeśli potrzebne)

### Weryfikacja w bazie danych

```bash
cd api && php artisan tinker
```

```php
// Sprawdź czy nie powstały duplikaty
$people = \App\Models\Person::where('name', 'John Smith')->get();
foreach($people as $p) {
    echo $p->slug . ' (' . $p->birth_date . ')' . PHP_EOL;
}

// Sprawdź czy nowa osoba ma poprawny slug
$newPerson = \App\Models\Person::where('slug', 'LIKE', 'new-person%')->first();
if ($newPerson) {
    echo "New person slug: " . $newPerson->slug . PHP_EOL;
    echo "Name: " . $newPerson->name . PHP_EOL;
    echo "Birth date: " . $newPerson->birth_date . PHP_EOL;
}
```

### Checklist końcowy

- [ ] GET endpoint z niejednoznacznym slugiem zwraca najnowszą osobę z `_meta`
- [ ] Generowanie z niejednoznacznym slugiem używa istniejącej osoby (najnowszej)
- [ ] Generowanie z jednoznacznym slugiem używa istniejącej osoby
- [ ] Generowanie nowej osoby tworzy osobę z poprawnym slugiem (wygenerowanym z danych AI)
- [ ] Nie powstają duplikaty osób
- [ ] Slug jest unikalny (unique constraint działa)

### Troubleshooting

**Problem:** Job tworzy nową osobę zamiast użyć istniejącej
- **Rozwiązanie:** Sprawdź czy `findExistingPerson()` w jobach używa logiki podobnej do `PersonRepository::findBySlugWithRelations()`

**Problem:** Slug koliduje (unique constraint violation)
- **Rozwiązanie:** Sprawdź czy `createPersonRecord()` używa `Person::generateSlug()` zamiast slug z requestu

**Problem:** Nie zwraca `_meta` dla niejednoznacznych slugów
- **Rozwiązanie:** Sprawdź czy `PersonDisambiguationService::determineMeta()` jest wywoływany w `PersonController::show()` (jeśli istnieje)

---

**Ostatnia aktualizacja:** 2025-12-06


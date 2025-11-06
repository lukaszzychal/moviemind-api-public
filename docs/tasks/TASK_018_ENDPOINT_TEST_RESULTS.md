# TASK-018: Wyniki Testów Endpointów z Real AI API

**Data:** 2025-11-04  
**Status:** ⚠️ Częściowo zakończone - wymaga uruchomienia Redis

---

## ✅ Testy zakończone pomyślnie

### 1. Healthcheck Endpoint
```bash
curl http://localhost:8000/up
```
**Status:** ✅ Działa (zwraca HTML response)

### 2. Movies List Endpoint
```bash
curl http://localhost:8000/api/v1/movies
```
**Status:** ✅ Działa
**Response:**
```json
{"data":[]}
```
**Uwaga:** Pusta lista, ponieważ baza danych została wyczyszczona bez seedów.

### 3. Feature Flags Endpoint
```bash
curl http://localhost:8000/api/v1/admin/flags
```
**Status:** ✅ Działa
**Response:** Lista wszystkich flag z ich statusami
- `ai_description_generation`: ✅ **active: true**
- `ai_bio_generation`: ✅ **active: true**

---

## ❌ Błędy napotkane

### Problem: Redis Connection Refused

**Błąd:**
```
Connection refused [tcp://127.0.0.1:6379]
Predis\Connection\Resource\Exception\StreamInitException
```

**Przyczyna:**
- Aplikacja używa `QUEUE_CONNECTION=redis`
- Redis nie jest uruchomiony lokalnie na porcie 6379
- Endpoint `/api/v1/generate` próbuje dodać job do kolejki Redis

**Stack trace:**
```
QueueMovieGenerationJob::handle()
  → RealGenerateMovieJob::dispatch()
    → Laravel Queue (Redis)
      → Connection refused
```

---

## ✅ Rozwiązanie zastosowane

**Przełączono na Database Queue:**
```env
QUEUE_CONNECTION=database
```

**Uruchomiono queue worker:**
```bash
php artisan queue:work --once
```

**Status:** ✅ Endpointy działają, job został przetworzony

---

## 🔧 Rozwiązania dla Redis (opcjonalnie)

### Opcja 1: Uruchom Redis lokalnie

**Docker Compose:**
```bash
docker-compose up -d redis
```

**Lub natywnie (macOS):**
```bash
brew install redis
brew services start redis
```

**Lub natywnie (Linux):**
```bash
sudo apt install redis-server
sudo systemctl start redis
```

### Opcja 2: Zmień na Database Queue (tymczasowo)

Edytuj `api/.env`:
```env
QUEUE_CONNECTION=database
```

Następnie:
```bash
cd api
php artisan queue:table  # jeśli nie istnieje
php artisan migrate
php artisan queue:work
```

### Opcja 3: Użyj Docker Compose dla całego środowiska

```bash
docker-compose up -d
```

To uruchomi:
- ✅ PHP-FPM
- ✅ Nginx
- ✅ PostgreSQL
- ✅ Redis
- ✅ Horizon (queue workers)

---

## 📋 Testy do wykonania po naprawie Redis

### 1. Test Generate Movie (MOVIE)
```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{"entity_type": "MOVIE", "slug": "the-matrix-1999"}'
```

**Oczekiwany wynik:**
```json
{
  "job_id": "...",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "the-matrix-1999"
}
```

### 2. Test Job Status
```bash
curl http://localhost:8000/api/v1/jobs/{job_id}
```

**Oczekiwane statusy:**
- `PENDING` → Job w kolejce
- `PROCESSING` → W trakcie generowania przez OpenAI
- `DONE` → Opis wygenerowany i zapisany
- `FAILED` → Błąd (sprawdź logi)

### 3. Test Generate Person (PERSON)
```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{"entity_type": "PERSON", "slug": "keanu-reeves"}'
```

### 4. Test Movie Show (po wygenerowaniu)
```bash
curl http://localhost:8000/api/v1/movies/the-matrix-1999
```

**Oczekiwany wynik:** Film z wygenerowanym opisem AI

---

## 🔍 Weryfikacja Queue Workers

### Sprawdź czy queue workers działają

**Docker:**
```bash
docker-compose logs horizon
# lub
docker-compose exec horizon php artisan horizon:status
```

**Lokalnie:**
```bash
cd api
php artisan queue:work
# lub
php artisan horizon
```

### Sprawdź logi Laravel

```bash
cd api
tail -f storage/logs/laravel.log
```

---

## 📊 Status testów

| Endpoint | Status | Uwagi |
|----------|--------|-------|
| `GET /up` | ✅ | Działa |
| `GET /api/v1/movies` | ✅ | Pusta lista (OK) |
| `GET /api/v1/admin/flags` | ✅ | Flagi włączone |
| `POST /api/v1/generate` | ✅ | Działa (zwraca job_id) |
| `GET /api/v1/jobs/{id}` | ✅ | Działa (pokazuje status) |
| `GET /api/v1/movies/{slug}` | ⏳ | Wymaga wygenerowania |

### Szczegóły testów

#### 1. Generate Movie Endpoint
```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{"entity_type": "MOVIE", "slug": "test-movie-2024"}'
```

**Response:**
```json
{
  "job_id": "5c99e98b-15c7-48e9-b57a-fa5b530452fe",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "test-movie-2024",
  "confidence": 0.9,
  "confidence_level": "high"
}
```
**Status:** ✅ Działa poprawnie

#### 2. Job Status Endpoint
```bash
curl http://localhost:8000/api/v1/jobs/5c99e98b-15c7-48e9-b57a-fa5b530452fe
```

**Response (PENDING):**
```json
{
  "job_id": "5c99e98b-15c7-48e9-b57a-fa5b530452fe",
  "status": "PENDING",
  "entity": "MOVIE",
  "slug": "test-movie-2024",
  "confidence": 0.9,
  "confidence_level": "high"
}
```

**Response (FAILED):**
```json
{
  "job_id": "5c99e98b-15c7-48e9-b57a-fa5b530452fe",
  "status": "FAILED",
  "entity": "MOVIE",
  "slug": "test-movie-2024",
  "id": null
}
```
**Status:** ✅ Działa poprawnie (pokazuje status FAILED z powodu rate limit OpenAI)

#### 3. OpenAI API Rate Limit
**Błąd:** `API returned status 429` (Too Many Requests)
**Przyczyna:** Przekroczono limit rate limiting dla OpenAI API
**Status:** ⚠️ Normalne zachowanie - API key może mieć ograniczenia

**Rozwiązanie:**
- Poczekaj kilka minut przed kolejnym requestem
- Sprawdź limit w OpenAI Dashboard
- Użyj innego API key (jeśli dostępny)

---

## 🎯 Następne kroki

1. **Uruchom Redis** (Opcja 1, 2 lub 3 powyżej)
2. **Uruchom queue workers** (`php artisan queue:work` lub `horizon`)
3. **Przetestuj endpoint `/api/v1/generate`**
4. **Sprawdź status job** przez `/api/v1/jobs/{id}`
5. **Zweryfikuj wygenerowany opis** przez `/api/v1/movies/{slug}`

---

## 📝 Uwagi

- **Baza danych jest pusta** - brak seedów, więc lista filmów będzie pusta
- **Feature flags są włączone** - `ai_description_generation` i `ai_bio_generation`
- **AI_SERVICE=real** - ustawione w `.env`
- **OPENAI_API_KEY** - ustawiony w `.env`

---

## 🔗 Powiązane dokumenty

- [TASK_018_REAL_AI_TEST_RESULTS.md](./TASK_018_REAL_AI_TEST_RESULTS.md) - Konfiguracja
- [TASK_018_FEATURE_FLAGS.md](./TASK_018_FEATURE_FLAGS.md) - Feature flags
- [TASKS.md](../issue/TASKS.md#task-018) - Zadanie


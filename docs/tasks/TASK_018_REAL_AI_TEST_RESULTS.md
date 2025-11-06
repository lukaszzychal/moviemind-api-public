# TASK-018: Test Lokalny z Real AI API - Wyniki

**Data:** 2025-11-04  
**Status:** ✅ Konfiguracja zakończona, gotowe do testów

---

## ✅ Wykonane kroki

### 1. Wyczyszczenie bazy danych (bez seedów)
```bash
php artisan migrate:fresh --no-interaction
```
- ✅ Wszystkie tabele zostały usunięte i ponownie utworzone
- ✅ Brak seedów (czysta baza danych)

### 2. Konfiguracja `.env`
Dodano następujące zmienne do `api/.env`:

```env
# OpenAI Configuration
OPENAI_API_KEY=sk-proj-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
OPENAI_MODEL=gpt-4o-mini

# AI Service Configuration
AI_SERVICE=real
```

### 3. Weryfikacja konfiguracji
```bash
php artisan config:show services.ai
# Output: service = real ✅

php artisan config:show services.openai
# Output: api_key, model = gpt-4o-mini ✅
```

### 4. Cache cleared
```bash
php artisan config:clear
php artisan cache:clear
```

---

## 🧪 Jak przetestować endpointy

### Opcja 1: Uruchomienie serwera lokalnego

```bash
# Terminal 1: Uruchom serwer
cd api
php artisan serve

# Terminal 2: Test endpointów
```

### Opcja 2: Użycie Docker Compose

```bash
# Uruchom wszystkie serwisy
docker-compose up -d

# Sprawdź status
docker-compose ps

# Test endpointów (gdy serwisy są gotowe)
```

---

## 📋 Endpointy do przetestowania

### 1. Healthcheck
```bash
curl http://localhost:8000/up
# Oczekiwany wynik: {"status":"ok"} lub podobny
```

### 2. Lista filmów
```bash
curl http://localhost:8000/api/v1/movies
# Oczekiwany wynik: {"data": []} (pusta lista, bo brak seedów)
```

### 3. Generowanie opisu filmu (MOVIE)
```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999"
  }'

# Oczekiwany wynik:
# {
#   "job_id": "...",
#   "status": "PENDING",
#   "message": "Generation queued for movie by slug",
#   "slug": "the-matrix-1999"
# }
```

### 4. Sprawdzenie statusu job
```bash
curl http://localhost:8000/api/v1/jobs/{job_id}

# Oczekiwane statusy:
# - PENDING (w kolejce)
# - PROCESSING (w trakcie generowania)
# - DONE (zakończone, opis wygenerowany)
# - FAILED (błąd)
```

### 5. Pobranie filmu (po wygenerowaniu)
```bash
curl http://localhost:8000/api/v1/movies/the-matrix-1999

# Oczekiwany wynik: Film z wygenerowanym opisem AI
```

### 6. Generowanie biografii osoby (PERSON)
```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "PERSON",
    "slug": "keanu-reeves"
  }'

# Analogicznie jak dla MOVIE
```

---

## 🔍 Weryfikacja działania Real AI

### Sprawdzenie logów
```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Horizon dashboard (jeśli dostępne)
open http://localhost:8000/horizon
```

### Sprawdzenie queue workers
```bash
# Sprawdź czy jobs są w kolejce
php artisan queue:work

# Lub użyj Horizon (jeśli skonfigurowane)
php artisan horizon
```

### Weryfikacja w bazie danych
```sql
-- Sprawdź wygenerowane opisy
SELECT * FROM movie_descriptions ORDER BY created_at DESC LIMIT 5;

-- Sprawdź statusy jobów
SELECT * FROM ai_jobs ORDER BY created_at DESC LIMIT 5;
```

---

## ⚠️ Uwagi

1. **OpenAI API Key**: Klucz został ustawiony w `.env`, ale **nie został zacommitowany** (`.env` jest w `.gitignore`).

2. **Brak seedów**: Baza danych jest pusta, więc:
   - Lista filmów będzie pusta
   - Trzeba wygenerować filmy/osoby przez endpoint `/generate` lub dodać seedy

3. **Queue Workers**: Upewnij się, że queue workers są uruchomione:
   ```bash
   php artisan queue:work
   # lub
   php artisan horizon
   ```

4. **Feature Flags**: Upewnij się, że feature flagi są włączone:
   ```bash
   php artisan pennant:feature ai_description_generation --on
   php artisan pennant:feature ai_bio_generation --on
   ```

---

## 📊 Następne kroki

1. **Uruchom serwer** (jeśli nie działa)
2. **Przetestuj endpointy** zgodnie z sekcją powyżej
3. **Zweryfikuj jakość** wygenerowanych opisów
4. **Sprawdź koszty** w OpenAI Dashboard
5. **Dokumentuj wyniki** testów

---

## 🔗 Powiązane dokumenty

- [TASK-018 w TASKS.md](../../issue/TASKS.md#task-018)
- [OpenAPI Specification](../../openapi.yaml)
- [Postman Collection](../../postman/moviemind-api.postman_collection.json)


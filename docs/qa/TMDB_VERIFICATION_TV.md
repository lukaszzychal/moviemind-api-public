# Testowanie weryfikacji TMDb dla TV Series i TV Shows

> **Created:** 2025-01-27  
> **Context:** Dokumentacja QA dla testowania weryfikacji TMDb przed generowaniem AI dla TV Series i TV Shows  
> **Category:** reference  
> **Target Audience:** QA Engineers, Testers  
> **Related Task:** TASK-046

## 📋 Spis treści

1. [Cel testowania](#cel-testowania)
2. [Testy automatyczne](#testy-automatyczne)
3. [Testy manualne](#testy-manualne)
4. [Scenariusze testowe](#scenariusze-testowe)
5. [Weryfikacja w bazie danych](#weryfikacja-w-bazie-danych)
6. [Troubleshooting](#troubleshooting)
7. [Checklist](#checklist)

---

## 🎯 Cel testowania

Upewnić się, że system poprawnie:
1. **Weryfikuje istnienie** TV Series i TV Shows w TMDb przed generowaniem AI
2. **Tworzy encje** z danych TMDb gdy nie istnieją lokalnie
3. **Zwraca odpowiednie kody statusu** (200, 202, 404) w zależności od sytuacji
4. **Obsługuje disambiguation** gdy istnieje wiele wyników w TMDb
5. **Cache'uje wyniki** weryfikacji TMDb (TTL: 24h)
6. **Zwraca `confidence` i `confidence_level`** w odpowiedziach

---

## 🧪 Testy automatyczne

### Uruchomienie testów

```bash
cd api

# Testy jednostkowe dla serwisów retrieval
php artisan test --filter=TvSeriesRetrievalServiceTest
php artisan test --filter=TvShowRetrievalServiceTest

# Testy feature dla weryfikacji TMDb
php artisan test --filter=MissingEntityGenerationTest::test_tv

# Wszystkie testy związane z TV Series i TV Shows
php artisan test --filter="TvSeries|TvShow"
```

### Co testują?

#### Testy jednostkowe (`TvSeriesRetrievalServiceTest`, `TvShowRetrievalServiceTest`)

1. **Cached result** - sprawdza że wyniki są cache'owane
2. **Existing entity** - sprawdza że istniejące encje są zwracane
3. **Selected description** - sprawdza że można wybrać konkretny opis
4. **Invalid description ID** - sprawdza obsługę błędnych ID opisów
5. **Feature flag disabled** - sprawdza że gdy flaga wyłączona, zwraca 404
6. **TMDb verification** - sprawdza że wywołuje weryfikację TMDb gdy encja nie istnieje

#### Testy feature (`MissingEntityGenerationTest`)

1. **TV Series found in TMDb** - sprawdza że zwraca 202 gdy znaleziono w TMDb
2. **TV Series not found in TMDb** - sprawdza że zwraca 404 gdy nie znaleziono
3. **TV Series feature flag off** - sprawdza że zwraca 404 gdy flaga wyłączona
4. **TV Show found in TMDb** - sprawdza że zwraca 202 gdy znaleziono w TMDb
5. **TV Show not found in TMDb** - sprawdza że zwraca 404 gdy nie znaleziono
6. **TV Show feature flag off** - sprawdza że zwraca 404 gdy flaga wyłączona

---

## 🔍 Testy manualne

### Prerequisites

1. **Feature flags** muszą być włączone:
   ```bash
   # Sprawdź status flag
   curl http://localhost:8000/api/v1/admin/flags | jq
   
   # Włącz jeśli potrzeba (wymaga admin endpoint)
   # ai_description_generation: true
   # tmdb_verification: true
   ```

2. **TMDb API Key** musi być skonfigurowany:
   ```bash
   # Sprawdź w .env
   grep TMDB_API_KEY api/.env
   ```

3. **Cache** powinien być wyczyszczony przed testami:
   ```bash
   cd api
   php artisan cache:clear
   ```

### Scenariusz 1: TV Series istnieje lokalnie

**Cel:** Sprawdzić że istniejące TV Series jest zwracane bez weryfikacji TMDb.

**Steps:**

1. **Utwórz TV Series lokalnie** (przez bazę danych lub API):
   ```sql
   INSERT INTO tv_series (id, title, slug, first_air_date, created_at, updated_at)
   VALUES (gen_random_uuid(), 'Breaking Bad', 'breaking-bad-2008', '2008-01-20', NOW(), NOW());
   ```

2. **Wyślij request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008" \
     -H "Accept: application/json" | jq
   ```

3. **Weryfikuj odpowiedź:**
   - [ ] Status code: `200 OK`
   - [ ] Response zawiera: `id`, `title`, `slug`, `first_air_date`
   - [ ] **NIE** wywołuje weryfikacji TMDb (sprawdź logi)
   - [ ] Response jest cache'owany

---

### Scenariusz 2: TV Series nie istnieje lokalnie, ale istnieje w TMDb

**Cel:** Sprawdzić że system weryfikuje TMDb i tworzy encję.

**Steps:**

1. **Upewnij się że TV Series nie istnieje lokalnie:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008" \
     -H "Accept: application/json"
   # Powinno zwrócić 404 lub 202 (jeśli już istnieje)
   ```

2. **Wyślij request dla TV Series który istnieje w TMDb:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008" \
     -H "Accept: application/json" | jq
   ```

3. **Weryfikuj odpowiedź:**
   - [ ] Status code: `202 Accepted`
   - [ ] Response zawiera:
     - `job_id` (UUID)
     - `status: "PENDING"`
     - `slug: "breaking-bad-2008"`
     - `confidence` (float, 0.0-1.0)
     - `confidence_level` (string: "high", "medium", "low", "very_low", "unknown")
     - `locale: "en-US"`
   - [ ] Weryfikacja TMDb została wywołana (sprawdź logi)
   - [ ] TV Series został utworzony w bazie danych (sprawdź SQL)
   - [ ] Job został zakolejkowany (sprawdź `GET /api/v1/jobs/{job_id}`)

4. **Sprawdź status job:**
   ```bash
   # Użyj job_id z poprzedniej odpowiedzi
   curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
     -H "Accept: application/json" | jq
   ```

5. **Po zakończeniu job, sprawdź ponownie:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Status code: `200 OK`
   - [ ] Response zawiera wygenerowany opis AI

---

### Scenariusz 3: TV Series nie istnieje w TMDb

**Cel:** Sprawdzić że system zwraca 404 gdy nie znaleziono w TMDb.

**Steps:**

1. **Wyślij request dla nieistniejącego TV Series:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/non-existent-series-xyz-9999" \
     -H "Accept: application/json" | jq
   ```

2. **Weryfikuj odpowiedź:**
   - [ ] Status code: `404 Not Found`
   - [ ] Response zawiera: `{"error": "TV series not found"}`
   - [ ] Weryfikacja TMDb została wywołana (sprawdź logi)
   - [ ] Wynik "NOT_FOUND" został cache'owany (TTL: 24h)
   - [ ] TV Series **NIE** został utworzony w bazie danych

---

### Scenariusz 4: TV Series - Disambiguation (wiele wyników w TMDb)

**Cel:** Sprawdzić że system obsługuje disambiguation gdy istnieje wiele wyników.

**Steps:**

1. **Wyślij request dla TV Series z wieloma wynikami w TMDb:**
   ```bash
   # Przykład: "The Office" (istnieje wersja US i UK)
   curl -X GET "http://localhost:8000/api/v1/tv-series/the-office" \
     -H "Accept: application/json" | jq
   ```

2. **Weryfikuj odpowiedź:**
   - [ ] Status code: `300 Multiple Choices` (lub odpowiedni kod)
   - [ ] Response zawiera `disambiguation` z listą opcji
   - [ ] Każda opcja zawiera: `slug`, `title`, `first_air_date`, `overview`
   - [ ] Można wybrać konkretną opcję przez parametr `?slug=the-office-2005`

3. **Wybierz konkretną opcję:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/the-office?slug=the-office-2005" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Status code: `202 Accepted` lub `200 OK`
   - [ ] Wybrana opcja została użyta do generacji

---

### Scenariusz 5: TV Show - analogiczne scenariusze

**Cel:** Sprawdzić że TV Shows działają analogicznie do TV Series.

**Steps:**

1. **TV Show istnieje lokalnie:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-shows/the-tonight-show-1954" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Status code: `200 OK`

2. **TV Show nie istnieje lokalnie, ale istnieje w TMDb:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-shows/the-tonight-show-1954" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Status code: `202 Accepted`
   - [ ] Response zawiera `confidence` i `confidence_level`

3. **TV Show nie istnieje w TMDb:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-shows/non-existent-show-xyz-9999" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Status code: `404 Not Found`
   - [ ] Response: `{"error": "TV show not found"}`

---

### Scenariusz 6: Cache weryfikacji TMDb

**Cel:** Sprawdzić że wyniki weryfikacji TMDb są cache'owane.

**Steps:**

1. **Wyślij pierwszy request (cache miss):**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Sprawdź logi - powinna być weryfikacja TMDb (cache miss)

2. **Wyślij drugi request (cache hit):**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Sprawdź logi - **NIE** powinna być weryfikacja TMDb (cache hit)
   - [ ] Response jest identyczna

3. **Sprawdź cache TTL:**
   ```bash
   # W Redis (jeśli używany)
   redis-cli TTL "tmdb:tv_series:breaking-bad-2008"
   # Powinno zwrócić ~86400 (24h w sekundach)
   ```

---

### Scenariusz 7: Feature flags

**Cel:** Sprawdzić że feature flags kontrolują dostępność funkcji.

**Steps:**

1. **Wyłącz feature flag:**
   ```bash
   # Przez admin endpoint (jeśli dostępny)
   curl -X POST "http://localhost:8000/api/v1/admin/flags/ai_description_generation" \
     -H "Content-Type: application/json" \
     -d '{"enabled": false}'
   ```

2. **Wyślij request:**
   ```bash
   curl -X GET "http://localhost:8000/api/v1/tv-series/breaking-bad-2008" \
     -H "Accept: application/json" | jq
   ```
   - [ ] Status code: `404 Not Found`
   - [ ] Response: `{"error": "TV series not found"}`
   - [ ] **NIE** wywołuje weryfikacji TMDb

3. **Włącz feature flag ponownie:**
   ```bash
   curl -X POST "http://localhost:8000/api/v1/admin/flags/ai_description_generation" \
     -H "Content-Type: application/json" \
     -d '{"enabled": true}'
   ```

---

## 🗄️ Weryfikacja w bazie danych

### Sprawdź utworzone encje

```sql
-- TV Series
SELECT id, title, slug, first_air_date, created_at 
FROM tv_series 
WHERE slug = 'breaking-bad-2008';

-- TV Shows
SELECT id, title, slug, first_air_date, created_at 
FROM tv_shows 
WHERE slug = 'the-tonight-show-1954';
```

### Sprawdź TMDb snapshots

```sql
-- TV Series snapshots
SELECT entity_type, entity_id, tmdb_id, tmdb_type, created_at 
FROM tmdb_snapshots 
WHERE entity_type = 'TV_SERIES' 
ORDER BY created_at DESC 
LIMIT 5;

-- TV Show snapshots
SELECT entity_type, entity_id, tmdb_id, tmdb_type, created_at 
FROM tmdb_snapshots 
WHERE entity_type = 'TV_SHOW' 
ORDER BY created_at DESC 
LIMIT 5;
```

### Sprawdź job status

```sql
-- Jobs dla TV Series
SELECT id, entity_type, entity_id, status, created_at 
FROM jobs 
WHERE entity_type = 'TV_SERIES' 
ORDER BY created_at DESC 
LIMIT 5;

-- Jobs dla TV Shows
SELECT id, entity_type, entity_id, status, created_at 
FROM jobs 
WHERE entity_type = 'TV_SHOW' 
ORDER BY created_at DESC 
LIMIT 5;
```

---

## 🐛 Troubleshooting

### Problem: Status 500 zamiast 202/404

**Możliwe przyczyny:**
1. TMDb API key nie jest skonfigurowany
2. Rate limit TMDb został przekroczony
3. Błąd w serwisie tworzenia (`TmdbTvSeriesCreationService`, `TmdbTvShowCreationService`)

**Rozwiązanie:**
```bash
# Sprawdź logi
tail -f api/storage/logs/laravel.log

# Sprawdź konfigurację
grep TMDB api/.env

# Sprawdź rate limit
# W Redis (jeśli używany)
redis-cli GET "tmdb:rate_limit:window"
```

### Problem: Cache nie działa

**Możliwe przyczyny:**
1. Cache driver nie jest skonfigurowany (powinien być `redis` lub `array`)
2. Cache został wyczyszczony

**Rozwiązanie:**
```bash
# Sprawdź konfigurację cache
grep CACHE_DRIVER api/.env

# Wyczyść cache
cd api
php artisan cache:clear

# Sprawdź cache w Redis
redis-cli KEYS "tmdb:tv_series:*"
redis-cli KEYS "tmdb:tv_show:*"
```

### Problem: Confidence jest null lub "unknown"

**Możliwe przyczyny:**
1. Slug nie został poprawnie zwalidowany
2. `SlugValidator` zwrócił null dla confidence

**Rozwiązanie:**
```bash
# Sprawdź logi walidacji
grep "SlugValidator" api/storage/logs/laravel.log

# Sprawdź slug format
# Powinien być: "title-year" (np. "breaking-bad-2008")
```

### Problem: Disambiguation nie działa

**Możliwe przyczyny:**
1. TMDb search zwraca tylko jeden wynik
2. Wszystkie wyniki mają ten sam rok (nie ma disambiguation)

**Rozwiązanie:**
```bash
# Sprawdź logi weryfikacji TMDb
grep "searchTvSeries\|searchTvShows" api/storage/logs/laravel.log

# Sprawdź bezpośrednio w TMDb API (jeśli masz klucz)
curl "https://api.themoviedb.org/3/search/tv?api_key=YOUR_KEY&query=the+office"
```

---

## ✅ Checklist

### Testy automatyczne
- [x] `TvSeriesRetrievalServiceTest` - wszystkie testy przechodzą (6 testów)
- [x] `TvShowRetrievalServiceTest` - wszystkie testy przechodzą (6 testów)
- [x] `MissingEntityGenerationTest::test_tv_*` - wszystkie testy przechodzą (6 testów)
- [x] PHPStan - 0 błędów
- [x] Laravel Pint - wszystkie pliki sformatowane

### Testy manualne - TV Series
- [ ] TV Series istnieje lokalnie → 200 OK
- [ ] TV Series nie istnieje lokalnie, istnieje w TMDb → 202 Accepted + job_id
- [ ] TV Series nie istnieje w TMDb → 404 Not Found
- [ ] Disambiguation działa (wiele wyników)
- [ ] Cache działa (cache hit/miss)
- [ ] Feature flag wyłączony → 404 Not Found
- [ ] Confidence i confidence_level są zwracane

### Testy manualne - TV Shows
- [ ] TV Show istnieje lokalnie → 200 OK
- [ ] TV Show nie istnieje lokalnie, istnieje w TMDb → 202 Accepted + job_id
- [ ] TV Show nie istnieje w TMDb → 404 Not Found
- [ ] Disambiguation działa (wiele wyników)
- [ ] Cache działa (cache hit/miss)
- [ ] Feature flag wyłączony → 404 Not Found
- [ ] Confidence i confidence_level są zwracane

### Weryfikacja w bazie danych
- [ ] TV Series są tworzone w bazie danych
- [ ] TV Shows są tworzone w bazie danych
- [ ] TMDb snapshots są zapisywane
- [ ] Jobs są tworzone i aktualizowane

### Performance
- [ ] Cache TTL: 24h (86400 sekund)
- [ ] Rate limiting TMDb działa (40 requests / 10 seconds)
- [ ] Response time < 500ms dla cache hit
- [ ] Response time < 2s dla cache miss (z weryfikacją TMDb)

---

## 📚 Powiązane dokumenty

- [Main Testing Guide](./MANUAL_TESTING_GUIDE.md) - Ogólny przewodnik testowania
- [Movies Testing Guide](./MANUAL_TESTING_MOVIES.md) - Testowanie Movies API
- [People Testing Guide](./MANUAL_TESTING_PEOPLE.md) - Testowanie People API
- [TMDb ID Hidden Testing](./TESTING_TMDB_ID_HIDDEN.md) - Testowanie ukrycia tmdb_id
- [API Testing Guide](./API_TESTING_GUIDE.md) - Przewodnik testowania API

---

## 📝 Notatki

**Status:** ✅ **TASK-046 ukończony**  
**Data weryfikacji:** 2025-01-27  
**Wersja API:** v1  
**Testy automatyczne:** 18 testów (wszystkie przechodzą)  
**Testy manualne:** Do wykonania przez QA

---

## 🔗 Przydatne linki

- [TMDb API Documentation](https://developers.themoviedb.org/3/getting-started/introduction)
- [Laravel Cache Documentation](https://laravel.com/docs/12.x/cache)
- [OpenAPI Specification](./openapi.yaml)


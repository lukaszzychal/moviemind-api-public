# Raport Testów Manualnych - TASK-037 Faza 3

> **Data wykonania:** 2025-12-06  
> **Kontekst:** Testy manualne dla feature flag'a `tmdb_verification`  
> **Kategoria:** testing_report

## 🎯 Cel

Weryfikacja działania feature flag'a `tmdb_verification` w środowisku lokalnym poprzez testy manualne zgodnie z `MANUAL_TESTING_GUIDE.md`.

## ✅ Test 15: Weryfikacja TMDb z Feature Flagiem (Movie)

### Kroki wykonane:

#### 1. Przygotowanie slug testowego
```bash
SLUG="non-existent-movie-$(date +%s)"
# Wynik: non-existent-movie-1764984961
```

#### 2. Wyłączenie feature flag'a `tmdb_verification`
```bash
curl -X POST "http://localhost:8000/api/v1/admin/flags/tmdb_verification" \
  -H "Content-Type: application/json" \
  -d '{"state":"off"}'
```

**Wynik:** ✅ `{"name": "tmdb_verification", "active": false}`

#### 3. Próba pobrania filmu (wyłączony flag - powinno zwrócić 202)
```bash
curl -X GET "http://localhost:8000/api/v1/movies/non-existent-movie-1764984961"
```

**Wynik:** ✅ `202 Accepted`
```json
{
  "job_id": "2d1f3b53-f369-4f92-bf76-15cc73b043fe",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "non-existent-movie-1764984961",
  "confidence": null,
  "confidence_level": "unknown",
  "locale": "en-US"
}
```

**Weryfikacja:** System przeszedł do generowania AI bez weryfikacji TMDb (fallback).

#### 4. Włączenie feature flag'a `tmdb_verification`
```bash
curl -X POST "http://localhost:8000/api/v1/admin/flags/tmdb_verification" \
  -H "Content-Type: application/json" \
  -d '{"state":"on"}'
```

**Wynik:** ✅ `{"name": "tmdb_verification", "active": true}`

#### 5. Próba pobrania filmu (włączony flag - powinno zwrócić 404)
```bash
curl -X GET "http://localhost:8000/api/v1/movies/non-existent-movie-1764984970"
```

**Wynik:** ✅ `404 Not Found`
```json
{
  "error": "Movie not found"
}
```

**Weryfikacja:** System zwrócił 404, ponieważ TMDb nie znalazło filmu (weryfikacja TMDb działa).

### ✅ Test 15 - Status: **SUKCES**

---

## ✅ Test 16: Weryfikacja TMDb z Feature Flagiem (Person)

### Kroki wykonane:

#### 1. Przygotowanie slug testowego
```bash
SLUG="non-existent-person-$(date +%s)"
# Wynik: non-existent-person-1764984984
```

#### 2. Wyłączenie feature flag'a `tmdb_verification`
```bash
curl -X POST "http://localhost:8000/api/v1/admin/flags/tmdb_verification" \
  -H "Content-Type: application/json" \
  -d '{"state":"off"}'
```

**Wynik:** ✅ `{"name": "tmdb_verification", "active": false}`

#### 3. Próba pobrania osoby (wyłączony flag - powinno zwrócić 202)
```bash
curl -X GET "http://localhost:8000/api/v1/people/non-existent-person-1764984984"
```

**Wynik:** ✅ `202 Accepted`
```json
{
  "job_id": "bd9232c3-4e64-45e3-87eb-ca1b0cdc4fdd",
  "status": "PENDING",
  "message": "Generation queued for person by slug",
  "slug": "non-existent-person-1764984984",
  "confidence": null,
  "confidence_level": "unknown",
  "locale": "en-US"
}
```

**Weryfikacja:** System przeszedł do generowania AI bez weryfikacji TMDb (fallback).

#### 4. Włączenie feature flag'a `tmdb_verification`
```bash
curl -X POST "http://localhost:8000/api/v1/admin/flags/tmdb_verification" \
  -H "Content-Type: application/json" \
  -d '{"state":"on"}'
```

**Wynik:** ✅ `{"name": "tmdb_verification", "active": true}`

#### 5. Próba pobrania osoby (włączony flag - powinno zwrócić 404)
```bash
curl -X GET "http://localhost:8000/api/v1/people/non-existent-person-1764984990"
```

**Wynik:** ✅ `404 Not Found`
```json
{
  "error": "Person not found"
}
```

**Weryfikacja:** System zwrócił 404, ponieważ TMDb nie znalazło osoby (weryfikacja TMDb działa).

### ✅ Test 16 - Status: **SUKCES**

---

## 📊 Podsumowanie

### Wyniki testów:

| Test | Typ Encji | Status | Opis |
|------|-----------|--------|------|
| Test 15 | Movie | ✅ SUKCES | Feature flag działa poprawnie - wyłączenie pozwala na generowanie AI, włączenie wymaga weryfikacji TMDb |
| Test 16 | Person | ✅ SUKCES | Feature flag działa poprawnie - wyłączenie pozwala na generowanie AI, włączenie wymaga weryfikacji TMDb |

### Weryfikacja zachowania:

#### Gdy `tmdb_verification` jest **wyłączony** (`active: false`):
- ✅ System **pomija** weryfikację TMDb
- ✅ System **zwraca 202 Accepted** i kolejkuje generację AI (fallback)
- ✅ Response zawiera `job_id` i `status: "PENDING"`

#### Gdy `tmdb_verification` jest **włączony** (`active: true`):
- ✅ System **wymaga** weryfikacji TMDb przed generowaniem
- ✅ Jeśli TMDb nie znajdzie encji, system **zwraca 404 Not Found**
- ✅ Response zawiera `{"error": "Movie not found"}` lub `{"error": "Person not found"}`

### Wnioski:

1. ✅ **Feature flag `tmdb_verification` działa poprawnie** dla wszystkich zaimplementowanych typów encji (Movie, Person)
2. ✅ **Wyłączenie flag'a pozwala na generowanie AI bez weryfikacji TMDb** (fallback)
3. ✅ **Włączenie flag'a wymaga weryfikacji TMDb** przed generowaniem
4. ✅ **Zachowanie jest zgodne z oczekiwaniami** opisanymi w dokumentacji

---

## 🔧 Środowisko testowe

- **Aplikacja:** MovieMind API (localhost:8000)
- **Docker:** Wszystkie kontenery działają (moviemind-php, moviemind-db, moviemind-redis, moviemind-horizon)
- **Data testów:** 2025-12-06
- **Wersja API:** v1

---

**Raport wygenerowany:** 2025-12-06  
**Status:** ✅ Wszystkie testy przechodzą pomyślnie


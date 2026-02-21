# MovieMind API - Manual Testing Guide

## 📋 Spis treści

1. [Szybki start](#szybki-start)
2. [Import kolekcji Postman](#import-kolekcji-postman)
3. [Użycie skryptów testowych](#użycie-skryptów-testowych)
4. [Przykłady użycia curl](#przykłady-użycia-curl)
5. [Kody odpowiedzi](#kody-odpowiedzi)
6. [Scenariusze testowe](#scenariusze-testowe)

---

## 🚀 Szybki start

### Wymagania

- **Postman** (opcjonalnie) - do importu kolekcji
- **curl** - do testów z linii poleceń
- **jq** (opcjonalnie) - do formatowania JSON w bash
- **Node.js** (opcjonalnie) - do uruchomienia skryptu JS

### Base URL

- **Lokalnie:** `http://localhost:8000`
- **Staging:** (ustaw w zmiennych środowiskowych)
- **Produkcja:** (ustaw w zmiennych środowiskowych)

### 🔐 Autoryzacja

| Typ autoryzacji | Nagłówek | Gdzie używany | Przykład |
|-----------------|----------|---------------|----------|
| **ApiKeyAuth** | `X-API-Key` | Publiczne API (np. `/generate`) | `mm_abc123...` |
| **AdminToken** | `X-Admin-Token` | Admin API (`/api/v1/admin/*`) | Token z `.env` |
| **Basic Auth** | `Authorization` | Horizon Dashboard (tylko PROD) | `Basic base64(user:pass)` |

> **Uwaga:** W środowisku lokalnym Basic Auth dla Horizon nie jest wymagane.

---

## 📥 Import kolekcji Postman

1. Otwórz Postman
2. Kliknij **Import**
3. Wybierz plik: `docs/MovieMind_API.postman_collection.json`
4. Kolekcja zostanie zaimportowana z wszystkimi endpointami

### Zmienne środowiskowe w Postman

Po imporcie ustaw zmienne:
- `base_url` - URL serwera (domyślnie: `http://localhost:8000`)
- `movie_slug` - Przykładowy slug filmu (domyślnie: `the-matrix-1999`)
- `person_slug` - Przykładowy slug osoby (domyślnie: `keanu-reeves-1964`)
- `search_query` - Przykładowe zapytanie (domyślnie: `Matrix`)

---

## 🧪 Użycie skryptów testowych

### Bash Script

```bash
# Nadaj uprawnienia do wykonania
chmod +x docs/test-api.sh

# Uruchom testy (domyślnie: http://localhost:8000)
# Bez klucza API (endpointy publiczne mogą zwrócić błąd 401)
./docs/test-api.sh

# Z kluczem API (jako argument)
./docs/test-api.sh http://localhost:8000 mm_twoj_klucz_api...

# Z kluczem API (jako zmienna środowiskowa)
API_KEY=mm_twoj_klucz_api... ./docs/test-api.sh
```

### Node.js Script

```bash
# Zainstaluj zależności (opcjonalnie, jeśli używasz starszego Node)
npm install node-fetch

# Uruchom testy
# Bez klucza API
node docs/test-api.js

# Z kluczem API (jako argument)
node docs/test-api.js http://localhost:8000 mm_twoj_klucz_api...

# Z kluczem API (jako zmienna środowiskowa)
API_KEY=mm_twoj_klucz_api... node docs/test-api.js
```

---

## 📝 Przykłady użycia curl

### 1. Lista filmów

```bash
# Podstawowe
curl -X GET "http://localhost:8000/api/v1/movies" \
  -H "X-API-Key: <TWOJ_KLUCZ_API>" \
  -H "Accept: application/json"

# Z wyszukiwaniem
curl -X GET "http://localhost:8000/api/v1/movies?q=Matrix" \
  -H "X-API-Key: <TWOJ_KLUCZ_API>" \
  -H "Accept: application/json" | jq
```

### 2. Zaawansowane wyszukiwanie

```bash
# Z filtrem roku
curl -X GET "http://localhost:8000/api/v1/movies/search?q=Matrix&year=1999" \
  -H "Accept: application/json" | jq

# Z filtrem reżysera
curl -X GET "http://localhost:8000/api/v1/movies/search?q=Matrix&director=Wachowski" \
  -H "Accept: application/json" | jq

# Z filtrem aktora
curl -X GET "http://localhost:8000/api/v1/movies/search?q=Matrix&actor=Keanu" \
  -H "Accept: application/json" | jq

# Z paginacją
curl -X GET "http://localhost:8000/api/v1/movies/search?q=Matrix&page=1&per_page=10" \
  -H "Accept: application/json" | jq
```

### 3. Szczegóły filmu

```bash
# Podstawowe
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
  -H "X-API-Key: <TWOJ_KLUCZ_API>" \
  -H "Accept: application/json" | jq

# Z wyborem konkretnego opisu
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999?description_id=123" \
  -H "Accept: application/json" | jq

# Z wyborem z disambiguation
curl -X GET "http://localhost:8000/api/v1/movies/bad-boys?tmdb_id=9739" \
  -H "Accept: application/json" | jq
```

### 4. Generowanie opisu

```bash
# Pojedynczy context tag
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "X-API-Key: <TWOJ_KLUCZ_API>" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "en-US",
    "context_tag": "humorous"
  }' | jq

# Wiele context tagów
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "en-US",
    "context_tag": ["DEFAULT", "modern", "humorous"]
  }' | jq
```

### 5. Status joba

```bash
# Zastąp {job_id} rzeczywistym ID z odpowiedzi generate
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "Accept: application/json" | jq
```

### 6. Odświeżanie danych

```bash
curl -X POST "http://localhost:8000/api/v1/movies/the-matrix-1999/refresh" \
  -H "Accept: application/json" | jq
```

---

## 📊 Kody odpowiedzi

| Kod | Znaczenie | Przykład |
|-----|-----------|----------|
| **200** | Sukces | Film znaleziony, lista zwrócona |
| **202** | Zaakceptowano | Job w kolejce do przetworzenia |
| **300** | Multiple Choices | Disambiguation - wiele wyników |
| **404** | Nie znaleziono | Film/osoba nie istnieje |
| **422** | Błąd walidacji | Nieprawidłowe parametry |
| **500** | Błąd serwera | Wewnętrzny błąd aplikacji |

---

## 🎯 Scenariusze testowe

### Scenariusz 1: Wyszukiwanie i generowanie opisu

```bash
# 1. Wyszukaj film
curl -X GET "http://localhost:8000/api/v1/movies/search?q=Matrix&year=1999" \
  -H "Accept: application/json" | jq

# 2. Pobierz szczegóły (jeśli istnieje)
curl -X GET "http://localhost:8000/api/v1/movies/the-matrix-1999" \
  -H "Accept: application/json" | jq

# 3. Wygeneruj opis (jeśli nie istnieje lub chcesz nowy)
curl -X POST "http://localhost:8000/api/v1/generate" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "slug": "the-matrix-1999",
    "locale": "en-US",
    "context_tag": "DEFAULT"
  }' | jq

# 4. Sprawdź status joba (z job_id z kroku 3)
curl -X GET "http://localhost:8000/api/v1/jobs/{job_id}" \
  -H "Accept: application/json" | jq
```

### Scenariusz 2: Disambiguation

```bash
# 1. Wyszukaj niejednoznaczny film
curl -X GET "http://localhost:8000/api/v1/movies/bad-boys" \
  -H "Accept: application/json" | jq

# 2. Jeśli otrzymasz 300, wybierz konkretny film z opcji
curl -X GET "http://localhost:8000/api/v1/movies/bad-boys?tmdb_id=9739" \
  -H "Accept: application/json" | jq
```

### Scenariusz 3: Paginacja wyników

```bash
# 1. Pierwsza strona
curl -X GET "http://localhost:8000/api/v1/movies/search?q=Test&page=1&per_page=10" \
  -H "Accept: application/json" | jq

# 2. Druga strona
curl -X GET "http://localhost:8000/api/v1/movies/search?q=Test&page=2&per_page=10" \
  -H "Accept: application/json" | jq
```

### Scenariusz 4: Filtrowanie wyników

```bash
# Wyszukiwanie z wieloma filtrami
curl -X GET "http://localhost:8000/api/v1/movies/search?q=Matrix&year=1999&director=Wachowski&actor=Keanu" \
  -H "Accept: application/json" | jq
```

---

## 🔍 Przykładowe odpowiedzi

### Sukces (200)

```json
{
  "id": 1,
  "title": "The Matrix",
  "slug": "the-matrix-1999",
  "release_year": 1999,
  "director": "Wachowski",
  "descriptions": [
    {
      "id": 1,
      "locale": "en-US",
      "text": "A computer hacker learns...",
      "context_tag": "DEFAULT",
      "origin": "GENERATED"
    }
  ],
  "_links": {
    "self": {
      "href": "http://localhost:8000/api/v1/movies/the-matrix-1999"
    }
  }
}
```

### Generation Queued (202)

```json
{
  "job_id": "abc123",
  "status": "queued",
  "slug": "the-matrix-1999",
  "confidence": 0.85,
  "confidence_level": "high",
  "locale": "en-US"
}
```

### Disambiguation (300)

```json
{
  "error": "Multiple movies found",
  "message": "Czy chodzi o któryś z tych filmów? Wybierz jeden z poniższych:",
  "slug": "bad-boys",
  "options": [
    {
      "slug": "bad-boys-1995",
      "title": "Bad Boys",
      "release_year": 1995,
      "director": "Michael Bay",
      "overview": "Two hip detectives...",
      "select_url": "http://localhost:8000/api/v1/movies/bad-boys-1995"
    }
  ],
  "count": 2,
  "hint": "Use the slug from options to access specific movie (e.g., GET /api/v1/movies/{slug})"
}
```

---

## 📚 Dodatkowe zasoby

- **Kolekcja Postman:** `docs/MovieMind_API.postman_collection.json`
- **Skrypt Bash:** `docs/test-api.sh`
- **Skrypt Node.js:** `docs/test-api.js`
- **Dokumentacja API:** (dodaj link jeśli dostępna)

---

## ⚠️ Uwagi

1. **Cache:** Odpowiedzi są cache'owane przez 1 godzinę
2. **Rate Limiting:** Endpointy mogą mieć ograniczenia częstotliwości
3. **Feature Flags:** Niektóre funkcje mogą być wyłączone przez feature flags
4. **Admin Endpoints:** Wymagają nagłówka `X-Admin-Token` (zdefiniowanego w `.env`). Dashboard Horizon na produkcji wymaga Basic Auth.

---

## 🐛 Troubleshooting

### Problem: 404 Not Found

- Sprawdź czy slug jest poprawny
- Sprawdź czy film istnieje w bazie
- Sprawdź czy feature flag `ai_description_generation` jest włączony

### Problem: 422 Validation Error

- Sprawdź parametry zapytania
- Sprawdź format JSON w body
- Sprawdź zakresy wartości (np. year: 1900-2034)

### Problem: 500 Internal Server Error

- Sprawdź logi serwera
- Sprawdź konfigurację OpenAI API
- Sprawdź połączenie z bazą danych

---

**Ostatnia aktualizacja:** 2024-12-17


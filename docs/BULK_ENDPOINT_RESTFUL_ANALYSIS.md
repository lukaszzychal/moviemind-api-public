# Bulk Endpoint RESTful Analysis

## Obecna Implementacja

**Endpoint:** `POST /api/v1/movies/bulk`

**Request:**
```json
{
  "slugs": ["the-matrix-1999", "inception-2010"],
  "include": ["descriptions", "people", "genres"]
}
```

**Response:**
```json
{
  "data": [/* movies */],
  "not_found": ["non-existent-slug"],
  "count": 2,
  "requested_count": 2
}
```

## Problem z RESTful

### ❌ Obecne podejście (POST /movies/bulk)

**Problemy:**
1. **POST dla READ operation** - POST jest używane do tworzenia/modyfikacji zasobów, nie do pobierania
2. **"bulk" nie jest zasobem** - w RESTful, ścieżka powinna reprezentować zasób, nie akcję
3. **Niespójność semantyczna** - GET dla pojedynczego filmu, POST dla wielu (powinno być GET dla obu)

### ✅ RESTful Best Practices

**Zasady:**
- GET = READ (pobieranie danych)
- POST = CREATE (tworzenie nowych zasobów)
- PUT/PATCH = UPDATE (aktualizacja)
- DELETE = DELETE (usuwanie)

**Dla bulk READ operations:**
- `GET /movies?ids=1,2,3` - najbardziej RESTful
- `GET /movies?slugs=slug1,slug2,slug3` - akceptowalne
- `POST /movies/batch` - mniej idealne, ale akceptowalne dla bardzo długich list

## Rekomendowane Rozwiązania

### Opcja 1: GET z Query Parameters (Najbardziej RESTful) ⭐

**Endpoint:** `GET /api/v1/movies?slugs=slug1,slug2,slug3&include=descriptions,people`

**Zalety:**
- ✅ Pełna zgodność z RESTful
- ✅ GET dla READ operation
- ✅ Cacheable (GET requests są cacheable)
- ✅ Idempotentne (GET requests są idempotentne)
- ✅ Spójne z innymi GET endpoints

**Wady:**
- ⚠️ Limit długości URL (~2000 znaków)
- ⚠️ Trudniejsze do debugowania (długie URL-e)

**Implementacja:**
```php
// GET /movies?slugs=slug1,slug2,slug3
public function index(Request $request): JsonResponse
{
    $slugs = $request->query('slugs');
    
    if ($slugs !== null) {
        // Bulk retrieve
        $slugsArray = is_array($slugs) ? $slugs : explode(',', $slugs);
        return $this->bulk($slugsArray, $request->query('include'));
    }
    
    // Normal search
    $q = $request->query('q');
    // ... existing logic
}
```

### Opcja 2: GET /movies/batch (Kompromis)

**Endpoint:** `GET /api/v1/movies/batch?slugs=slug1,slug2,slug3`

**Zalety:**
- ✅ GET dla READ operation
- ✅ Wyraźne rozróżnienie od normalnego search
- ✅ Cacheable

**Wady:**
- ⚠️ "batch" nie jest zasobem (ale akceptowalne jako akcja)

### Opcja 3: Zachować POST /movies/bulk (Obecne)

**Zalety:**
- ✅ Brak limitu długości URL
- ✅ Łatwiejsze do debugowania (body w JSON)
- ✅ Obsługuje bardzo długie listy slugów

**Wady:**
- ❌ Nie jest w pełni RESTful (POST dla READ)
- ❌ Nie cacheable
- ❌ Niespójne z innymi GET endpoints

## Rekomendacja

### ⭐ Opcja 1: Rozszerzyć GET /movies

**Implementacja:**
1. Rozszerzyć `GET /movies` o parametr `slugs`
2. Jeśli `slugs` jest podane, użyć bulk retrieve
3. Jeśli nie, użyć normalnego search
4. Zachować `POST /movies/bulk` jako deprecated/alternatywę dla bardzo długich list (>50 slugów)

**Przykład:**
```bash
# Normal search
GET /api/v1/movies?q=matrix

# Bulk retrieve (short list)
GET /api/v1/movies?slugs=the-matrix-1999,inception-2010

# Bulk retrieve (long list) - fallback to POST
POST /api/v1/movies/bulk
{
  "slugs": [/* 50+ slugs */]
}
```

## Porównanie z Popularnymi API

### GitHub API
- `GET /repos/:owner/:repo` - single
- `GET /repos?ids=1,2,3` - bulk (query params)

### Stripe API
- `GET /customers/:id` - single
- `GET /customers?ids=1,2,3` - bulk (query params)

### JSON:API
- `GET /articles?filter[id]=1,2,3` - bulk (query params)

## Wnioski

**Najlepsze rozwiązanie:** Rozszerzyć `GET /movies` o parametr `slugs`, zachować `POST /movies/bulk` jako fallback dla bardzo długich list.

**Priorytet:** Średni (UX improvement, nie krytyczne)

**Breaking Change:** Tak (ale można zachować backward compatibility przez deprecation)


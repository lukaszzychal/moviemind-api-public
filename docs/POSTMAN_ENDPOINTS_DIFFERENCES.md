# 📋 Różnice między endpointami w Postman Collection

**Data:** 2025-11-01

---

## ❓ Pytanie

**"Jaka jest różnica między endpointami:**
- Linia 30: `Movies - Show (by slug)`
- Linia 50: `People - Show (by slug)`
- Linia 155: `Movies - Show (missing slug => 202 when generation on)`
- Linia 175: `People - Show (missing slug => 202 when generation on)`"

---

## ✅ Odpowiedź

To **te same endpointy**, ale testujące **różne scenariusze**:

| Endpoint | Scenariusz | Slug | Status | Opis |
|----------|-----------|------|--------|------|
| **30, 50** | ✅ **Dane ISTNIEJĄ** | `the-matrix`, `christopher-nolan` | `200 OK` | Film/osoba jest w bazie |
| **155, 175** | ❌ **Dane NIE ISTNIEJĄ** + generacja włączona | `annihilation`, `john-doe` | `202 Accepted` | Film/osoba nie istnieje, generacja automatyczna |

---

## 📊 Szczegółowe porównanie

### **1. Movies - Show (linia 30) vs Movies - Show (missing) (linia 155)**

**Endpoint:** `GET /api/v1/movies/{slug}`

#### **Linia 30: "Movies - Show (by slug)"**
```http
GET /api/v1/movies/the-matrix
```

**Scenariusz:**
- ✅ Film **ISTNIEJE** w bazie danych
- ✅ Zwraca pełne dane filmu

**Odpowiedź:**
```json
{
  "id": 1,
  "slug": "the-matrix",
  "title": "The Matrix",
  "release_year": 1999,
  "director": {...},
  "default_description": {...},
  "_links": {...}
}
```
**Status:** `200 OK`

---

#### **Linia 155: "Movies - Show (missing slug => 202 when generation on)"**
```http
GET /api/v1/movies/annihilation
```

**Scenariusz:**
- ❌ Film **NIE ISTNIEJE** w bazie danych
- ✅ Feature flag `ai_description_generation` jest **włączony**
- ✅ API automatycznie **kolejkuje generację** przez AI

**Odpowiedź:**
```json
{
  "job_id": "c6de4a2f-1111-2222-3333-abcdefabcdef",
  "status": "PENDING",
  "slug": "annihilation"
}
```
**Status:** `202 Accepted`

**Co się dzieje:**
1. API nie znajduje filmu w bazie
2. Sprawdza feature flag (`ai_description_generation`)
3. Tworzy `job_id` i kolejkuje generację
4. Zwraca `202 Accepted` z informacją o job

---

### **2. People - Show (linia 50) vs People - Show (missing) (linia 175)**

**Endpoint:** `GET /api/v1/people/{slug}`

#### **Linia 50: "People - Show (by slug)"**
```http
GET /api/v1/people/christopher-nolan
```

**Scenariusz:**
- ✅ Osoba **ISTNIEJE** w bazie danych
- ✅ Zwraca pełne dane osoby

**Odpowiedź:**
```json
{
  "id": 123,
  "slug": "christopher-nolan",
  "name": "Christopher Nolan",
  "bios": [...],
  "movies": [...],
  "_links": {...}
}
```
**Status:** `200 OK`

---

#### **Linia 175: "People - Show (missing slug => 202 when generation on)"**
```http
GET /api/v1/people/john-doe
```

**Scenariusz:**
- ❌ Osoba **NIE ISTNIEJE** w bazie danych
- ✅ Feature flag `ai_bio_generation` jest **włączony**
- ✅ API automatycznie **kolejkuje generację** przez AI

**Odpowiedź:**
```json
{
  "job_id": "c6de4a2f-1111-2222-3333-abcdefabcdef",
  "status": "PENDING",
  "slug": "john-doe"
}
```
**Status:** `202 Accepted`

---

## 🔍 Implementacja w kodzie

### **MovieController.php**

```php
public function show(string $slug)
{
    $movie = $this->movieRepository->findBySlugWithRelations($slug);
    
    if ($movie) {
        // ✅ Film istnieje - zwróć dane
        return response()->json([...], 200);
    }
    
    // ❌ Film nie istnieje
    if (! Feature::active('ai_description_generation')) {
        return response()->json(['error' => 'Movie not found'], 404);
    }
    
    // ✅ Generacja włączona - kolejkuj
    $jobId = (string) Str::uuid();
    event(new MovieGenerationRequested($slug, $jobId));
    
    return response()->json([
        'job_id' => $jobId,
        'status' => 'PENDING',
        'slug' => $slug,
    ], 202);  // ← 202 Accepted
}
```

---

### **PersonController.php**

```php
public function show(string $slug)
{
    $person = $this->personRepository->findBySlugWithRelations($slug);
    
    if ($person) {
        // ✅ Osoba istnieje - zwróć dane
        return response()->json([...], 200);
    }
    
    // ❌ Osoba nie istnieje
    if (! Feature::active('ai_bio_generation')) {
        return response()->json(['error' => 'Person not found'], 404);
    }
    
    // ✅ Generacja włączona - kolejkuj
    $jobId = (string) Str::uuid();
    event(new PersonGenerationRequested($slug, $jobId));
    
    return response()->json([
        'job_id' => $jobId,
        'status' => 'PENDING',
        'slug' => $slug,
    ], 202);  // ← 202 Accepted
}
```

---

## 📊 Tabela porównawcza

| Parametr | Linia 30/50 (Istniejące) | Linia 155/175 (Brakujące) |
|----------|---------------------------|---------------------------|
| **Endpoint** | `GET /api/v1/movies/{slug}`<br>`GET /api/v1/people/{slug}` | `GET /api/v1/movies/{slug}`<br>`GET /api/v1/people/{slug}` |
| **Slug** | `the-matrix`, `christopher-nolan` | `annihilation`, `john-doe` |
| **Status w bazie** | ✅ Istnieje | ❌ Nie istnieje |
| **Feature flag** | Nie sprawdzany | ✅ Włączony |
| **Akcja** | Zwraca dane | Kolejkuje generację |
| **HTTP Status** | `200 OK` | `202 Accepted` |
| **Response body** | Pełne dane (id, title, etc.) | `job_id`, `status`, `slug` |
| **Następne kroki** | - | Sprawdź `/api/v1/jobs/{jobId}` |

---

## 🎯 Kiedy używać którego endpointa?

### **Testowanie istnienia danych (30, 50):**
```http
GET /api/v1/movies/the-matrix
GET /api/v1/people/christopher-nolan
```
✅ Używaj gdy chcesz sprawdzić czy film/osoba istnieje w bazie

---

### **Testowanie automatycznej generacji (155, 175):**
```http
GET /api/v1/movies/annihilation
GET /api/v1/people/john-doe
```
✅ Używaj gdy chcesz przetestować:
- Automatyczną kolejkę generacji
- Feature flags
- Job tracking (`/api/v1/jobs/{jobId}`)

---

## ⚠️ Ważne uwagi

### **1. Feature Flags muszą być włączone**

Dla endpointów 155 i 175 (missing slug), feature flags muszą być aktywne:
- `ai_description_generation` (Movies)
- `ai_bio_generation` (People)

**Jeśli flagi są wyłączone:**
- API zwróci `404 Not Found` zamiast `202 Accepted`

---

### **2. Job tracking**

Po otrzymaniu `202 Accepted`, możesz śledzić status job:

```http
GET /api/v1/jobs/{jobId}
```

**Możliwe statusy:**
- `PENDING` - w kolejce
- `PROCESSING` - przetwarzany
- `DONE` - ukończony
- `FAILED` - nieudany

---

### **3. Slug format**

**Movies:**
- ✅ `the-matrix` (tylko tytuł)
- ✅ `the-matrix-1999` (tytuł + rok) - lepsze dopasowanie

**People:**
- ✅ `christopher-nolan` (imię-nazwisko)
- ✅ `keanu-reeves` (imię-nazwisko)

---

## 📝 Podsumowanie

| Endpoint Postman | Scenariusz | HTTP Status | Użycie |
|------------------|------------|-------------|--------|
| **30, 50** | Dane istnieją | `200 OK` | Testowanie odczytu danych |
| **155, 175** | Dane nie istnieją + generacja | `202 Accepted` | Testowanie automatycznej generacji |

**To nie są różne endpointy, ale różne scenariusze dla tych samych endpointów!**

---

**Ostatnia aktualizacja:** 2025-11-01


# 📋 Endpoint `/api/v1/generate` - Dokumentacja Odpowiedzi

**Endpoint:** `POST /api/v1/generate`  
**Status:** ✅ Działa

---

## 📥 Request

```json
{
  "entity_type": "MOVIE" | "PERSON" | "ACTOR",
  "entity_id": "string (slug)",
  "locale": "string (optional)",
  "context_tag": "string (optional)"
}
```

---

## 📤 Odpowiedzi

### **1. ✅ 202 Accepted - Generowanie w kolejce**

**Gdy:** Film/Person nie istnieje i flaga jest włączona

**Status Code:** `202 Accepted`

**Response:**
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "the-matrix-1999"
}
```

**Dla PERSON/ACTOR:**
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "PENDING",
  "message": "Generation queued for person by slug",
  "slug": "keanu-reeves"
}
```

**Co się dzieje:**
- ✅ Sprawdzono czy entity istnieje → nie istnieje
- ✅ Sprawdzono flagę feature → włączona
- ✅ Utworzono `job_id` (UUID)
- ✅ Zapisano status `PENDING` w cache
- ✅ Wyemitowano Event (`MovieGenerationRequested` / `PersonGenerationRequested`)
- ✅ Job dodany do kolejki (asynchronicznie)

---

### **2. ✅ 200 OK - Entity już istnieje**

**Gdy:** Film/Person już istnieje w bazie

**Status Code:** `200 OK`

**Response (MOVIE):**
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "DONE",
  "message": "Movie already exists",
  "slug": "the-matrix-1999",
  "id": 123
}
```

**Response (PERSON/ACTOR):**
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "DONE",
  "message": "Person already exists",
  "slug": "keanu-reeves",
  "id": 456
}
```

**Co się dzieje:**
- ✅ Sprawdzono czy entity istnieje → **istnieje**
- ✅ Zwrócono od razu (nie dodawaj do kolejki)
- ✅ Status `DONE` bo nie trzeba generować

---

### **3. ❌ 403 Forbidden - Feature wyłączony**

**Gdy:** Flaga feature jest wyłączona

**Status Code:** `403 Forbidden`

**Response:**
```json
{
  "error": "Feature not available"
}
```

**Przyczyny:**
- `ai_description_generation` wyłączona dla MOVIE
- `ai_bio_generation` wyłączona dla PERSON/ACTOR

**Jak włączyć:**
```bash
# Przez API
POST /api/v1/admin/flags/ai_description_generation
Body: {"state": "on"}

# Przez Tinker
Laravel\Pennant\Feature::activate('ai_description_generation');
```

---

### **4. ❌ 400 Bad Request - Błędne dane**

**Gdy:** Błędny `entity_type` lub brak wymaganych pól

**Status Code:** `400 Bad Request`

**Response (błędny entity_type):**
```json
{
  "error": "Invalid entity type"
}
```

**Response (błędy walidacji):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "entity_type": [
      "The entity type field is required."
    ],
    "entity_id": [
      "The entity ID field is required."
    ]
  }
}
```

**Możliwe błędy walidacji:**
- `entity_type` - wymagane, musi być: `MOVIE`, `PERSON`, `ACTOR`
- `entity_id` - wymagane, string, max 255 znaków
- `locale` - opcjonalne, string, max 10 znaków
- `context_tag` - opcjonalne, string, max 64 znaków

---

## 🔄 Pełny Flow

### **Scenariusz 1: Nowy film (generowanie)**

```bash
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "entity_id": "new-movie-slug"
}
```

**Response (202):**
```json
{
  "job_id": "uuid-here",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "new-movie-slug"
}
```

**Następnie:**
1. Job przetwarzany asynchronicznie (queue)
2. Sprawdź status: `GET /api/v1/jobs/{job_id}`
3. Po zakończeniu: `GET /api/v1/movies/{slug}`

---

### **Scenariusz 2: Film już istnieje**

```bash
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "entity_id": "existing-movie-slug"
}
```

**Response (200):**
```json
{
  "job_id": "uuid-here",
  "status": "DONE",
  "message": "Movie already exists",
  "slug": "existing-movie-slug",
  "id": 123
}
```

**Następnie:**
- Możesz od razu użyć: `GET /api/v1/movies/existing-movie-slug`

---

## 📊 Przykłady z curl

### **1. Generowanie nowego filmu:**
```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "MOVIE",
    "entity_id": "the-matrix-1999"
  }'
```

**Response (202):**
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "the-matrix-1999"
}
```

---

### **2. Sprawdzenie statusu joba:**
```bash
curl http://localhost:8000/api/v1/jobs/7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d
```

**Response (200 - w trakcie):**
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "PENDING",
  "entity": "MOVIE",
  "slug": "the-matrix-1999"
}
```

**Response (200 - zakończone):**
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "DONE",
  "entity": "MOVIE",
  "slug": "the-matrix-1999",
  "movie_id": 123
}
```

---

### **3. Generowanie osoby:**
```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -d '{
    "entity_type": "PERSON",
    "entity_id": "keanu-reeves"
  }'
```

**Response (202):**
```json
{
  "job_id": "8e0a6b2d-5f7c-4e1b-9d8a-2c6f4b1a3e9d",
  "status": "PENDING",
  "message": "Generation queued for person by slug",
  "slug": "keanu-reeves"
}
```

---

## 🎯 Podsumowanie

| Scenariusz | Status Code | Response |
|------------|-------------|----------|
| **Nowy film/osoba** | `202 Accepted` | `job_id`, `status: PENDING`, `slug` |
| **Już istnieje** | `200 OK` | `job_id`, `status: DONE`, `slug`, `id` |
| **Feature OFF** | `403 Forbidden` | `error: "Feature not available"` |
| **Błędne dane** | `400 Bad Request` | `error` lub `errors` (walidacja) |

---

## 🔍 Sprawdzenie statusu

Po otrzymaniu `job_id` możesz sprawdzić status:

```bash
GET /api/v1/jobs/{job_id}
```

**Możliwe statusy:**
- `PENDING` - w kolejce, czeka na przetworzenie
- `DONE` - zakończone pomyślnie
- `FAILED` - błąd podczas generowania

---

**Ostatnia aktualizacja:** 2025-11-01


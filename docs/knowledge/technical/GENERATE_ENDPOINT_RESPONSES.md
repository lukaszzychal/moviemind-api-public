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

### **1. ✅ 202 Accepted – zadanie w kolejce (nowy entity)**

**Gdy:** Film lub osoba nie istnieje, a odpowiednia flaga jest aktywna.

**Status Code:** `202 Accepted`

**Przykład (MOVIE):**
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "new-movie-slug",
  "confidence": 0.82,
  "confidence_level": "medium"
}
```

**Przykład (PERSON/ACTOR):**
```json
{
  "job_id": "7f9d5a7c-6e6c-4f3a-9c5b-3a7f9b8b1e2d",
  "status": "PENDING",
  "message": "Generation queued for person by slug",
  "slug": "new-person-slug",
  "confidence": 0.76,
  "confidence_level": "medium"
}
```

**Co się dzieje:**
- ✅ Walidujemy flagę feature i slug
- ✅ Tworzymy `job_id` (UUID) oraz zapisujemy status `PENDING` w cache (`ai_job:{job_id}`)
- ✅ Emitujemy event (`MovieGenerationRequested` / `PersonGenerationRequested`)
- ✅ Listener wybiera typ joba (Mock/Real) i umieszcza go w kolejce (Redis/Horizon)

---

### **2. ✅ 202 Accepted – zadanie w kolejce (entity istnieje)**

**Gdy:** Film / osoba już istnieje – zawsze kolejkujemy nową generację, ale od razu zwracamy szczegóły.

**Status Code:** `202 Accepted`

**Response (MOVIE):**
```json
{
  "job_id": "a40d1cd3-92ad-4a61-86fa-8e8fcfca0b4a",
  "status": "PENDING",
  "message": "Generation queued for existing movie slug",
  "slug": "the-matrix-1999",
  "existing_id": 42,
  "description_id": 314,
  "confidence": 0.91,
  "confidence_level": "high"
}
```

**Response (PERSON/ACTOR):**
```json
{
  "job_id": "d51fb6a8-4bfe-4f69-aacd-4bc19f420c92",
  "status": "PENDING",
  "message": "Generation queued for existing person slug",
  "slug": "keanu-reeves",
  "existing_id": 17,
  "bio_id": 281,
  "confidence": 0.88,
  "confidence_level": "high"
}
```

**Co się dzieje:**
- ✅ Baseline (aktualny `description_id` / `bio_id`) trafia do joba
- ✅ Job zapisuje nową wersję i używa blokady Redis, aby tylko pierwsza ukończona generacja stała się domyślna
- ✅ Pozostałe joby zapisują alternatywne wersje (np. inne `context_tag`)
- ✅ Status w cache po zakończeniu zawiera ID świeżo wygenerowanej wersji

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

### **Scenariusz 2: Film już istnieje (wymuszenie regeneracji)**

```bash
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "entity_id": "existing-movie-slug"
}
```

**Response (202):**
```json
{
  "job_id": "uuid-here",
  "status": "PENDING",
  "message": "Generation queued for existing movie slug",
  "slug": "existing-movie-slug",
  "existing_id": 123,
  "description_id": 456
}
```

**Następnie:**
- Job w tle tworzy nową wersję opisu (pozostałe joby zachowają swoje wersje, ale nie nadpiszą domyślnej)
- Sprawdź status joba → po `DONE` odczytasz finalny `description_id` z cache
- Użyj `GET /api/v1/movies/existing-movie-slug?description_id={nowy_id}` aby pobrać alternatywną wersję

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
| **Nowy film/osoba** | `202 Accepted` | `job_id`, `status: PENDING`, `slug`, `confidence`, `confidence_level` |
| **Entity istnieje (regeneracja)** | `202 Accepted` | `job_id`, `status: PENDING`, `slug`, `existing_id`, `description_id`/`bio_id`, `confidence_level` |
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

**Ostatnia aktualizacja:** 2025-11-10


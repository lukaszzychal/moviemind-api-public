# 🔒 Slug Validation and Security

**Data:** 2025-11-01  
**Status:** ✅ Zaimplementowane

---

## 📋 Co zostało zaimplementowane

### **1. SlugValidator Helper**

Automatyczna walidacja formatu slug przed dodaniem do kolejki generacji.

**Lokalizacja:** `api/app/Helpers/SlugValidator.php`

**Metody:**
- `validateMovieSlug(string $slug): array` - walidacja slug filmu
- `validatePersonSlug(string $slug): array` - walidacja slug osoby

**Zwraca:**
```php
[
    'valid' => bool,        // true jeśli slug jest akceptowalny
    'confidence' => float,  // 0.0 - 1.0 (poziom pewności)
    'reason' => string       // Opis decyzji
]
```

---

### **2. Confidence Score**

Poziom wiarygodności slug jest zwracany w odpowiedzi API.

**Response format:**
```json
{
  "job_id": "uuid",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "the-matrix-1999",
  "confidence": 0.9,
  "confidence_level": "high"
}
```

**Poziomy confidence:**
- `high` (>= 0.9) - Wysoka pewność (np. slug z rokiem)
- `medium` (>= 0.7) - Średnia pewność
- `low` (>= 0.5) - Niska pewność (może być nieprawdziwy)
- `very_low` (< 0.5) - Bardzo niska (odrzucony)

---

### **3. Zmiana `entity_id` → `slug`**

**Nowy format (Rekomendowany):**
```json
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999"
}
```

**Stary format (Deprecated, ale działa):**
```json
{
  "entity_type": "MOVIE",
  "entity_id": "the-matrix-1999"
}
```

**Dlaczego zmiana?**
- ✅ Jasność - `slug` jest bardziej opisowe niż `entity_id`
- ✅ Spójność - API używa slugów wszędzie (`/movies/{slug}`, `/people/{slug}`)
- ✅ Backward compatibility - stary format nadal działa

---

## 🔍 Jak działa walidacja?

### **Dla filmów (`validateMovieSlug`):**

#### **✅ Wysoka pewność (0.9+):**
```php
"the-matrix-1999"              // Format: title-year
"inception-2010"               // Format: title-year
"the-prestige-2006-christopher-nolan"  // Format: title-year-director
```

**Dlaczego:**
- Zawiera rok w formacie YYYY (1888 - obecny rok + 2)
- Rozpoznawalny format slug filmu

---

#### **⚠️ Średnia pewność (0.5-0.9):**
```php
"random-string-xyz"            // 0.7 - ma słowa, ale bez roku
"some-movie-title"              // 0.6 - wygląda jak tytuł
```

**Dlaczego:**
- Wygląda jak sensowny slug, ale brak roku
- Może być rozpoznany przez OpenAI, ale niepewny

---

#### **❌ Odrzucone (< 0.5):**
```php
"1"                            // 0.0 - za krótki
"random-xyz-123"               // 0.4 - losowy pattern z numerami
"abc-123"                      // 0.4 - "abc-liczba" pattern
"123"                          // 0.1 - tylko cyfry
```

**Dlaczego:**
- Zbyt krótkie
- Losowe wzorce (abc-123, a-456)
- Tylko cyfry
- 3+ kolejne cyfry (nie rok)

---

### **Dla osób (`validatePersonSlug`):**

#### **✅ Wysoka pewność (0.85+):**
```php
"keanu-reeves"                 // 2 słowa (imię-nazwisko)
"christopher-nolan"            // 2 słowa
"mary-elizabeth-winstead"      // 3 słowa (first-middle-last)
```

**Dlaczego:**
- 2-4 słowa (typowe dla imion)
- Nie zawiera losowych wzorców

---

#### **⚠️ Średnia pewność (0.5-0.85):**
```php
"madonna"                      // 0.6 - pojedyncze słowo (mononym)
"bono"                         // 0.6 - pojedyncze słowo
```

**Dlaczego:**
- Pojedyncze słowo (może być mononym)
- Długie (>5 znaków) więc możliwe

---

#### **❌ Odrzucone (< 0.5):**
```php
"1"                            // 0.0 - za krótki
"a-123"                        // 0.1 - losowy pattern
"abc123"                       // 0.1 - nie ma myślników
```

---

## 📊 Przykłady walidacji

### **Test 1: Prawidłowy slug (film z rokiem)**
```bash
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999"
}
```

**Walidacja:**
- ✅ Format: `title-year` z rokiem 1999
- ✅ Confidence: **0.9** (high)
- ✅ Status: **202 Accepted**

**Response:**
```json
{
  "job_id": "uuid",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "the-matrix-1999",
  "confidence": 0.9,
  "confidence_level": "high"
}
```

---

### **Test 2: Słaby slug (losowy string)**
```bash
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "slug": "random-xyz-123"
}
```

**Walidacja:**
- ❌ Pattern: `title-abc-123` (losowe wzorce)
- ❌ Confidence: **0.4** (very_low)
- ❌ Status: **400 Bad Request**

**Response:**
```json
{
  "error": "Invalid slug format",
  "message": "Slug format suspicious",
  "confidence": 0.4,
  "slug": "random-xyz-123"
}
```

---

### **Test 3: Zbyt krótki slug**
```bash
POST /api/v1/generate
{
  "entity_type": "MOVIE",
  "slug": "1"
}
```

**Walidacja:**
- ❌ Długość: 1 znak (minimum 3)
- ❌ Confidence: **0.0**
- ❌ Status: **400 Bad Request**

**Response:**
```json
{
  "error": "Invalid slug format",
  "message": "Slug too short (minimum 3 characters)",
  "confidence": 0.0,
  "slug": "1"
}
```

---

## ✅ Backward Compatibility

### **Stary format (`entity_id`) nadal działa:**

```json
{
  "entity_type": "MOVIE",
  "entity_id": "the-matrix-1999"  ✅ Działa
}
```

**Co się dzieje:**
1. `prepareForValidation()` kopiuje `entity_id` → `slug`
2. Walidacja używa `slug`
3. Response zwraca `slug` w odpowiedzi

**Przykład:**
```bash
# Stary format
{"entity_type": "MOVIE", "entity_id": "inception-2010"}

# Automatycznie konwertowane na:
{"entity_type": "MOVIE", "slug": "inception-2010"}
```

---

## 🎯 Zalecenia użycia

### **✅ Rekomendowany format:**

```json
{
  "entity_type": "MOVIE",
  "slug": "the-matrix-1999"
}
```

### **⚠️ Deprecated (ale działa):**

```json
{
  "entity_type": "MOVIE",
  "entity_id": "the-matrix-1999"
}
```

---

## 📈 Poziomy Confidence

| Confidence | Level | Przykład | Rekomendacja |
|------------|-------|----------|--------------|
| **0.9 - 1.0** | `high` | `the-matrix-1999` | ✅ Bezpieczne |
| **0.7 - 0.89** | `medium` | `random-movie-title` | ⚠️ Sprawdź |
| **0.5 - 0.69** | `low` | `some-title-123` | ⚠️ Uwaga |
| **< 0.5** | `very_low` | `random-xyz-123` | ❌ Odrzucone |

---

## 🔄 Flow z zabezpieczeniami

```
1. Client → POST /api/v1/generate
   Body: {"entity_type": "MOVIE", "slug": "random-xyz-123"}
   ↓
2. GenerateRequest → Walidacja
   - Sprawdza czy slug lub entity_id istnieje
   - prepareForValidation() → konwertuje entity_id → slug
   ↓
3. GenerateController → SlugValidator
   - validateMovieSlug("random-xyz-123")
   - Zwraca: {valid: false, confidence: 0.4}
   ↓
4. Response → 400 Bad Request
   {
     "error": "Invalid slug format",
     "message": "Slug format suspicious",
     "confidence": 0.4,
     "slug": "random-xyz-123"
   }
```

**Dla prawidłowego slug:**
```
1. Client → POST /api/v1/generate
   Body: {"entity_type": "MOVIE", "slug": "the-matrix-1999"}
   ↓
2. SlugValidator → {valid: true, confidence: 0.9}
   ↓
3. Cache + Event + Job
   ↓
4. Response → 202 Accepted
   {
     "job_id": "uuid",
     "status": "PENDING",
     "slug": "the-matrix-1999",
     "confidence": 0.9,
     "confidence_level": "high"
   }
```

---

## 🧪 Testowanie

### **Test walidacji:**

```bash
# Prawidłowy slug
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"entity_type": "MOVIE", "slug": "the-matrix-1999"}'

# Nieprawidłowy slug
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"entity_type": "MOVIE", "slug": "random-xyz-123"}'
```

---

## 📝 Pliki zmienione

1. **`api/app/Helpers/SlugValidator.php`** - Nowy helper
2. **`api/app/Http/Controllers/Api/GenerateController.php`** - Dodana walidacja i confidence
3. **`api/app/Http/Requests/GenerateRequest.php`** - Dodane pole `slug`, backward compatibility
4. **`api/app/Services/HateoasService.php`** - Zmienione `entity_id` → `slug`
5. **`docs/postman/moviemind-api.postman_collection.json`** - Zaktualizowane przykłady

---

**Ostatnia aktualizacja:** 2025-11-01  
**Status:** ✅ Działa


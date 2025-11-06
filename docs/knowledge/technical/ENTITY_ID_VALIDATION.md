# 📋 Walidacja `entity_id` w `/api/v1/generate`

**Endpoint:** `POST /api/v1/generate`  
**Pole:** `entity_id`

---

## ✅ Prawidłowy `entity_id`

**Format:** String (slug), max 255 znaków

**Przykłady:**
```json
{
  "entity_type": "MOVIE",
  "entity_id": "the-matrix-1999"  ✅
}

{
  "entity_type": "PERSON",
  "entity_id": "keanu-reeves"     ✅
}
```

**Reguły walidacji:**
- `required` - pole wymagane
- `string` - musi być stringiem
- `max:255` - maksymalnie 255 znaków

---

## ❌ Nieprawidłowe `entity_id` - Co się dzieje?

### **1. Liczba zamiast stringa**

**Request:**
```json
{
  "entity_type": "MOVIE",
  "entity_id": 1
}
```

**Response (422 Unprocessable Entity):**
```json
{
  "message": "The entity ID must be a string.",
  "errors": {
    "entity_id": [
      "The entity ID must be a string."
    ]
  }
}
```

**Co się dzieje:**
- ❌ Walidacja nie przechodzi (wymagany string)
- ❌ Endpoint zwraca błąd walidacji
- ❌ Job nie jest tworzony

---

### **2. Pusty string**

**Request:**
```json
{
  "entity_type": "MOVIE",
  "entity_id": ""
}
```

**Response (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "entity_id": [
      "The entity ID field is required."
    ]
  }
}
```

**Co się dzieje:**
- ❌ Walidacja nie przechodzi (required)
- ❌ Endpoint zwraca błąd walidacji

---

### **3. Null**

**Request:**
```json
{
  "entity_type": "MOVIE",
  "entity_id": null
}
```

**Response (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "entity_id": [
      "The entity ID field is required."
    ]
  }
}
```

**Co się dzieje:**
- ❌ Walidacja nie przechodzi (required)
- ❌ Endpoint zwraca błąd walidacji

---

### **4. Brak pola `entity_id`**

**Request:**
```json
{
  "entity_type": "MOVIE"
}
```

**Response (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "entity_id": [
      "The entity ID field is required."
    ]
  }
}
```

**Co się dzieje:**
- ❌ Walidacja nie przechodzi (required)
- ❌ Endpoint zwraca błąd walidacji

---

### **5. String > 255 znaków**

**Request:**
```json
{
  "entity_type": "MOVIE",
  "entity_id": "a-very-long-slug-that-exceeds-255-characters..." // 256+ znaków
}
```

**Response (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "entity_id": [
      "The entity ID may not be greater than 255 characters."
    ]
  }
}
```

**Co się dzieje:**
- ❌ Walidacja nie przechodzi (max:255)
- ❌ Endpoint zwraca błąd walidacji

---

### **6. Nieprawidłowy typ (array, object)**

**Request:**
```json
{
  "entity_type": "MOVIE",
  "entity_id": ["test"]
}
```

**Response (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "entity_id": [
      "The entity ID must be a string."
    ]
  }
}
```

**Co się dzieje:**
- ❌ Walidacja nie przechodzi (string)
- ❌ Endpoint zwraca błąd walidacji

---

## 📊 Podsumowanie

| Scenariusz | Walidacja | Status Code | Response |
|------------|-----------|-------------|----------|
| **Liczba (1)** | ❌ Nie przechodzi | `422` | Błąd walidacji |
| **String ("test")** | ✅ Przechodzi | `202` | Job utworzony |
| **Pusty string ("")** | ❌ Nie przechodzi | `422` | Błąd walidacji |
| **Null** | ❌ Nie przechodzi | `422` | Błąd walidacji |
| **Brak pola** | ❌ Nie przechodzi | `422` | Błąd walidacji |
| **> 255 znaków** | ❌ Nie przechodzi | `422` | Błąd walidacji |
| **Array/Object** | ❌ Nie przechodzi | `422` | Błąd walidacji |

---

## ⚠️ Ważne Uwagi

### **1. Zawsze używaj stringa**

```json
// ❌ Błąd walidacji
{"entity_id": 1}

// ✅ Poprawne
{"entity_id": "the-matrix-1999"}
```

**Dlaczego?**
- Liczba `1` jest odrzucana przez walidację
- Tylko string jest akceptowany

---

### **2. Co się dzieje z nieistniejącym slugiem?**

**Request:**
```json
{
  "entity_type": "MOVIE",
  "entity_id": "non-existent-movie-slug"
}
```

**Response (202):**
```json
{
  "job_id": "uuid-here",
  "status": "PENDING",
  "message": "Generation queued for movie by slug",
  "slug": "non-existent-movie-slug"
}
```

**Co się dzieje:**
1. ✅ Kontroler sprawdza czy film istnieje → nie istnieje
2. ✅ Tworzy job_id i status PENDING
3. ✅ Emituje Event → Job dodany do kolejki
4. ⚠️ Job będzie próbował wygenerować film ze slugiem "non-existent-movie-slug"
5. ⚠️ OpenAI może nie znaleźć filmu i zwrócić ogólne dane lub błąd

**Wniosek:** Endpoint akceptuje każdy slug (jeśli przejdzie walidację), ale rezultat generacji zależy od tego czy OpenAI rozpozna slug.

---

## 🔍 Przykłady Testów

### **Test 1: Liczba (błąd walidacji)**
```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"entity_type": "MOVIE", "entity_id": 1}'
```

**Response (422):**
```json
{
  "message": "The entity ID must be a string.",
  "errors": {
    "entity_id": ["The entity ID must be a string."]
  }
}
```

---

### **Test 2: Pusty string (błąd)**
```bash
curl -X POST http://localhost:8000/api/v1/generate \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"entity_type": "MOVIE", "entity_id": ""}'
```

**Response (422):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "entity_id": ["The entity ID field is required."]
  }
}
```

---

## ✅ Rekomendacje

1. **Zawsze używaj stringa:**
   ```json
   {"entity_id": "the-matrix-1999"}  ✅
   ```

2. **Nie używaj liczb:**
   ```json
   {"entity_id": 1}  ❌ Błąd walidacji
   ```

3. **Używaj sensownych slugów:**
   ```json
   {"entity_id": "the-matrix-1999"}        ✅
   {"entity_id": "keanu-reeves"}            ✅
   {"entity_id": "1"}                      ⚠️
   {"entity_id": "random-string-xyz"}      ⚠️ Może nie działać w OpenAI
   ```

---

**Ostatnia aktualizacja:** 2025-11-01


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

### **3. Dlaczego niektóre slugi działają, a inne nie?**

#### **✅ Dobre slugi (rozpoznawalne przez OpenAI):**

```json
{"entity_id": "the-matrix-1999"}     ✅ Format: tytuł-film-rok
{"entity_id": "keanu-reeves"}         ✅ Format: imię-nazwisko
{"entity_id": "inception-2010"}       ✅ Rozpoznawalny film
{"entity_id": "christopher-nolan"}    ✅ Rozpoznawalny reżyser
```

**Dlaczego działają?**
- OpenAI rozpoznaje tytuły filmów i nazwiska osób
- Slug "the-matrix-1999" → OpenAI wie że to "The Matrix (1999)"
- Slug "keanu-reeves" → OpenAI wie że to "Keanu Reeves"
- AI ma wiedzę o popularnych filmach i osobach

#### **⚠️ Słabe slugi (mogą nie działać):**

```json
{"entity_id": "1"}                    ⚠️ Za krótki, bez kontekstu
{"entity_id": "random-string-xyz"}    ⚠️ Losowy string, OpenAI nie rozpozna
{"entity_id": "abc-def-ghi"}          ⚠️ Bez znaczenia dla AI
{"entity_id": "test-123"}             ⚠️ Zbyt ogólny
```

**Dlaczego mogą nie działać?**
- OpenAI nie rozpozna co to za film/osoba
- Może zwrócić ogólne/błędne dane
- Może zwrócić informacje o innym filmie/osobie (hallucination)
- Może zwrócić błąd lub pustą odpowiedź

---

### **4. Jak OpenAI używa slug?**

**Prompt wysyłany do OpenAI:**
```
Generate movie information for slug: the-matrix-1999. 
Return JSON with: title, release_year, director, description (movie plot), genres (array).
```

**Co się dzieje:**
1. Slug jest przekazywany bezpośrednio do promptu
2. OpenAI próbuje "zgadnąć" co to za film na podstawie slug
3. Jeśli rozpozna → zwraca prawdziwe dane
4. Jeśli NIE rozpozna → zwraca ogólne/błędne dane

**Przykład z "the-matrix-1999":**
- OpenAI rozpoznaje: "The Matrix (1999)"
- Zwraca: tytuł, rok, reżyser, opis, gatunki

**Przykład z "random-string-xyz":**
- OpenAI NIE rozpoznaje co to za film
- Może zwrócić: ogólny film, błąd, lub losowe dane
- Wynik będzie nieprzewidywalny

---

### **5. Co się dzieje gdy OpenAI nie rozpozna slug?**

**Scenariusz z "random-string-xyz":**

1. ✅ Walidacja przechodzi (slug jest stringiem)
2. ✅ Job jest tworzony (202 Accepted)
3. ⚠️ Job przetwarza slug w OpenAI
4. ⚠️ OpenAI nie rozpoznaje filmu → może zwrócić:
   - Ogólne dane (jakieś losowe informacje)
   - Błąd w odpowiedzi
   - Dane o innym filmie (hallucination)
5. ⚠️ Film zostaje utworzony z błędnymi danymi

**Przykładowy rezultat:**
```json
{
  "title": "Random String Xyz",  // ❌ Nieprawdziwe
  "release_year": 2000,          // ❌ Losowy rok
  "director": "Unknown",          // ❌ Brak danych
  "description": "Generic movie description...",  // ❌ Ogólny opis
  "genres": ["Action"]            // ❌ Losowy gatunek
}
```

**VS poprawny rezultat z "the-matrix-1999":**
```json
{
  "title": "The Matrix",          // ✅ Prawdziwe
  "release_year": 1999,           // ✅ Prawdziwe
  "director": "Lana Wachowski, Lilly Wachowski",  // ✅ Prawdziwe
  "description": "A computer hacker learns...",  // ✅ Prawdziwy opis
  "genres": ["Sci-Fi", "Action"]  // ✅ Prawdziwe gatunki
}
```

---

### **6. Rekomendacje dla slugów**

#### **✅ Format dla filmów:**
```json
{"entity_id": "tytul-film-rok"}        // np. "the-matrix-1999"
{"entity_id": "tytul-film"}             // np. "inception" (jeśli jednoznaczny)
```

#### **✅ Format dla osób:**
```json
{"entity_id": "imie-nazwisko"}         // np. "keanu-reeves"
{"entity_id": "imie-srodkowe-nazwisko"} // np. "christopher-nolan"
```

#### **❌ Unikaj:**
```json
{"entity_id": "1"}                     // Zbyt krótki
{"entity_id": "test"}                  // Zbyt ogólny
{"entity_id": "random-xyz"}            // Bez znaczenia
{"entity_id": "abc-123-def"}           // Losowy string
```

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
   {"entity_id": "the-matrix-1999"}        ✅ Rozpoznawalny film
   {"entity_id": "keanu-reeves"}            ✅ Rozpoznawalna osoba
   {"entity_id": "1"}                      ⚠️ Technicznie działa, ale OpenAI nie rozpozna
   {"entity_id": "random-string-xyz"}      ⚠️ OpenAI nie rozpozna - zwróci ogólne/błędne dane
   ```

---

**Ostatnia aktualizacja:** 2025-11-01

